<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['first_name', 'last_name', 'phone', 'phone_search_hash', 'email', 'email_search_hash', 'admin_notes', 'last_seen_at'])]
#[Hidden(['first_name', 'last_name', 'phone', 'phone_search_hash', 'email', 'email_search_hash', 'admin_notes'])]
class Customer extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'first_name' => 'encrypted',
            'last_name' => 'encrypted',
            'phone' => 'encrypted',
            'email' => 'encrypted',
            'admin_notes' => 'encrypted',
            'last_seen_at' => 'datetime',
        ];
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
