<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topic extends Model
{
    protected $fillable = ['subject_id', 'name', 'slug', 'sort'];

    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }
    public function skills(): HasMany { return $this->hasMany(Skill::class)->orderBy('sort'); }
}
