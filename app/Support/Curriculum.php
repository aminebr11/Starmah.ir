<?php

namespace App\Support;

use App\Models\Classroom;
use App\Models\CurriculumBook;
use App\Models\CurriculumChapter;
use App\Models\User;

/**
 * منبعِ واحدِ «زمینه‌ی درسی» — مقطع، پایه، درس، فصل، مبحث.
 *
 * هم فرم‌های ساخت (آزمون و بازی)، هم هوش مصنوعی و هم بانک سؤال از همین‌جا
 * می‌خوانند تا یک سؤال همه‌جا زیرِ یک برچسبِ یکسان ذخیره و پیدا شود.
 */
class Curriculum
{
    public static function levelOf(?string $grade): ?string
    {
        if (! $grade) {
            return null;
        }
        foreach (Levels::MAP as $level => $grades) {
            if (in_array($grade, $grades, true)) {
                return $level;
            }
        }
        return null;
    }

    /** بازه‌ی سنیِ تقریبیِ هر پایه — برای تنظیمِ زبان و دشواری در پرامپت. */
    public static function ageOf(?string $grade): ?string
    {
        $i = array_search($grade, Levels::allGrades(), true);
        return $i === false ? null : Jalali::fa((string) ($i + 6)) . ' تا ' . Jalali::fa((string) ($i + 7)) . ' سال';
    }

    /** فصل‌های یک درس در یک پایه که این مدرسه می‌بیند (سراسری + مدرسه). */
    public static function chapters(?string $grade, ?string $subject, ?int $schoolId): array
    {
        if (! $grade || ! $subject) {
            return [];
        }
        return CurriculumChapter::visibleTo($schoolId)
            ->where('grade', $grade)->where('subject', $subject)
            ->orderBy('number')->orderBy('id')->get()
            ->map(fn ($c) => self::chapterRow($c))->values()->all();
    }

    public static function chapterRow(CurriculumChapter $c): array
    {
        return [
            'id' => $c->id, 'number' => $c->number, 'title' => $c->title, 'label' => $c->label(),
            'lessons' => array_values(array_filter((array) ($c->lessons ?? []))),
            'custom' => $c->school_id !== null,
        ];
    }

    /** کلاس‌های معلم با پایه، مقطع و درس‌های هر پایه — ورودیِ منوی آبشاری. */
    public static function teacherClasses(User $teacher): array
    {
        return Classroom::where('teacher_id', $teacher->id)->with('school:id,level')->get()
            ->map(function ($c) {
                $level = $c->school?->level ?: self::levelOf($c->grade);
                $subjects = ($c->grade && $level)
                    ? CurriculumBook::where('level', $level)->where('grade', $c->grade)->where('is_active', true)
                        ->orderBy('sort')->get(['name', 'icon'])->map(fn ($b) => ['name' => $b->name, 'icon' => $b->icon])->values()->all()
                    : [];
                return ['id' => $c->id, 'name' => $c->name, 'grade' => $c->grade, 'level' => $level, 'subjects' => $subjects];
            })->values()->all();
    }

    /**
     * زمینه‌ی کاملِ یک درخواست را از ورودیِ فرم می‌سازد و اعتبارسنجی می‌کند:
     * پایه/مقطع از روی کلاس، عنوان و درس‌های فصل از روی شناسه‌ی فصل.
     */
    public static function resolve(array $in, User $user): array
    {
        $grade = $in['grade'] ?? null;
        $level = $in['level'] ?? null;
        if (! empty($in['classroom_id'])) {
            $c = Classroom::where('id', $in['classroom_id'])->where('school_id', $user->school_id)->first();
            if ($c) {
                $grade = $c->grade ?: $grade;
                $level = $c->school?->level ?: $level;
            }
        }
        $level = $level ?: self::levelOf($grade);

        $chapter = null;
        if (! empty($in['chapter_id'])) {
            $chapter = CurriculumChapter::visibleTo($user->school_id)->find($in['chapter_id']);
        }

        return [
            'level' => $level,
            'grade' => $grade,
            'subject' => trim((string) ($in['subject'] ?? '')) ?: ($chapter?->subject),
            'book' => trim((string) ($in['book'] ?? '')) ?: null,
            'chapter_id' => $chapter?->id,
            'chapter' => $chapter ? $chapter->label() : (trim((string) ($in['chapter'] ?? '')) ?: null),
            'chapter_no' => $chapter?->number,
            'lessons' => $chapter ? array_values(array_filter((array) $chapter->lessons)) : [],
            'topic' => trim((string) ($in['topic'] ?? '')) ?: null,
            'goal' => trim((string) ($in['goal'] ?? '')) ?: null,
        ];
    }

    /**
     * اثرانگشتِ یک متن برای تشخیصِ سؤالِ تکراری: حروفِ عربی/فارسی یکسان،
     * اعراب و نیم‌فاصله و علائم حذف، ارقام یکسان.
     */
    public static function fingerprint(?string $text): string
    {
        $s = (string) $text;
        $s = strtr($s, ['ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', 'ة' => 'ه', 'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', "\u{200C}" => ' ']);
        $s = strtr($s, array_combine(mb_str_split('۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩'), mb_str_split('01234567890123456789')));
        $s = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $s);      // اعراب
        $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($s));
        return sha1(trim(preg_replace('/\s+/u', ' ', $s)));
    }
}
