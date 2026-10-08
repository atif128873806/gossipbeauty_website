<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'service_category_id', 'slug', 'name', 'summary', 'inclusions', 'duration_minutes',
    'buffer_before_minutes', 'buffer_after_minutes', 'price_mode', 'price_minor',
    'price_note', 'is_featured', 'is_active', 'is_online_bookable', 'archived_at', 'position',
])]
class Service extends Model
{
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'buffer_before_minutes' => 'integer',
            'buffer_after_minutes' => 'integer',
            'price_minor' => 'integer',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'is_online_bookable' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function addOns(): BelongsToMany
    {
        return $this->belongsToMany(AddOn::class, 'service_add_on')->withPivot(['is_required', 'position'])->orderByPivot('position');
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'staff_service')
            ->withPivot(['duration_override_minutes', 'price_override_minor', 'accepts_new_clients']);
    }

    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class, 'service_resource')->withPivot('units_required');
    }
}
