<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'classroom_id', 'teacher_id', 'title', 'type',
        'skill_ids', 'question_count', 'due_at', 'config', 'is_published',
    ];

    protected $casts = [
        'skill_ids'    => 'array',
        'config'       => 'array',
        'due_at'       => 'datetime',
        'is_published' => 'boolean',
    ];

    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }
    public function submissions(): HasMany { return $this->hasMany(AssignmentSubmission::class); }
}
