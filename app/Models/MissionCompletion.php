<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** انجامِ یک مأموریت توسط دانش‌آموز در یک روز (یک‌بار در روز). */
class MissionCompletion extends Model
{
    protected $fillable = ['mission_id', 'student_id', 'play_date', 'score', 'total', 'xp_awarded'];

    protected $casts = ['play_date' => 'date'];

    public function mission(): BelongsTo { return $this->belongsTo(Mission::class); }
    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }
}
