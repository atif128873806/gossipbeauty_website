<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'id', 'timezone', 'booking_horizon_days', 'minimum_notice_minutes',
    'slot_increment_minutes', 'online_booking_enabled', 'instant_confirmation',
    'allow_online_cancellation', 'allow_online_rescheduling', 'policy_text',
])]
class BookingConfiguration extends Model
{
    protected $table = 'booking_configuration';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'booking_horizon_days' => 'integer',
            'minimum_notice_minutes' => 'integer',
            'slot_increment_minutes' => 'integer',
            'online_booking_enabled' => 'boolean',
            'instant_confirmation' => 'boolean',
            'allow_online_cancellation' => 'boolean',
            'allow_online_rescheduling' => 'boolean',
            'policy_text' => 'array',
        ];
    }

    public static function current(): self
    {
        return self::query()->find(1) ?? new self([
            'id' => 1,
            'timezone' => 'America/Toronto',
            'booking_horizon_days' => 14,
            'minimum_notice_minutes' => 0,
            'slot_increment_minutes' => 30,
            'online_booking_enabled' => false,
            'instant_confirmation' => true,
        ]);
    }
}
