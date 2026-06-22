<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class Season extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'name', 'starts_at', 'ends_at', 'is_active'];
    protected $casts = ['starts_at' => 'date', 'ends_at' => 'date', 'is_active' => 'boolean'];
}
