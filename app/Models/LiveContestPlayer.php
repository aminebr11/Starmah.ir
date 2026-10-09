<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveContestPlayer extends Model
{
    protected $fillable = ['live_contest_id', 'student_id', 'score', 'correct', 'streak', 'last_seen_at'];

    protected $casts = ['last_seen_at' => 'datetime'];

    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }
}
