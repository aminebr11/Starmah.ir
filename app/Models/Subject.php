<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'name', 'slug', 'grade', 'icon', 'sort', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function topics(): HasMany { return $this->hasMany(Topic::class)->orderBy('sort'); }
}
