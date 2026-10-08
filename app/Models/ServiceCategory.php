<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'summary', 'position', 'is_active'])]
class ServiceCategory extends Model
{
    public function services(): HasMany
    {
        return $this->hasMany(Service::class)->orderBy('position');
    }
}
