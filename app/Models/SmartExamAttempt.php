<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class SmartExamAttempt extends Model
{
    protected $fillable = [
        'smart_exam_id', 'student_id', 'attempt_no', 'token', 'token_expires_at', 'started_at',
        'finished_at', 'duration_sec', 'score', 'auto_score', 'desc_score', 'max_score',
        'progress', 'status', 'ip', 'rewarded',
    ];
    protected $casts = ['progress' => 'array', 'rewarded' => 'boolean', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'token_expires_at' => 'datetime'];
    public function exam(): BelongsTo { return $this->belongsTo(SmartExam::class, 'smart_exam_id'); }
    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }
    public function answers(): HasMany { return $this->hasMany(SmartExamAnswer::class, 'attempt_id'); }
}
