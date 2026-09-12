<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * محتوای کلاس: جزوه/فایل، پادکست، گالری، تکلیف.
 * چندمستأجری (BelongsToSchool) — هر مدرسه فقط محتوای خودش را می‌بیند.
 */
class ClassContent extends Model
{
    use BelongsToSchool;

    /** پادکست حالا هم صوتی است هم تصویری؛ video نوعِ جداگانه‌ی ویدیوی درسی است. */
    public const TYPES = ['material', 'podcast', 'video', 'gallery', 'homework'];

    protected $fillable = [
        'school_id', 'teacher_id', 'classroom_id', 'type',
        'title', 'description', 'file_path', 'external_url', 'due_at',
        'duration_seconds', 'xp_reward',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'duration_seconds' => 'integer',
        'xp_reward' => 'integer',
    ];

    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }
    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
}
