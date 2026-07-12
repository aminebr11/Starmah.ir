<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduGameAttempt extends Model
{
    protected $fillable = [
        'edu_game_id', 'student_id', 'score', 'max_score', 'progress',
        'status', 'hints_used', 'duration_sec', 'completed_at',
    ];
    protected $casts = ['progress' => 'array', 'completed_at' => 'datetime'];

    public function game(): BelongsTo { return $this->belongsTo(EduGame::class, 'edu_game_id'); }
    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }
}
