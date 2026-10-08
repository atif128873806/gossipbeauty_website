<?php

namespace App\Domain\Booking;

use App\Models\Appointment;
use App\Models\BookingEvent;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminAppointmentManager
{
    public function updateStatus(Appointment $appointment, string $status, User $actor, ?string $reason = null): Appointment
    {
        if (! in_array($status, ['completed', 'cancelled', 'no_show'], true)) {
            throw ValidationException::withMessages(['status' => 'Choose a supported appointment status.']);
        }
        if (! $actor->canManageAppointments() && $actor->staff_id !== $appointment->staff_id) {
            abort(403);
        }

        return DB::transaction(function () use ($appointment, $status, $actor, $reason): Appointment {
            $staff = $appointment->staff_id;
            if ($staff !== null) {
                Staff::query()->whereKey($staff)->lockForUpdate()->first();
            }
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            if (! in_array($locked->status, Appointment::CAPACITY_HOLDING_STATUSES, true)) {
                throw ValidationException::withMessages(['status' => 'This appointment has already been closed.']);
            }
            if ($status === 'cancelled' && trim((string) $reason) === '') {
                throw ValidationException::withMessages(['reason' => 'Enter a cancellation reason.']);
            }
            $before = $locked->status;
            $locked->status = $status;
            if ($status === 'cancelled') {
                $locked->cancelled_at = now();
                $locked->cancellation_reason = mb_substr(trim((string) $reason), 0, 255);
            }
            $locked->save();
            BookingEvent::query()->create([
                'appointment_id' => $locked->id, 'actor_user_id' => $actor->id,
                'actor_type' => 'staff', 'event_type' => 'appointment.status_changed',
                'changes' => ['status' => ['from' => $before, 'to' => $status], 'reason' => $reason], 'source' => 'admin',
            ]);

            return $locked;
        }, 3);
    }
}
