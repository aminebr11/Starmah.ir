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
        'publish_at', 'is_visible', 'notified_at',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'publish_at' => 'datetime',
        'notified_at' => 'datetime',
        'is_visible' => 'boolean',
        'duration_seconds' => 'integer',
        'xp_reward' => 'integer',
    ];

    /** آیا همین حالا برای دانش‌آموز دیدنی است؟ */
    public function isLive(): bool
    {
        return $this->is_visible !== false
            && ($this->publish_at === null || $this->publish_at->lessThanOrEqualTo(now()));
    }

    /**
     * فقط محتوایی که هم «نمایش» است و هم زمانِ انتشارش رسیده.
     * publish_at خالی = انتشارِ فوری (رفتارِ پیش‌فرض).
     */
    public function scopeLive($q)
    {
        return $q->where('is_visible', true)
            ->where(fn ($x) => $x->whereNull('publish_at')->orWhere('publish_at', '<=', now()));
    }

    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }
    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
}
