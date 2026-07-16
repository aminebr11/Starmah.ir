<?php

namespace App\Support;

use App\Models\BankShare;
use App\Models\Classroom;
use App\Models\CurriculumBook;
use App\Models\SmartQuestionBank;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * دسترسیِ بانک سؤالات — دسته‌بندی بر مبنای مقطع→کلاس→درس و اشتراک‌گذاریِ دقیق:
 * - معلم: سؤال‌های خودش (هر پایه) + سؤال‌های مدرسه‌ی خودش + سؤال‌های سراسری‌ای که ادمین کل
 *   برای مدرسه‌ی او (در مقطع/کلاس/درسِ مجاز) به اشتراک گذاشته — محدود به پایه‌های تدریسیِ همان معلم.
 * - مدیر مدرسه: کلِ بانکِ مدرسه‌ی خودش + بانکِ سراسریِ به‌اشتراک‌گذاشته‌شده با مدرسه‌اش.
 * - ادمین کل: کلِ بانک.
 */
class BankAccess
{
    /** درختِ برنامه‌ی درسی: مقطع → کلاس → درس‌ها (منبعِ واحد برای منوهای آبشاری). */
    public static function curriculumTree(): array
    {
        return CurriculumBook::where('is_active', true)->orderBy('sort')->get()
            ->groupBy('level')
            ->map(function ($byLevel, $level) {
                return [
                    'level'  => $level,
                    'grades' => $byLevel->groupBy('grade')->map(fn ($rows, $grade) => [
                        'grade'    => $grade,
                        'subjects' => $rows->map(fn ($b) => ['name' => $b->name, 'icon' => $b->icon])->values()->all(),
                    ])->values()->all(),
                ];
            })->values()->all();
    }

    /** نگاشتِ کلاس→مقطع (برای سؤال‌های قدیمی که ستونِ level ندارند). */
    public static function gradeLevelMap(): array
    {
        return CurriculumBook::where('is_active', true)
            ->get(['grade', 'level'])->pluck('level', 'grade')->all();
    }

    /** مقطعِ یک سؤال (اگر ثبت نشده، از روی کلاس حدس زده می‌شود). */
    public static function levelOf(?string $level, ?string $grade): ?string
    {
        if ($level) {
            return $level;
        }
        return $grade ? (self::gradeLevelMap()[$grade] ?? null) : null;
    }

    /** پایه‌هایی که این معلم تدریس می‌کند. */
    public static function teacherGrades(User $teacher): array
    {
        return Classroom::where('teacher_id', $teacher->id)->pluck('grade')->filter()->unique()->values()->all();
    }

    /** مجوزهای اشتراکِ یک مدرسه (ردیف‌های bank_shares). */
    public static function schoolShares(?int $schoolId): \Illuminate\Support\Collection
    {
        if (! $schoolId) {
            return collect();
        }
        return BankShare::where('school_id', $schoolId)->get();
    }

    /** آیا مدرسه در اشتراک‌گذاریِ سراسری مشارکت دارد؟ (سازگاری با بانک کاربرگ‌ها). */
    public static function schoolCanSeeShared(?int $schoolId): bool
    {
        return self::schoolShares($schoolId)->isNotEmpty();
    }

    /**
     * افزودنِ شرطِ «سؤال‌های سراسریِ به‌اشتراک‌گذاشته‌شده با این مدرسه» به کوئری.
     * هر ردیفِ اشتراک: (level, grade, subject) که هرکدام NULL یعنی «همه».
     */
    private static function applySharedGlobal(Builder $q, ?int $schoolId, array $limitGrades = []): void
    {
        $shares = self::schoolShares($schoolId);
        if ($shares->isEmpty()) {
            return;
        }
        $gradeLevel = self::gradeLevelMap();

        $q->orWhere(function (Builder $g) use ($shares, $limitGrades, $gradeLevel) {
            $g->where('scope', 'global');
            $g->where(function (Builder $any) use ($shares, $gradeLevel) {
                foreach ($shares as $s) {
                    $any->orWhere(function (Builder $m) use ($s, $gradeLevel) {
                        if ($s->level) {
                            // سؤال‌هایی که level‌شان مطابق است یا (level خالی و کلاس‌شان در آن مقطع است)
                            $gradesInLevel = array_keys(array_filter($gradeLevel, fn ($lv) => $lv === $s->level));
                            $m->where(function (Builder $lv) use ($s, $gradesInLevel) {
                                $lv->where('level', $s->level);
                                if ($gradesInLevel) {
                                    $lv->orWhere(fn (Builder $x) => $x->whereNull('level')->whereIn('grade', $gradesInLevel));
                                }
                            });
                        }
                        if ($s->grade) {
                            $m->where('grade', $s->grade);
                        }
                        if ($s->subject) {
                            $m->where(fn (Builder $x) => $x->where('subject', $s->subject)->orWhere('book', $s->subject));
                        }
                    });
                }
            });
            // محدود به پایه‌های تدریسیِ معلم (اگر مشخص شده)
            if ($limitGrades) {
                $g->where(fn (Builder $x) => $x->whereNull('grade')->orWhereIn('grade', $limitGrades));
            }
        });
    }

    /** کوئریِ سؤال‌های قابل‌مشاهده برای یک کاربر. */
    public static function visibleQuery(User $user): Builder
    {
        if ($user->hasRole(Roles::SUPER_ADMIN)) {
            return SmartQuestionBank::withoutGlobalScopes();
        }

        if ($user->hasRole(Roles::SCHOOL_ADMIN)) {
            return SmartQuestionBank::withoutGlobalScopes()->where(function (Builder $q) use ($user) {
                $q->where('school_id', $user->school_id);
                self::applySharedGlobal($q, $user->school_id); // بدون محدودیتِ پایه برای مدیر
            });
        }

        // معلم
        $grades = self::teacherGrades($user);

        return SmartQuestionBank::withoutGlobalScopes()->where(function (Builder $q) use ($user, $grades) {
            // سؤال‌های خودِ معلم (هر پایه)
            $q->where('teacher_id', $user->id);
            // بانکِ مدرسه‌ی خودش در پایه‌های تدریسی
            $q->orWhere(function (Builder $w) use ($user, $grades) {
                $w->where('school_id', $user->school_id)
                    ->whereIn('scope', ['school', 'shared', 'global'])
                    ->where(fn (Builder $g) => $g->whereNull('grade')->when($grades, fn ($x) => $x->orWhereIn('grade', $grades)));
            });
            // بانکِ سراسریِ به‌اشتراک‌گذاشته‌شده با مدرسه (محدود به پایه‌های تدریسی)
            self::applySharedGlobal($q, $user->school_id, $grades);
        });
    }

    /** فهرستِ درس‌ها و شماره‌درس‌های موجود در بانکِ قابل‌دسترسِ کاربر (برای منوی آبشاریِ انتخابگر). */
    public static function pickerFacets(User $user): array
    {
        $rows = self::visibleQuery($user)
            ->whereIn('type', ['mc', 'tf'])
            ->get(['subject', 'book', 'lesson_no']);
        $tree = [];
        foreach ($rows as $r) {
            $subj = $r->subject ?: ($r->book ?: 'عمومی');
            $lesson = $r->lesson_no ?: '—';
            $tree[$subj][$lesson] = true;
        }
        $out = [];
        foreach ($tree as $subj => $lessons) {
            $ls = array_keys($lessons);
            sort($ls, SORT_NATURAL);
            $out[] = ['subject' => $subj, 'lessons' => $ls];
        }
        usort($out, fn ($a, $b) => strcmp($a['subject'], $b['subject']));
        return $out;
    }

    /** کوئریِ انتخابگرِ بانک (برای آزمون/بازی) با فیلترِ درس/شماره‌درس/جست‌وجو. */
    public static function pickerQuery(User $user, ?string $subject, ?string $lessonNo, ?string $search): Builder
    {
        return self::visibleQuery($user)
            ->whereIn('type', ['mc', 'tf'])
            ->when($subject, fn ($x) => $x->where(fn ($w) => $w->where('subject', $subject)->orWhere('book', $subject)))
            ->when($lessonNo, fn ($x) => $x->where('lesson_no', $lessonNo))
            ->when($search, fn ($x) => $x->where('prompt', 'like', '%' . $search . '%'));
    }

    /** آیا این کاربر می‌تواند این سؤال را ویرایش/حذف کند؟ */
    public static function canEdit(User $user, SmartQuestionBank $q): bool
    {
        if ($user->hasRole(Roles::SUPER_ADMIN)) {
            return true;
        }
        if ($user->hasRole(Roles::SCHOOL_ADMIN)) {
            return $q->school_id === $user->school_id;
        }
        return $q->teacher_id === $user->id; // معلم فقط سؤال‌های خودش
    }

    /** ثبتِ خودکارِ یک سؤال در بانک هنگام ساختِ آزمون/بازی (بدون تکرار). */
    public static function autosave(User $teacher, array $q, array $meta = []): void
    {
        $prompt = trim($q['prompt'] ?? '');
        if ($prompt === '' || ! in_array(($q['type'] ?? 'mc'), ['mc', 'tf', 'desc', 'blank'], true)) {
            return;
        }
        $exists = SmartQuestionBank::where('teacher_id', $teacher->id)
            ->where('prompt', $prompt)->exists();
        if ($exists) {
            return;
        }
        SmartQuestionBank::create([
            'school_id' => $teacher->school_id, 'teacher_id' => $teacher->id,
            'scope' => 'school', 'type' => $q['type'] ?? 'mc', 'prompt' => $prompt,
            'choices' => $q['choices'] ?? [], 'answer' => $q['answer'] ?? null,
            'explanation' => $q['explanation'] ?? null,
            'level' => $meta['level'] ?? null, 'lesson_no' => $meta['lesson_no'] ?? null,
            'subject' => $meta['subject'] ?? null, 'grade' => $meta['grade'] ?? null,
            'book' => $meta['book'] ?? ($meta['subject'] ?? null), 'chapter' => $meta['chapter'] ?? null,
            'topic' => $q['topic'] ?? ($meta['topic'] ?? null),
            'difficulty' => $q['difficulty'] ?? 'medium',
            'source' => $q['source'] ?? ($meta['source'] ?? 'manual'),
        ]);
    }
}
