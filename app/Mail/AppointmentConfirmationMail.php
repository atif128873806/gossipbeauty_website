<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly array $details) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->details['notice_type'] ?? null) {
            'appointment.changed' => 'Your Gossips Beauty appointment has changed',
            'appointment.cancelled' => 'Your Gossips Beauty appointment was cancelled',
            default => 'Your Gossips Beauty appointment is confirmed',
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.appointment-confirmation');
    }
}
