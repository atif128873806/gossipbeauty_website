<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'appointment_id', 'notification_type', 'channel', 'recipient', 'status', 'attempts',
    'provider_reference', 'last_error', 'last_attempt_at', 'sent_at',
])]
class NotificationDelivery extends Model
{
    protected function casts(): array
    {
        return [
            'recipient' => 'encrypted',
            'attempts' => 'integer',
            'last_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
