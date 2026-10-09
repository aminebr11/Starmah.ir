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
    ];

    protected $casts = [
        'sentences' => 'array', 'due_at' => 'datetime', 'published_at' => 'datetime', 'is_published' => 'boolean', 'penalty' => 'float',
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

    /** نمره‌ی پیشنهادی از تعدادِ غلط (توصیفی و عددیِ ۲۰). */
    public function suggest(int $mistakes): array
    {
        $grade = $mistakes <= 1 ? 'خیلی خوب' : ($mistakes <= 3 ? 'خوب' : ($mistakes <= 6 ? 'قابل قبول' : 'نیاز به تلاش'));

        return ['grade' => $grade, 'score' => max(0, round(20 - $mistakes * (float) ($this->penalty ?: 0.5), 2))];
    }
}
