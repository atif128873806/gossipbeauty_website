<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'title', 'public_bio', 'photo_path', 'is_active', 'is_bookable', 'accepts_new_clients', 'position'])]
class Staff extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_bookable' => 'boolean',
            'accepts_new_clients' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'staff_service')
            ->withPivot(['duration_override_minutes', 'price_override_minor', 'accepts_new_clients']);
    }

    public function workingHours(): HasMany
    {
        return $this->hasMany(WorkingHour::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
