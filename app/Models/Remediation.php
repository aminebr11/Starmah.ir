<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * «یادآوریِ جبرانی» — برای یک سؤالِ اشتباه‌زده‌ی یک دانش‌آموز.
 * فقط در حسابِ همان دانش‌آموز ساخته و دیده می‌شود (معلم در گزارش رصدش می‌کند).
 */
class Remediation extends Model
{
    protected $fillable = [
        'school_id', 'student_id', 'teacher_id', 'source', 'source_id', 'source_ref', 'source_title',
        'q_key', 'bank_id', 'objective_id', 'objective_label', 'question', 'lost_xp', 'cap_xp', 'recovered_xp',
        'step', 'steps', 'tries', 'due_on', 'status', 'ai_tried', 'last_played_at', 'last_credit',
    ];

    protected $casts = [
        'question' => 'array', 'due_on' => 'date', 'last_played_at' => 'datetime', 'ai_tried' => 'boolean',
        'lost_xp' => 'integer', 'cap_xp' => 'integer', 'recovered_xp' => 'integer', 'step' => 'integer', 'steps' => 'integer',
    ];

    public const SOURCE_LABELS = ['exam' => '🧪 آزمون', 'game' => '🎮 بازی', 'mission' => '🎯 مأموریت'];

    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }
    public function objective(): BelongsTo { return $this->belongsTo(LearningObjective::class, 'objective_id'); }
}
