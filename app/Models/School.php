<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    protected $fillable = [
        'name', 'slug', 'city', 'logo', 'plan', 'status',
        'seats', 'subscription_ends_at', 'branding', 'settings',
    ];

    protected $casts = [
        'branding' => 'array',
        'settings' => 'array',
        'subscription_ends_at' => 'date',
    ];

    public function users(): HasMany { return $this->hasMany(User::class); }
    public function classrooms(): HasMany { return $this->hasMany(Classroom::class); }
    public function students(): HasMany
    {
        return $this->hasMany(User::class)->whereHas('roles', fn ($q) => $q->where('name', 'student'));
    }
}
