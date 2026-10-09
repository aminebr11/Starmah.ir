<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** پاسخِ دانش‌آموز به «املا/روخوانی»: عکسِ برگه‌ی املا یا صدای روخوانی + تصحیحِ معلم. */
class AudioSubmission extends Model
{
    protected $fillable = [
        'audio_task_id', 'student_id', 'file_path', 'file_kind', 'note', 'plays', 'submitted_at', 'submit_xp',
        'mistakes', 'grade', 'score', 'feedback', 'marked_path', 'graded_at', 'graded_by',
    ];

    protected $casts = [
        'submitted_at' => 'datetime', 'graded_at' => 'datetime', 'submit_xp' => 'boolean', 'score' => 'float', 'mistakes' => 'integer',
    ];

    public function task(): BelongsTo { return $this->belongsTo(AudioTask::class, 'audio_task_id'); }
    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }

    /** داده‌ی نمایش (همان قالبِ نمایشگرِ کاربرگ + فیلدهای املا). فایل از مسیرِ خودِ سایت. */
    public function viewData(): array
    {
        $v = $this->updated_at?->timestamp ?? 0;

        return [
            'id' => $this->id,
            'url' => $this->file_path ? route('audio.file', [$this->id, 'file']) . '?v=' . $v : null,
            'marked_url' => $this->marked_path ? route('audio.file', [$this->id, 'marked']) . '?v=' . $v : null,
            'pdf' => $this->file_kind === 'pdf',
            'audio' => $this->file_kind === 'audio',
            'note' => $this->note,
            'date' => $this->submitted_at ? \App\Support\Jalali::format($this->submitted_at, true) : null,
            'plays' => (int) $this->plays,
            'mistakes' => $this->mistakes,
            'grade' => $this->grade,
            'score' => $this->score,
            'feedback' => $this->feedback,
            'graded' => (bool) $this->graded_at,
            'xp' => 0,
        ];
    }
}
