<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class DisciplineTopic extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'name', 'kind', 'points', 'created_by', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
}
