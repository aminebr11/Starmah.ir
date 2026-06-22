<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillMastery extends Model
{
    protected $table = 'student_skill_mastery';

    protected $fillable = ['student_id', 'skill_id', 'mastery', 'attempts', 'last_practiced_at'];
    protected $casts = ['last_practiced_at' => 'datetime'];

    public function skill(): BelongsTo { return $this->belongsTo(Skill::class); }
}
