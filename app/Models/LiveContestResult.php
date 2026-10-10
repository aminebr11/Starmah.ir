<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** سابقه‌ی ماندگارِ یک دانش‌آموز در یک دورِ «مسابقه‌ی زنده». */
class LiveContestResult extends Model
{
    protected $fillable = ['live_contest_id', 'round', 'student_id', 'rank', 'players', 'score', 'correct', 'answered', 'total',
        'accuracy', 'best_streak', 'avg_ms', 'xp', 'finished_at'];

    protected $casts = ['finished_at' => 'datetime'];

    public function contest(): BelongsTo { return $this->belongsTo(LiveContest::class, 'live_contest_id'); }
    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }

    public static function ready(): bool
    {
        return \App\Support\DbSchema::hasTable('live_contest_results') && \App\Support\DbSchema::hasColumn('live_contests', 'round');
    }
}
