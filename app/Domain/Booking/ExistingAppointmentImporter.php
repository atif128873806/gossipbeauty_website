<?php

namespace App\Domain\Booking;

use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\BookingConfiguration;
use App\Models\BookingEvent;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Records an existing third-party reservation in the same calendar without importing customer data. */
class ExistingAppointmentImporter
{
    public function import(
        string $serviceLabel,
        int $staffId,
        string $date,
        string $startTime,
        string $endTime,
        ?string $sourceReference,
        User $actor,
    ): Appointment {
        $serviceLabel = trim($serviceLabel);
        $sourceReference = trim((string) $sourceReference);
        if ($serviceLabel === '' || mb_strlen($serviceLabel) > 160) {
            throw ValidationException::withMessages(['service_label' => 'Enter the service name shown in the existing booking.']);
        }
        if (mb_strlen($sourceReference) > 80) {
            throw ValidationException::withMessages(['source_reference' => 'Keep the source reference under 80 characters.']);
        }

        $configuration = BookingConfiguration::current();
        $timezone = (string) $configuration->timezone;
        $start = AvailabilityService::localInstant($date, $startTime, $timezone);
        $end = AvailabilityService::localInstant($date, $endTime, $timezone);
        if ($start === null || $end === null || ! $end->greaterThan($start)) {
            throw ValidationException::withMessages(['start_time' => 'Enter a valid Toronto time range on the same date.']);
        }
        if (! $start->greaterThan(CarbonImmutable::now($timezone))) {
            throw ValidationException::withMessages(['date' => 'Only upcoming appointments can be added to the cutover calendar.']);
        }

        $startUtc = $start->setTimezone('UTC');
        $endUtc = $end->setTimezone('UTC');
        $duration = (int) $start->diffInMinutes($end);
        $idempotencyKey = $sourceReference !== ''
            ? 'dikidi:'.hash('sha256', $sourceReference)
            : Str::uuid()->toString();

        return DB::transaction(function () use (
            $serviceLabel, $staffId, $sourceReference, $idempotencyKey, $timezone, $startUtc, $endUtc, $duration, $actor,
        ): Appointment {
            BookingConfiguration::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $existing = Appointment::query()
                ->where('idempotency_key', $idempotencyKey)
                ->when($sourceReference !== '', fn ($query) => $query->orWhere(fn ($query) => $query
                    ->where('origin', 'dikidi')
                    ->where('external_reference', $sourceReference)))
                ->first();
            if ($existing !== null) {
                return $existing->load(['customer', 'staff', 'items']);
            }

            Staff::query()->whereKey($staffId)->lockForUpdate()->firstOrFail();
            $conflict = Appointment::query()
                ->where('staff_id', $staffId)
                ->whereIn('status', Appointment::CAPACITY_HOLDING_STATUSES)
                ->where('busy_starts_at_utc', '<', $endUtc->format('Y-m-d H:i:s'))
                ->where('busy_ends_at_utc', '>', $startUtc->format('Y-m-d H:i:s'))
                ->lockForUpdate()
                ->exists();
            if ($conflict) {
                throw new BookingConflict('This existing appointment overlaps a reservation already in the Gossips calendar. Resolve the conflict before continuing.');
            }

            $appointment = Appointment::query()->create([
                'public_reference' => $this->newReference(),
                'staff_id' => $staffId,
                'starts_at_utc' => $startUtc,
                'ends_at_utc' => $endUtc,
                'busy_starts_at_utc' => $startUtc,
                'busy_ends_at_utc' => $endUtc,
                'timezone' => $timezone,
                'status' => 'confirmed',
                'origin' => 'dikidi',
                'idempotency_key' => $idempotencyKey,
                'external_reference' => $sourceReference !== '' ? $sourceReference : null,
                'price_mode' => 'quote',
                'price_minor' => null,
                'duration_minutes' => $duration,
                'policy_snapshot' => [],
                'created_by' => $actor->id,
                'confirmed_at' => now(),
            ]);
            AppointmentItem::query()->create([
                'appointment_id' => $appointment->id,
                'item_type' => 'service',
                'name_snapshot' => $serviceLabel,
                'duration_snapshot_minutes' => $duration,
                'price_mode_snapshot' => 'quote',
                'price_snapshot_minor' => null,
                'position' => 0,
            ]);
            BookingEvent::query()->create([
                'appointment_id' => $appointment->id,
                'actor_user_id' => $actor->id,
                'actor_type' => 'staff',
                'event_type' => 'appointment.imported',
                'changes' => [
                    'source' => 'dikidi',
                    'staff_id' => $staffId,
                    'starts_at_utc' => $startUtc->toIso8601String(),
                    'ends_at_utc' => $endUtc->toIso8601String(),
                    'source_reference_hash' => $sourceReference === '' ? null : substr(hash('sha256', $sourceReference), 0, 16),
                ],
                'source' => 'dikidi',
            ]);

            return $appointment->load(['customer', 'staff', 'items']);
        }, 3);
    }

    private function newReference(): string
    {
        do {
            $reference = 'GB-'.now()->format('ym').'-'.Str::upper(Str::random(6));
        } while (Appointment::query()->where('public_reference', $reference)->exists());

        return $reference;
    }
}
