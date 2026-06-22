<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityResult extends Model
{
    protected $fillable = [
        'student_id', 'skill_id', 'classroom_id', 'theme_id',
        'score', 'max_score', 'accuracy', 'time_spent',
    ];

    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }
    public function skill(): BelongsTo { return $this->belongsTo(Skill::class); }
}
