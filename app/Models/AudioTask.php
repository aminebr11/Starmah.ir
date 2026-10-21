<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * «املا و روخوانیِ صوتی».
 * - dictation: معلم صدا می‌گذارد (صدای خودش، فایل، یا متن → صدای فارسی جمله‌به‌جمله)؛ دانش‌آموز گوش می‌دهد،
 *   روی کاغذ می‌نویسد، عکس می‌فرستد؛ معلم روی برگه تصحیح می‌کند و نمره می‌دهد.
 * - reading: متنِ روخوانی + خوانشِ الگو؛ دانش‌آموز صدای خودش را ضبط می‌کند و معلم نمره می‌دهد.
 * نمره در «دفترِ نمره» (ستونِ همین تکلیف) و سوابقِ دانش‌آموز ثبت می‌شود.
 */
class AudioTask extends Model
{
    protected $fillable = [
        'school_id', 'teacher_id', 'classroom_id', 'kind', 'title', 'subject', 'grade', 'text', 'sentences', 'source',
        'audio_path', 'pace', 'score_type', 'penalty', 'grade_column_id', 'due_at', 'is_published', 'published_at',
        'publish_at', 'audio_hash',
    ];

    protected $casts = [
        'sentences' => 'array', 'due_at' => 'datetime', 'published_at' => 'datetime', 'publish_at' => 'datetime', 'is_published' => 'boolean', 'penalty' => 'float',
    ];

    public const KINDS = ['dictation' => '📝 املا', 'reading' => '🎙️ روخوانی'];

    /** ارزشیابیِ توصیفیِ دبستان (همان جدولِ امتیازِ دفترِ نمره). */
    public const GRADES = ['خیلی خوب', 'خوب', 'قابل قبول', 'نیاز به تلاش'];

    /** پایه‌هایی که درسِ «املا» دارند (پیش‌فرض: هر شش پایه‌ی دبستان؛ قابلِ تغییر با تنظیمِ dictation_grades). */
    public const DICTATION_GRADES = ['اول', 'دوم', 'سوم', 'چهارم', 'پنجم', 'ششم'];

    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }
    public function submissions(): HasMany { return $this->hasMany(AudioSubmission::class); }

    public static function ready(): bool
    {
        return \App\Support\DbSchema::hasTable('audio_tasks') && \App\Support\DbSchema::hasTable('audio_submissions');
    }

    /** آیا پایه‌ی این کلاس درسِ املا دارد؟ */
    public static function dictationAllowed(?string $grade): bool
    {
        $grade = trim((string) $grade);
        if ($grade === '') {
            return false;
        }
        $list = array_filter(array_map('trim', preg_split('/[,،\n]+/u', (string) Setting::get('dictation_grades', '')) ?: []));
        $list = $list ?: self::DICTATION_GRADES;
        foreach ($list as $g) {
            if ($g !== '' && mb_strpos($grade, $g) !== false) {
                return true;
            }
        }
        // اگر برای این پایه درسی به نامِ «املا» در برنامه‌ی درسی ثبت شده باشد
        return (bool) rescue(fn () => CurriculumChapter::withoutGlobalScopes()->where('grade', $grade)
            ->where('subject', 'like', '%املا%')->exists(), false, false);
    }

    /** متن → جمله‌ها (برای پخشِ جمله‌به‌جمله). جمله‌ی خیلی بلند در «،» شکسته می‌شود. */
    public static function splitSentences(string $text): array
    {
        $text = trim(preg_replace('/[ \t]+/u', ' ', $text) ?? $text);
        $parts = preg_split('/(?<=[.!؟?!\n])\s*|\n+/u', $text) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            if (mb_strlen($p) > 90 && mb_strpos($p, '،') !== false) {
                foreach (preg_split('/(?<=،)\s*/u', $p) as $q) {
                    if (trim($q) !== '') {
                        $out[] = trim($q);
                    }
                }
            } else {
                $out[] = $p;
            }
        }

        return array_slice($out, 0, 40);
    }

    /**
     * متن → جمله‌ها فقط سرِ نقطه/علامت (برای صدای پیوسته؛ ویرگول شکسته نمی‌شود و خودِ صدا مکثِ کوتاهش را دارد).
     * فاصله‌ی پیش از نقطه («آن‌ها .») برداشته می‌شود و تکه‌های یک‌دو کلمه‌ای به جمله‌ی کنارشان می‌چسبند،
     * تا «آن‌ها .» یک جمله‌ی جدا (و یک صدای بی‌معنا) نشود.
     */
    public static function speechSentences(string $text): array
    {
        $text = trim(preg_replace('/[ \t]+/u', ' ', $text) ?? $text);
        $text = preg_replace('/\s+([.!؟?…،؛:])/u', '$1', $text) ?? $text;
        $parts = array_values(array_filter(array_map('trim', preg_split('/(?<=[.!؟?…])\s+|\n+/u', $text) ?: []),
            fn ($p) => $p !== '' && preg_match('/[\p{L}\p{N}]/u', $p)));
        $words = fn ($p) => count(preg_split('/\s+/u', $p) ?: []);
        $out = [];
        foreach ($parts as $p) {
            if ($out && $words($p) <= 2 && $words(end($out)) < 25) {
                $out[count($out) - 1] .= ' ' . $p;
            } else {
                $out[] = $p;
            }
        }
        if (count($out) > 1 && $words($out[0]) <= 2) {
            $out[1] = $out[0] . ' ' . $out[1];
            array_shift($out);
        }

        return array_slice($out, 0, 60);
    }

    /** سبکِ خواندنِ سیستم برای این تکلیف. */
    public function speechStyle(): string
    {
        return $this->kind === 'dictation' ? 'dictation' : 'reading';
    }

    /** اثرانگشتِ متن و مکث‌ها — اگر عوض شد، صدای سیستم باید دوباره ساخته شود. */
    public function wantHash(): string
    {
        return sha1($this->kind . '|' . ($this->pace ?: 'normal') . '|' . implode("\n", array_column($this->sentences ?? [], 'text')));
    }

    /** صدای سیستم (متن ← صدا) هنوز ساخته نشده یا با متنِ فعلی نمی‌خواند؟ */
    public function needsBuild(): bool
    {
        // بدونِ سرویسِ صدا چیزی برای ساختن نیست؛ بچه‌ها با صدای فارسیِ گوشیِ خودشان می‌شنوند (مثلِ قبل)
        if ($this->source !== 'tts' || ! $this->sentences || ! \App\Services\SpeechService::available()) {
            return false;
        }
        if (! $this->audio_path) {
            return true;
        }
        // تکلیف‌های قدیمی (پیش از این ستون) که صدایشان ساخته شده، دوباره ساخته نمی‌شوند
        return \App\Support\DbSchema::hasColumn('audio_tasks', 'audio_hash') && $this->audio_hash !== null && $this->audio_hash !== $this->wantHash();
    }

    /** صدای قابلِ پخش دارد؟ (برای انتشار) */
    public function audioReady(): bool
    {
        return $this->source === 'tts' ? ! $this->needsBuild() : (bool) $this->audio_path;
    }

    /** انتشار برای کلاس (با اعلان، فقط بارِ اول). */
    public function publishNow(): void
    {
        $first = ! $this->published_at;
        $this->update(['is_published' => true, 'published_at' => $this->published_at ?? now()]
            + (\App\Support\DbSchema::hasColumn('audio_tasks', 'publish_at') ? ['publish_at' => null] : []));
        if (! $first) {
            return;
        }
        rescue(function () {
            $ids = Classroom::find($this->classroom_id)?->students()->pluck('users.id')->all() ?? [];
            if (! $ids) {
                return;
            }
            $ann = Announcement::create([
                'school_id' => $this->school_id, 'sender_id' => $this->teacher_id, 'audience' => 'personal',
                'title' => ($this->kind === 'dictation' ? '📝 املای صوتیِ تازه — ' : '🎙️ روخوانیِ تازه — ') . $this->title,
                'body' => $this->kind === 'dictation'
                    ? 'یک برگه و مداد بردار، گوش بده و بنویس؛ بعد عکسش را برای معلم بفرست.'
                    : 'متن را با صدای بلند بخوان و صدایت را برای معلم بفرست.',
                'link' => route('listen.show', $this->id, false),
            ]);
            $ann->recipients()->sync($ids);
        }, null, true);
    }

    /** انتشارِ زمان‌بندی‌شده: سرِ ساعت (بدونِ cron؛ با هر بارگذاریِ صفحه بررسی می‌شود). */
    public static function releaseDue(): void
    {
        if (! self::ready() || ! \App\Support\DbSchema::hasColumn('audio_tasks', 'publish_at')) {
            return;
        }
        rescue(function () {
            self::where('is_published', false)->whereNotNull('publish_at')->where('publish_at', '<=', now())->limit(20)->get()
                ->each(function (self $t) {
                    // صدایش آماده نیست → منتشر نمی‌شود (معلم در صفحه‌ی تکلیف هشدار می‌بیند)
                    if ($t->audioReady()) {
                        $t->publishNow();
                    }
                });
        }, null, false);
    }

    /** نمره‌ی پیشنهادی از تعدادِ غلط (توصیفی و عددیِ ۲۰). */
    public function suggest(int $mistakes): array
    {
        $grade = $mistakes <= 1 ? 'خیلی خوب' : ($mistakes <= 3 ? 'خوب' : ($mistakes <= 6 ? 'قابل قبول' : 'نیاز به تلاش'));

        return ['grade' => $grade, 'score' => max(0, round(20 - $mistakes * (float) ($this->penalty ?: 0.5), 2))];
    }
}
