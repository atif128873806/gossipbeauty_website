<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['slug', 'name', 'description', 'duration_minutes', 'price_mode', 'price_minor', 'is_required', 'is_standalone', 'is_active'])]
class AddOn extends Model
{
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'price_minor' => 'integer',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_add_on')->withPivot(['is_required', 'position']);
    }
}
