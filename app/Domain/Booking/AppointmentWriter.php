<?php

namespace App\Domain\Booking;

use App\Models\AddOn;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\BookingConfiguration;
use App\Models\BookingEvent;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** All website and admin appointment writes pass through this transaction boundary. */
class AppointmentWriter
{
    public function __construct(private readonly AvailabilityService $availability) {}

    /**
     * @param  list<int>  $serviceIds
     * @param  list<int>  $addOnIds
     * @param  array{first_name:string,last_name?:?string,phone:string,email?:?string,customer_note?:?string}  $contact
     */
    public function confirm(
        array $serviceIds,
        array $addOnIds,
        ?int $requestedStaffId,
        string $date,
        string $time,
        array $contact,
        string $idempotencyKey,
        string $origin = 'website',
        ?User $actor = null,
    ): Appointment {
        if (! in_array($origin, ['website', 'admin', 'phone'], true)) {
            throw ValidationException::withMessages(['booking' => 'This appointment source is not valid.']);
        }

        if ($serviceIds === [] || count($serviceIds) !== count(array_unique($serviceIds))) {
            throw ValidationException::withMessages(['service' => 'Choose at least one valid service.']);
        }

        if ($idempotencyKey === '' || strlen($idempotencyKey) > 80) {
            throw ValidationException::withMessages(['booking' => 'Please refresh the booking and try again.']);
        }

        $firstName = trim($contact['first_name'] ?? '');
        $lastName = trim($contact['last_name'] ?? '');
        $phone = $this->normalizePhone($contact['phone'] ?? '');
        $email = trim((string) ($contact['email'] ?? ''));
        $customerNote = trim((string) ($contact['customer_note'] ?? ''));

        if ($firstName === '' || mb_strlen($firstName) > 120) {
            throw ValidationException::withMessages(['first_name' => 'Enter your first name.']);
        }

        if ($lastName !== '' && mb_strlen($lastName) > 120) {
            throw ValidationException::withMessages(['last_name' => 'Your last name is too long.']);
        }

        if ($phone === null) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid phone number with its area or country code.']);
        }

        if ($email !== '' && (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255)) {
            throw ValidationException::withMessages(['email' => 'Enter a valid email address or leave it blank.']);
        }

        if (mb_strlen($customerNote) > 2000) {
            throw ValidationException::withMessages(['customer_note' => 'Keep your note under 2,000 characters.']);
        }

        return DB::transaction(function () use (
            $serviceIds, $addOnIds, $requestedStaffId, $date, $time, $firstName, $lastName,
            $phone, $email, $customerNote, $idempotencyKey, $origin, $actor,
        ): Appointment {
            // The single-studio row is a stable mutex and serializes booking writes
            // at the confirmed small-studio traffic level, including first-time
            // customer matching where no customer row exists yet.
            BookingConfiguration::query()->whereKey(1)->lockForUpdate()->first();
            $existing = Appointment::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing->load(['customer', 'staff', 'items']);
            }

            $services = $this->loadServices($serviceIds, $origin);
            $addOns = $this->loadAddOns($addOnIds);
            $phoneHash = hash_hmac('sha256', preg_replace('/\D+/', '', $phone), (string) config('app.key'));
            $existingCustomer = Customer::withTrashed()->where('phone_search_hash', $phoneHash)->first();
            $isNewCustomer = $existingCustomer === null;

            $config = BookingConfiguration::current();
            if ($origin === 'website' && ! $config->online_booking_enabled) {
                throw new BookingConflict('Online booking is not open right now.');
            }

            $day = CarbonImmutable::parse($date, $config->timezone)->startOfDay();
            $candidates = $this->slotsForSelection($services, $addOns, $requestedStaffId, $isNewCustomer, $day, $time, $origin);
            if ($candidates === []) {
                throw new BookingConflict('That time is no longer available. Choose another time.');
            }

            $staffIds = array_values(array_unique(array_column($candidates, 'staff_id')));
            $staffRows = Staff::query()->whereIn('id', $staffIds)->orderBy('id')->lockForUpdate()->get();
            if ($staffRows->count() !== count($staffIds)) {
                throw new BookingConflict('The selected professional is no longer available.');
            }

            $resourceIds = collect($services)
                ->flatMap(fn (Service $service) => $service->resources->pluck('id'))
                ->unique()->sort()->values();
            if ($resourceIds->isNotEmpty()) {
                \App\Models\Resource::query()->whereIn('id', $resourceIds)->orderBy('id')->lockForUpdate()->get();
            }

            // Recalculate only after stable staff rows have been locked. A competing booking
            // either committed before this check or will wait until this transaction commits.
            $candidates = $this->slotsForSelection($services, $addOns, $requestedStaffId, $isNewCustomer, $day, $time, $origin, true);
            $slot = $candidates[0] ?? null;
            if ($slot === null) {
                throw new BookingConflict('That time was just booked. Choose another time.');
            }

            $staff = Staff::query()->with('services')->findOrFail($slot['staff_id']);
            $customer = $existingCustomer ?? new Customer;
            if ($customer->trashed()) {
                $customer->restore();
            }
            $customer->fill([
                'first_name' => $firstName,
                'last_name' => $lastName !== '' ? $lastName : null,
                'phone' => $phone,
                'phone_search_hash' => $phoneHash,
                'email' => $email !== '' ? mb_strtolower($email) : null,
                'email_search_hash' => $email !== '' ? hash_hmac('sha256', mb_strtolower($email), (string) config('app.key')) : null,
                'last_seen_at' => now(),
            ]);
            $customer->save();

            $start = CarbonImmutable::parse($slot['instant'])->setTimezone('UTC');
            $end = CarbonImmutable::parse($slot['end_instant'])->setTimezone('UTC');
            $duration = (int) $slot['duration_minutes'];
            $beforeBuffer = (int) collect($services)->max('buffer_before_minutes');
            $afterBuffer = (int) collect($services)->max('buffer_after_minutes');
            [$priceMode, $priceMinor] = $this->priceSnapshot($staff, $services, $addOns);

            $appointment = Appointment::query()->create([
                'public_reference' => $this->newReference(),
                'customer_id' => $customer->id,
                'staff_id' => $staff->id,
                'starts_at_utc' => $start,
                'ends_at_utc' => $end,
                'busy_starts_at_utc' => $start->subMinutes($beforeBuffer),
                'busy_ends_at_utc' => $end->addMinutes($afterBuffer),
                'timezone' => $config->timezone,
                'status' => 'confirmed',
                'origin' => $origin,
                'idempotency_key' => $idempotencyKey,
                'price_mode' => $priceMode,
                'price_minor' => $priceMinor,
                'duration_minutes' => $duration,
                'policy_snapshot' => $config->policy_text ?? [],
                'customer_note' => $customerNote !== '' ? $customerNote : null,
                'created_by' => $actor?->id,
                'confirmed_at' => now(),
            ]);

            foreach ($services as $position => $service) {
                $serviceAssignment = $staff->services->firstWhere('id', $service->id)?->pivot;
                AppointmentItem::query()->create([
                    'appointment_id' => $appointment->id,
                    'service_id' => $service->id,
                    'item_type' => 'service',
                    'name_snapshot' => $service->name,
                    'duration_snapshot_minutes' => (int) ($serviceAssignment?->duration_override_minutes ?? $service->duration_minutes),
                    'price_mode_snapshot' => $service->price_mode,
                    'price_snapshot_minor' => $serviceAssignment?->price_override_minor ?? $service->price_minor,
                    'position' => $position,
                ]);
            }

            foreach ($addOns as $position => $addOn) {
                AppointmentItem::query()->create([
                    'appointment_id' => $appointment->id,
                    'add_on_id' => $addOn->id,
                    'item_type' => 'add_on',
                    'name_snapshot' => $addOn->name,
                    'duration_snapshot_minutes' => (int) $addOn->duration_minutes,
                    'price_mode_snapshot' => $addOn->price_mode,
                    'price_snapshot_minor' => $addOn->price_minor,
                    'position' => count($services) + $position,
                ]);
            }

            $requirements = collect($services)->flatMap(fn (Service $service) => $service->resources->map(fn ($resource) => [
                'resource' => $resource,
                'units' => (int) $resource->pivot->units_required,
            ]))->groupBy(fn (array $item) => $item['resource']->id);

            foreach ($requirements as $resourceId => $items) {
                DB::table('appointment_resources')->insert([
                    'appointment_id' => $appointment->id,
                    'resource_id' => $resourceId,
                    'units_reserved' => $items->sum('units'),
                ]);
            }

            BookingEvent::query()->create([
                'appointment_id' => $appointment->id,
                'actor_user_id' => $actor?->id,
                'actor_type' => $actor === null ? 'customer' : 'staff',
                'event_type' => 'appointment.confirmed',
                'changes' => [
                    'status' => ['from' => null, 'to' => 'confirmed'],
                    'staff_id' => ['from' => null, 'to' => $staff->id],
                    'starts_at_utc' => ['from' => null, 'to' => $start->toIso8601String()],
                    'origin' => $origin,
                ],
                'source' => $origin,
            ]);

            return $appointment->load(['customer', 'staff', 'items']);
        }, 3);
    }

    /** @param list<int> $serviceIds
     * @return list<Service>
     */
    private function loadServices(array $serviceIds, string $origin): array
    {
        $services = Service::query()
            ->whereIn('id', $serviceIds)
            ->orderBy('id')
            ->where('is_active', true)
            ->when($origin === 'website', fn ($query) => $query->where('is_online_bookable', true))
            ->with(['addOns', 'resources'])
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($services->count() !== count($serviceIds)) {
            throw ValidationException::withMessages(['service' => 'One of those services is no longer available. Choose again.']);
        }

        return collect($serviceIds)->map(fn ($id) => $services->get($id))->all();
    }

    /** @param list<int> $addOnIds */
    private function loadAddOns(array $addOnIds): array
    {
        if ($addOnIds === []) {
            return [];
        }

        if (count($addOnIds) !== count(array_unique($addOnIds))) {
            throw ValidationException::withMessages(['extras' => 'Choose each extra only once.']);
        }

        $addOns = AddOn::query()->whereIn('id', $addOnIds)->where('is_active', true)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if ($addOns->count() !== count($addOnIds)) {
            throw ValidationException::withMessages(['extras' => 'One of those extras is no longer available. Choose again.']);
        }

        return collect($addOnIds)->map(fn ($id) => $addOns->get($id))->all();
    }

    /** @param list<Service> $services
     * @param  list<AddOn>  $addOns
     * @return list<array<string,mixed>>
     */
    private function slotsForSelection(array $services, array $addOns, ?int $staffId, bool $isNewCustomer, CarbonImmutable $day, string $time, string $origin, bool $lockForUpdate = false): array
    {
        $possible = $this->availability->slots(
            $services,
            array_map(fn (AddOn $addOn) => (int) $addOn->id, $addOns),
            $staffId,
            $isNewCustomer,
            $day,
            $day,
            $origin === 'website',
            null,
            $lockForUpdate,
        );

        return array_values(array_filter($possible, static fn (array $slot): bool => $slot['time'] === $time));
    }

    /** @param list<Service> $services
     * @param  list<AddOn>  $addOns
     * @return array{string,?int}
     */
    private function priceSnapshot(Staff $staff, array $services, array $addOns): array
    {
        $total = 0;
        $hasFrom = false;
        $hasQuote = false;
        $hasCharge = false;

        foreach ($services as $service) {
            $pivot = $staff->services->firstWhere('id', $service->id)?->pivot;
            $mode = $service->price_mode;
            $amount = $pivot?->price_override_minor ?? $service->price_minor;
            $hasQuote = $hasQuote || $mode === 'quote' || $amount === null;
            $hasFrom = $hasFrom || $mode === 'from';
            $hasCharge = $hasCharge || ($mode !== 'free' && (int) $amount > 0);
            $total += (int) ($amount ?? 0);
        }

        foreach ($addOns as $addOn) {
            $mode = $addOn->price_mode;
            $amount = $addOn->price_minor;
            $hasQuote = $hasQuote || $mode === 'quote' || $amount === null;
            $hasFrom = $hasFrom || $mode === 'from';
            $hasCharge = $hasCharge || ($mode !== 'free' && (int) $amount > 0);
            $total += (int) ($amount ?? 0);
        }

        if ($hasQuote) {
            return ['quote', null];
        }
        if (! $hasCharge) {
            return ['free', 0];
        }

        return [$hasFrom ? 'from' : 'fixed', $total];
    }

    private function normalizePhone(string $phone): ?string
    {
        $phone = trim($phone);
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === null || strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        return (str_starts_with($phone, '+') ? '+' : '').$digits;
    }

    private function newReference(): string
    {
        do {
            $reference = 'GB-'.now()->format('ym').'-'.Str::upper(Str::random(6));
        } while (Appointment::query()->where('public_reference', $reference)->exists());

        return $reference;
    }
}
