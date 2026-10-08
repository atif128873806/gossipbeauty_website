<?php

namespace App\Domain\Booking;

use App\Content\Pricing;
use App\Models\AddOn;
use App\Models\Appointment;
use App\Models\BookingConfiguration;
use App\Models\ScheduleException;
use App\Models\Service;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The canonical availability calculation used by website and admin booking actions.
 * Public results are suggestions; AppointmentWriter rechecks the same constraints while
 * holding locks before it creates or moves anything.
 */
class AvailabilityService
{
    /**
     * @param  list<Service>  $services
     * @param  list<int>  $addOnIds
     * @return list<array{date: string, time: string, instant: string, end_instant: string, staff_id: int, staff_name: string, duration_minutes: int, price: array{label: string, value: string, note: ?string}}>
     */
    public function slots(
        array $services,
        array $addOnIds,
        ?int $requestedStaffId,
        bool $isNewCustomer,
        CarbonImmutable $fromDate,
        CarbonImmutable $throughDate,
        bool $publicBooking = true,
        ?int $ignoreAppointmentId = null,
        bool $lockForUpdate = false,
        ?int $durationOverrideMinutes = null,
        bool $allowHistoricalAddOns = false,
    ): array {
        $settings = BookingConfiguration::current();

        if ($services === [] || ($publicBooking && ! $settings->online_booking_enabled)) {
            return [];
        }

        $timezone = (string) $settings->timezone;
        $tz = new DateTimeZone($timezone);
        $nowUtc = CarbonImmutable::now('UTC');
        $earliest = $nowUtc->addMinutes((int) $settings->minimum_notice_minutes);
        $lastDate = $nowUtc->setTimezone($tz)->startOfDay()->addDays((int) $settings->booking_horizon_days);
        $from = $fromDate->setTimezone($tz)->startOfDay()->max($nowUtc->setTimezone($tz)->startOfDay());
        $through = $throughDate->setTimezone($tz)->startOfDay()->min($lastDate);

        if ($through->lessThan($from)) {
            return [];
        }

        $serviceIds = collect($services)->map(fn (Service $service) => (int) $service->getKey())->unique()->values();
        $addOns = $this->compatibleAddOns($services, $addOnIds, $allowHistoricalAddOns);

        if ($addOns === null) {
            return [];
        }

        $staffMembers = $this->eligibleStaff($serviceIds, $requestedStaffId, $isNewCustomer, $lockForUpdate);
        $staffIds = $staffMembers->pluck('id')->map(fn ($id) => (int) $id)->all();
        $windowStartUtc = $from->setTimezone('UTC')->subDay();
        $windowEndUtc = $through->addDay()->setTimezone('UTC');
        $appointments = Appointment::query()->whereIn('staff_id', $staffIds)
            ->whereIn('status', Appointment::CAPACITY_HOLDING_STATUSES)
            ->where('busy_starts_at_utc', '<', $windowEndUtc->format('Y-m-d H:i:s'))
            ->where('busy_ends_at_utc', '>', $windowStartUtc->format('Y-m-d H:i:s'))
            ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
            ->get(['id', 'staff_id', 'busy_starts_at_utc', 'busy_ends_at_utc']);
        $exceptions = ScheduleException::query()
            ->whereIn('kind', ['break', 'time_off', 'closure'])
            ->where(fn ($query) => $query->whereNull('staff_id')->orWhereIn('staff_id', $staffIds))
            ->where('starts_at_utc', '<', $windowEndUtc->format('Y-m-d H:i:s'))
            ->where('ends_at_utc', '>', $windowStartUtc->format('Y-m-d H:i:s'))
            ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
            ->get(['id', 'staff_id', 'kind', 'starts_at_utc', 'ends_at_utc']);
        $results = [];

        for ($day = $from; $day->lessThanOrEqualTo($through); $day = $day->addDay()) {
            foreach ($staffMembers as $staff) {
                foreach ($staff->workingHours as $hours) {
                    if ((int) $hours->weekday !== $day->dayOfWeek || ! $this->hoursApply($hours, $day)) {
                        continue;
                    }

                    $opens = self::localInstant($day->format('Y-m-d'), substr((string) $hours->opens_at, 0, 5), $timezone);
                    $closes = self::localInstant($day->format('Y-m-d'), substr((string) $hours->closes_at, 0, 5), $timezone);

                    if ($opens === null || $closes === null || $closes->lessThanOrEqualTo($opens)) {
                        continue;
                    }

                    $minutes = $durationOverrideMinutes ?? $this->durationFor($staff, $services, $addOns);
                    if ($minutes <= 0) {
                        continue;
                    }

                    $buffers = $this->buffers($services);
                    $stepMinutes = max(5, (int) $settings->slot_increment_minutes);

                    for ($cursor = $opens; $cursor->lessThan($closes); $cursor = $cursor->addMinutes($stepMinutes)) {
                        $local = $cursor->setTimezone($tz);
                        $start = self::localInstant($local->format('Y-m-d'), $local->format('H:i'), $timezone);
                        if ($start === null || $start->lessThan($earliest)) {
                            continue;
                        }

                        $end = $start->addMinutes($minutes);
                        $busyStart = $start->subMinutes($buffers['before']);
                        $busyEnd = $end->addMinutes($buffers['after']);

                        if ($end->greaterThan($closes) || ! $this->insideHours($staff, $busyStart, $busyEnd, $timezone)) {
                            continue;
                        }

                        if ($this->hasScheduleException($staff, $busyStart, $busyEnd, $exceptions)
                            || $this->hasStaffConflict($staff, $busyStart, $busyEnd, $appointments, $ignoreAppointmentId)
                            || $this->resourcesUnavailable($services, $busyStart, $busyEnd, $ignoreAppointmentId, $lockForUpdate)) {
                            continue;
                        }

                        $price = $this->priceFor($staff, $services, $addOns);
                        $results[] = [
                            'date' => $local->format('Y-m-d'),
                            'time' => $local->format('H:i'),
                            'instant' => $start->toIso8601String(),
                            'end_instant' => $end->toIso8601String(),
                            'staff_id' => (int) $staff->id,
                            'staff_name' => (string) $staff->name,
                            'duration_minutes' => $minutes,
                            'price' => $price,
                        ];
                    }
                }
            }
        }

        usort($results, static fn (array $a, array $b): int => [$a['instant'], $a['staff_id']] <=> [$b['instant'], $b['staff_id']]);

        return $results;
    }

    /** @param  Collection<int, Service>|list<Service>  $services */
    private function compatibleAddOns(array $services, array $addOnIds, bool $allowHistorical = false): ?Collection
    {
        $ids = collect($addOnIds)->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->count() !== count($addOnIds)) {
            return null;
        }

        if ($allowHistorical) {
            return $ids->isEmpty() ? collect() : AddOn::query()->whereIn('id', $ids)->get();
        }

        $services = collect($services);
        $compatibleIds = $services->flatMap(fn (Service $service) => $service->addOns()->pluck('add_ons.id'))->unique();

        if ($ids->diff($compatibleIds)->isNotEmpty()) {
            return null;
        }

        $requiredIds = $services->flatMap(fn (Service $service) => $service->addOns()
            ->wherePivot('is_required', true)->pluck('add_ons.id'))->unique();

        if ($requiredIds->diff($ids)->isNotEmpty()) {
            return null;
        }

        return $ids->isEmpty()
            ? collect()
            : AddOn::query()->whereIn('id', $ids)->where('is_active', true)->get();
    }

    /** @param  Collection<int, int>  $serviceIds
     * @return Collection<int, Staff>
     */
    private function eligibleStaff(Collection $serviceIds, ?int $requestedStaffId, bool $isNewCustomer, bool $lockForUpdate = false): Collection
    {
        $query = Staff::query()
            ->where('is_active', true)
            ->where('is_bookable', true)
            ->with([
                'workingHours' => fn ($relation) => $lockForUpdate ? $relation->lockForUpdate() : $relation,
                'services' => fn ($relation) => $lockForUpdate ? $relation->lockForUpdate() : $relation,
            ])
            ->when($lockForUpdate, fn ($query) => $query->lockForUpdate());

        if ($requestedStaffId !== null) {
            $query->whereKey($requestedStaffId);
        }

        if ($isNewCustomer) {
            $query->where('accepts_new_clients', true);
        }

        return $query->get()->filter(function (Staff $staff) use ($serviceIds, $isNewCustomer): bool {
            $eligibleServices = $staff->services->filter(fn (Service $service) => $serviceIds->contains((int) $service->id)
                && (! $isNewCustomer || (bool) $service->pivot->accepts_new_clients)
            )->count();

            return $eligibleServices === $serviceIds->count();
        })->values();
    }

    private function hoursApply(object $hours, CarbonImmutable $day): bool
    {
        return ($hours->effective_from === null || $day->toDateString() >= $hours->effective_from->toDateString())
            && ($hours->effective_until === null || $day->toDateString() <= $hours->effective_until->toDateString());
    }

    /** @param list<Service> $services */
    private function durationFor(Staff $staff, array $services, Collection $addOns): int
    {
        $total = 0;

        foreach ($services as $service) {
            $assignment = $staff->services->firstWhere('id', $service->id)?->pivot;
            $duration = $assignment?->duration_override_minutes ?? $service->duration_minutes;
            if ($duration === null) {
                return 0;
            }
            $total += (int) $duration;
        }

        return $total + (int) $addOns->sum('duration_minutes');
    }

    /** @param list<Service> $services
     * @return array{before:int,after:int}
     */
    private function buffers(array $services): array
    {
        return [
            'before' => (int) collect($services)->max('buffer_before_minutes'),
            'after' => (int) collect($services)->max('buffer_after_minutes'),
        ];
    }

    private function insideHours(Staff $staff, CarbonImmutable $busyStart, CarbonImmutable $busyEnd, string $timezone): bool
    {
        $tz = new DateTimeZone($timezone);
        $localDate = $busyStart->setTimezone($tz);
        $hoursForDay = $staff->workingHours->filter(fn ($hours) => (int) $hours->weekday === $localDate->dayOfWeek);

        foreach ($hoursForDay as $hours) {
            $day = CarbonImmutable::parse($localDate->toDateString(), $tz);
            $open = self::localInstant($day->format('Y-m-d'), substr((string) $hours->opens_at, 0, 5), $timezone);
            $close = self::localInstant($day->format('Y-m-d'), substr((string) $hours->closes_at, 0, 5), $timezone);
            if ($open !== null && $close !== null && $busyStart->greaterThanOrEqualTo($open) && $busyEnd->lessThanOrEqualTo($close)) {
                return true;
            }
        }

        return false;
    }

    private function hasScheduleException(Staff $staff, CarbonImmutable $busyStart, CarbonImmutable $busyEnd, Collection $exceptions): bool
    {
        return $exceptions->contains(fn (ScheduleException $exception) => ($exception->staff_id === null || (int) $exception->staff_id === (int) $staff->id)
            && $exception->starts_at_utc->lessThan($busyEnd)
            && $exception->ends_at_utc->greaterThan($busyStart)
        );
    }

    private function hasStaffConflict(Staff $staff, CarbonImmutable $busyStart, CarbonImmutable $busyEnd, Collection $appointments, ?int $ignoreAppointmentId = null): bool
    {
        return $appointments->contains(fn (Appointment $appointment) => (int) $appointment->staff_id === (int) $staff->id
            && (int) $appointment->id !== (int) $ignoreAppointmentId
            && $appointment->busy_starts_at_utc->lessThan($busyEnd)
            && $appointment->busy_ends_at_utc->greaterThan($busyStart)
        );
    }

    /** @param list<Service> $services */
    private function resourcesUnavailable(array $services, CarbonImmutable $busyStart, CarbonImmutable $busyEnd, ?int $ignoreAppointmentId = null, bool $lockForUpdate = false): bool
    {
        $requirements = collect($services)->flatMap(fn (Service $service) => $service->resources->map(fn ($resource) => [
            'resource' => $resource,
            'units' => (int) $resource->pivot->units_required,
        ]))->groupBy(fn (array $item) => $item['resource']->id);

        foreach ($requirements as $items) {
            $resource = $items->first()['resource'];
            $needed = (int) $items->sum('units');
            $usageQuery = DB::table('appointment_resources')
                ->join('appointments', 'appointments.id', '=', 'appointment_resources.appointment_id')
                ->where('appointment_resources.resource_id', $resource->id)
                ->whereIn('appointments.status', Appointment::CAPACITY_HOLDING_STATUSES)
                ->when($ignoreAppointmentId !== null, fn ($query) => $query->where('appointments.id', '!=', $ignoreAppointmentId))
                ->where('appointments.busy_starts_at_utc', '<', $busyEnd->format('Y-m-d H:i:s'))
                ->where('appointments.busy_ends_at_utc', '>', $busyStart->format('Y-m-d H:i:s'));
            if ($lockForUpdate) {
                $usageQuery->lockForUpdate();
            }
            $alreadyUsed = $lockForUpdate ? $usageQuery->pluck('appointment_resources.units_reserved')->sum() : $usageQuery->sum('appointment_resources.units_reserved');

            if ($alreadyUsed + $needed > (int) $resource->capacity) {
                return true;
            }
        }

        return false;
    }

    /** @param list<Service> $services */
    private function priceFor(Staff $staff, array $services, Collection $addOns): array
    {
        $items = [];
        foreach ($services as $service) {
            $pivot = $staff->services->firstWhere('id', $service->id)?->pivot;
            $items[] = [
                'price_mode' => $service->price_mode,
                'price_minor' => $pivot?->price_override_minor ?? $service->price_minor,
            ];
        }

        foreach ($addOns as $addOn) {
            $items[] = ['price_mode' => $addOn->price_mode, 'price_minor' => $addOn->price_minor];
        }

        return Pricing::summary($items);
    }

    /**
     * Return one unique local wall-clock time as an instant. Nonexistent spring-forward
     * times and repeated fall-back times are omitted because the customer-facing time label
     * has no offset with which to distinguish two moments.
     */
    public static function localInstant(string $date, string $time, string $timezone): ?CarbonImmutable
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $dateParts) !== 1
            || ! checkdate((int) $dateParts[2], (int) $dateParts[3], (int) $dateParts[1])
            || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
            return null;
        }

        $zone = new DateTimeZone($timezone);
        $wallClock = CarbonImmutable::createFromFormat('!Y-m-d H:i', $date.' '.$time, 'UTC');
        if ($wallClock === false) {
            return null;
        }

        $possible = [];
        $transitions = $zone->getTransitions($wallClock->timestamp - 172800, $wallClock->timestamp + 172800) ?: [];
        $offsets = array_unique(array_column($transitions, 'offset'));

        foreach ($offsets as $offset) {
            $candidate = CarbonImmutable::createFromTimestampUTC($wallClock->timestamp - (int) $offset)->setTimezone($zone);
            if ($candidate->format('Y-m-d H:i') === $date.' '.$time) {
                $possible[$candidate->timestamp] = $candidate;
            }
        }

        if (count($possible) !== 1) {
            return null;
        }

        return array_values($possible)[0];
    }
}
