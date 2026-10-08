<?php

namespace App\Domain\Booking;

use App\Models\AddOn;
use App\Models\Appointment;
use App\Models\BookingEvent;
use App\Models\Resource;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentRescheduler
{
    public function __construct(private readonly AvailabilityService $availability) {}

    public function move(Appointment $appointment, string $date, string $time, ?int $requestedStaffId, User $actor): Appointment
    {
        if (! $actor->canManageAppointments() && $actor->staff_id !== $appointment->staff_id) {
            abort(403);
        }
        if (! $actor->canManageAppointments() && $requestedStaffId !== null && $requestedStaffId !== $appointment->staff_id) {
            abort(403);
        }
        if (! $actor->canManageAppointments()) {
            $requestedStaffId = $appointment->staff_id;
        }

        return DB::transaction(function () use ($appointment, $date, $time, $requestedStaffId, $actor): Appointment {
            $lockedAppointment = Appointment::query()->findOrFail($appointment->id);
            $items = $lockedAppointment->items()->get();
            $serviceIds = $items->where('item_type', 'service')->pluck('service_id')->filter()->map(fn ($id) => (int) $id)->all();
            $addOnIds = $items->where('item_type', 'add_on')->pluck('add_on_id')->filter()->map(fn ($id) => (int) $id)->all();
            if ($serviceIds === []) {
                throw ValidationException::withMessages(['appointment' => 'This appointment has no service details that can be rescheduled.']);
            }
            Service::query()->whereIn('id', $serviceIds)->orderBy('id')->lockForUpdate()->get();
            if ($addOnIds !== []) {
                AddOn::query()->whereIn('id', $addOnIds)->orderBy('id')->lockForUpdate()->get();
            }
            $servicesById = Service::query()->whereIn('id', $serviceIds)->with(['resources', 'addOns'])->get()->keyBy('id');
            if ($servicesById->count() !== count(array_unique($serviceIds))) {
                throw ValidationException::withMessages(['appointment' => 'A service on this appointment is no longer available for rescheduling.']);
            }
            $services = array_map(fn ($id) => $servicesById->get($id), $serviceIds);
            $newCustomer = $lockedAppointment->customer_id === null;
            $tz = $lockedAppointment->timezone;
            $day = CarbonImmutable::parse($date, $tz)->startOfDay();
            $candidates = $this->candidates($services, $addOnIds, $requestedStaffId, $newCustomer, $day, $time, $lockedAppointment->id, (int) $lockedAppointment->duration_minutes);
            if ($candidates === []) {
                throw new BookingConflict('That time is not available for this appointment.');
            }
            $staffIds = collect(array_column($candidates, 'staff_id'))->push($lockedAppointment->staff_id)->filter()->unique()->sort()->values();
            $staffRows = Staff::query()->whereIn('id', $staffIds)->orderBy('id')->lockForUpdate()->get();
            if ($staffRows->count() !== $staffIds->count()) {
                throw new BookingConflict('The selected professional is no longer available.');
            }
            $current = Appointment::query()->whereKey($lockedAppointment->id)->lockForUpdate()->firstOrFail();
            if ($current->staff_id !== $lockedAppointment->staff_id
                || ! $current->starts_at_utc->equalTo($lockedAppointment->starts_at_utc)
                || ! $current->ends_at_utc->equalTo($lockedAppointment->ends_at_utc)
                || $current->duration_minutes !== $lockedAppointment->duration_minutes
                || ! in_array($current->status, Appointment::CAPACITY_HOLDING_STATUSES, true)) {
                throw new BookingConflict('This appointment changed while you were editing it. Reload the calendar and try again.');
            }
            $lockedAppointment = $current;
            $resourceIds = collect($services)->flatMap(fn (Service $service) => $service->resources->pluck('id'))->unique()->sort()->values();
            if ($resourceIds->isNotEmpty()) {
                Resource::query()->whereIn('id', $resourceIds)->orderBy('id')->lockForUpdate()->get();
            }
            $candidates = $this->candidates($services, $addOnIds, $requestedStaffId, $newCustomer, $day, $time, $lockedAppointment->id, (int) $lockedAppointment->duration_minutes, true);
            $slot = $candidates[0] ?? null;
            if ($slot === null) {
                throw new BookingConflict('That time was just booked. Choose another time.');
            }
            $staff = $staffRows->firstWhere('id', $slot['staff_id']);
            $start = CarbonImmutable::parse($slot['instant'])->setTimezone('UTC');
            $end = CarbonImmutable::parse($slot['end_instant'])->setTimezone('UTC');
            $before = (int) collect($services)->max('buffer_before_minutes');
            $after = (int) collect($services)->max('buffer_after_minutes');
            $old = ['staff_id' => $lockedAppointment->staff_id, 'starts_at_utc' => $lockedAppointment->starts_at_utc->toIso8601String(), 'ends_at_utc' => $lockedAppointment->ends_at_utc->toIso8601String()];
            $lockedAppointment->update([
                'staff_id' => $staff->id, 'starts_at_utc' => $start, 'ends_at_utc' => $end,
                'busy_starts_at_utc' => $start->subMinutes($before), 'busy_ends_at_utc' => $end->addMinutes($after),
                'duration_minutes' => $slot['duration_minutes'],
            ]);
            $requirements = collect($services)->flatMap(fn (Service $service) => $service->resources->map(fn ($resource) => [
                'id' => (int) $resource->id, 'units' => (int) $resource->pivot->units_required,
            ]))->groupBy('id')->map(fn ($rows) => $rows->sum('units'));
            $lockedAppointment->resources()->sync($requirements->mapWithKeys(fn ($units, $id) => [$id => ['units_reserved' => $units]])->all());
            BookingEvent::query()->create([
                'appointment_id' => $lockedAppointment->id, 'actor_user_id' => $actor->id,
                'actor_type' => 'staff', 'event_type' => 'appointment.rescheduled',
                'changes' => ['from' => $old, 'to' => ['staff_id' => $staff->id, 'starts_at_utc' => $start->toIso8601String(), 'ends_at_utc' => $end->toIso8601String()]], 'source' => 'admin',
            ]);

            return $lockedAppointment->load(['customer', 'staff', 'items']);
        }, 3);
    }

    private function candidates(array $services, array $addOnIds, ?int $staffId, bool $isNew, CarbonImmutable $day, string $time, int $ignoreId, int $durationMinutes, bool $lockForUpdate = false): array
    {
        return array_values(array_filter($this->availability->slots(
            $services, $addOnIds, $staffId, $isNew, $day, $day, false, $ignoreId,
            $lockForUpdate, $durationMinutes, true,
        ), fn (array $slot) => $slot['time'] === $time));
    }
}
