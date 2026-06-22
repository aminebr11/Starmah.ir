<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Badge extends Model
{
    protected $fillable = ['key', 'name', 'emoji', 'description', 'criteria'];
    protected $casts = ['criteria' => 'array'];
}
