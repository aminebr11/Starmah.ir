<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Skill extends Model
{
    protected $fillable = ['topic_id', 'name', 'slug', 'sort'];

    public function topic(): BelongsTo { return $this->belongsTo(Topic::class); }
    public function questions(): HasMany { return $this->hasMany(Question::class); }
}
