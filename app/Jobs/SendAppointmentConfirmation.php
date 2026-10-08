<?php

namespace App\Jobs;

use App\Mail\AppointmentConfirmationMail;
use App\Models\NotificationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendAppointmentConfirmation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 600];

    public function __construct(public readonly int $deliveryId) {}

    public function handle(): void
    {
        $delivery = NotificationDelivery::query()->with(['appointment.customer', 'appointment.staff', 'appointment.items'])->findOrFail($this->deliveryId);
        if ($delivery->status === 'sent') {
            return;
        }
        $appointment = $delivery->appointment;
        if (! $appointment || ! $appointment->customer || ! $delivery->recipient) {
            $delivery->update(['status' => 'failed', 'last_error' => 'Confirmation recipient or appointment is unavailable.']);

            return;
        }
        $delivery->update(['attempts' => $delivery->attempts + 1, 'last_attempt_at' => now()]);
        $details = [
            'first_name' => $appointment->customer->first_name,
            'reference' => $appointment->public_reference,
            'services' => $appointment->items->pluck('name_snapshot')->all(),
            'staff' => $appointment->staff?->name,
            'time' => $appointment->starts_at_utc->setTimezone($appointment->timezone)->format('l, F j, Y · g:i A'),
            'address' => config('gossips.location.address_line').', '.config('gossips.location.city'),
            'notice_type' => $delivery->notification_type,
        ];
        Mail::to($delivery->recipient)->send(new AppointmentConfirmationMail($details));
        $delivery->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        NotificationDelivery::query()->whereKey($this->deliveryId)->update([
            'status' => 'failed', 'last_error' => mb_substr($exception?->getMessage() ?? 'Delivery failed.', 0, 4000),
        ]);
    }
}
