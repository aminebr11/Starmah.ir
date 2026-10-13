<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * یک جلسه‌ی «کلاسِ آنلاین».
 *
 * وضعیت: scheduled (برنامه‌ریزی‌شده) → live (در حالِ برگزاری) → ended.
 * «version» با هر تغییرِ وضعیتِ کلاس (حضور، دست، گفت‌وگو، نظرسنجی، تخته…)
 * یک واحد بالا می‌رود تا گوشیِ بچه‌ها فقط وقتی چیزی عوض شده، داده‌ی کامل بگیرند.
 */
class OnlineRoom extends Model
{
    /** اگر معلم این‌قدر دیده نشود، کلاس خودکار بسته می‌شود. */
    public const HOST_GONE_MIN = 30;

    protected $fillable = ['school_id', 'teacher_id', 'classroom_id', 'title', 'status', 'starts_at', 'duration_min', 'started_at',
        'ended_at', 'host_seen_at', 'media', 'settings', 'version', 'recording_path', 'recording_size', 'recording_shared', 'xp_done'];

    protected $casts = ['starts_at' => 'datetime', 'started_at' => 'datetime', 'ended_at' => 'datetime', 'host_seen_at' => 'datetime',
        'settings' => 'array', 'recording_shared' => 'boolean', 'xp_done' => 'boolean'];

    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }
    public function participants(): HasMany { return $this->hasMany(OnlineParticipant::class, 'room_id'); }
    public function messages(): HasMany { return $this->hasMany(OnlineMessage::class, 'room_id'); }
    public function polls(): HasMany { return $this->hasMany(OnlinePoll::class, 'room_id'); }

    public function live(): bool { return $this->status === 'live'; }
    public function ended(): bool { return $this->status === 'ended'; }

    /** یک تنظیم از settings با پیش‌فرض. */
    public function opt(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /** تغییرِ چند تنظیم و اعلامِ تغییر. */
    public function setOpts(array $values): void
    {
        $this->settings = array_merge($this->settings ?? [], $values);
        $this->save();
        $this->bump();
    }

    /** «چیزی عوض شد» — همه‌ی شرکت‌کننده‌ها در همگام‌سازیِ بعدی داده‌ی تازه می‌گیرند. */
    public function bump(): void
    {
        DB::table('online_rooms')->where('id', $this->id)->increment('version');
        $this->version = (int) DB::table('online_rooms')->where('id', $this->id)->value('version');
    }

    /** شناسه‌ی دانش‌آموزانی که حقِ ورود دارند (کلاسِ انتخاب‌شده، یا همه‌ی کلاس‌های معلم). */
    public function audienceIds(): array
    {
        $rooms = $this->classroom_id ? [$this->classroom_id] : Classroom::where('teacher_id', $this->teacher_id)->pluck('id')->all();

        return DB::table('classroom_student')->whereIn('classroom_id', $rooms)->distinct()->pluck('student_id')->map(fn ($v) => (int) $v)->all();
    }

    /** کلاس‌هایی که یک دانش‌آموز می‌تواند ببیند. */
    public static function forStudent(User $student)
    {
        $rooms = DB::table('classroom_student')->where('student_id', $student->id)->pluck('classroom_id');
        $teachers = Classroom::whereIn('id', $rooms)->pluck('teacher_id')->filter();

        return static::where(fn ($q) => $q->whereIn('classroom_id', $rooms)
            ->orWhere(fn ($q) => $q->whereNull('classroom_id')->whereIn('teacher_id', $teachers)));
    }

    /** اگر معلم مدتی دیده نشده یا زمانِ کلاس خیلی گذشته، کلاس بسته شود. */
    public function closeIfAbandoned(): void
    {
        if (! $this->live()) {
            return;
        }
        $seen = $this->host_seen_at ?? $this->started_at;
        if ($seen && $seen->lt(now()->subMinutes(self::HOST_GONE_MIN))) {
            app(\App\Services\OnlineClassService::class)->end($this, null);
        }
    }

    public static function ready(): bool
    {
        return \App\Support\DbSchema::hasTable('online_rooms') && \App\Support\DbSchema::hasTable('online_poll_answers');
    }
}
