<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** مأموریتِ روزانه‌ی تعریف‌شده توسط معلم (سؤال‌ها از بانکِ سؤالِ معلم). */
class Mission extends Model
{
    use BelongsToSchool;

    /** انواعِ مأموریت — هرکدام به یک بخشِ موجودِ سامانه وصل است. */
    public const TYPES = ['quiz', 'podcast', 'video', 'material', 'worksheet', 'game', 'exam'];

    /** انواعی که به یک «فعالیت» وصل‌اند و جایزه پس از انجامِ واقعی داده می‌شود. */
    public const ACTIVITY_TYPES = ['podcast', 'video', 'material', 'worksheet', 'game', 'exam'];

    protected $fillable = [
        'school_id', 'teacher_id', 'classroom_id', 'theme_id', 'title', 'description', 'type', 'resource_id',
        'subject', 'lesson_no', 'difficulty', 'question_ids', 'question_count', 'pass_percent',
        'xp_reward', 'badge_name', 'badge_icon', 'is_active',
        // دوره‌ی اجرا: یک‌بار در یک تاریخ، یا تکرارِ روزانه در یک بازه
        'repeat_mode', 'starts_on', 'ends_on',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'question_ids' => 'array',
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    /** حالت‌های تکرار. */
    public const REPEATS = ['daily', 'once'];

    /**
     * آیا این مأموریت در تاریخِ داده‌شده اجرا می‌شود؟
     *
     * خالی‌بودنِ تاریخ‌ها یعنی «بدونِ محدودیت» — پس مأموریت‌های قدیمی
     * که تاریخ ندارند دقیقاً مثلِ قبل هر روز فعال‌اند.
     */
    public function runsOn($date = null): bool
    {
        $d = $date ? \Illuminate\Support\Carbon::parse($date)->startOfDay() : now()->startOfDay();

        if ($this->repeat_mode === 'once') {
            return $this->starts_on !== null && $this->starts_on->isSameDay($d);
        }
        if ($this->starts_on && $d->lt($this->starts_on->startOfDay())) {
            return false;
        }
        if ($this->ends_on && $d->gt($this->ends_on->startOfDay())) {
            return false;
        }

        return true;
    }

    /** برچسبِ خوانا برای فهرستِ معلم. */
    public function scheduleLabel(): string
    {
        $fa = fn ($d) => \App\Support\Jalali::format($d);
        if ($this->repeat_mode === 'once') {
            return $this->starts_on ? 'فقط ' . $fa($this->starts_on) : 'یک‌بار (بدونِ تاریخ)';
        }
        if ($this->starts_on && $this->ends_on) {
            return 'روزانه ' . $fa($this->starts_on) . ' تا ' . $fa($this->ends_on);
        }
        if ($this->starts_on) {
            return 'روزانه از ' . $fa($this->starts_on);
        }
        if ($this->ends_on) {
            return 'روزانه تا ' . $fa($this->ends_on);
        }

        return 'روزانه — بدونِ پایان';
    }

    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }
    public function completions(): HasMany { return $this->hasMany(MissionCompletion::class); }
}
