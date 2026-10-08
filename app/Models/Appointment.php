<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'public_reference', 'customer_id', 'staff_id', 'starts_at_utc', 'ends_at_utc',
    'busy_starts_at_utc', 'busy_ends_at_utc', 'timezone', 'status', 'origin',
    'idempotency_key', 'external_reference', 'price_mode', 'price_minor', 'duration_minutes', 'policy_snapshot',
    'customer_note', 'internal_note', 'created_by', 'confirmed_at', 'cancelled_at',
    'cancellation_reason',
])]
class Appointment extends Model
{
    public const CAPACITY_HOLDING_STATUSES = ['pending', 'confirmed'];

    protected function casts(): array
    {
        return [
            'starts_at_utc' => 'immutable_datetime',
            'ends_at_utc' => 'immutable_datetime',
            'busy_starts_at_utc' => 'immutable_datetime',
            'busy_ends_at_utc' => 'immutable_datetime',
            'policy_snapshot' => 'array',
            'duration_minutes' => 'integer',
            'price_minor' => 'integer',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'customer_note' => 'encrypted',
            'internal_note' => 'encrypted',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AppointmentItem::class)->orderBy('position');
    }

    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class, 'appointment_resources')->withPivot('units_reserved');
    }

    public function events(): HasMany
    {
        return $this->hasMany(BookingEvent::class);
    }

    public function notificationDeliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }
}
