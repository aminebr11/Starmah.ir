<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentSubmission extends Model
{
    protected $fillable = [
        'assignment_id', 'student_id', 'score', 'max_score',
        'accuracy', 'answers', 'status', 'submitted_at',
    ];

    protected $casts = ['answers' => 'array', 'submitted_at' => 'datetime'];

    public function assignment(): BelongsTo { return $this->belongsTo(Assignment::class); }
    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }
}
