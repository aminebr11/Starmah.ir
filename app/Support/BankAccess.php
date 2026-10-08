<?php

namespace App\Support;

use App\Models\BankShare;
use App\Models\Classroom;
use App\Models\CurriculumBook;
use App\Models\SmartQuestionBank;
use App\Models\User;
use App\Support\Curriculum;
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

    /**
     * ثبتِ یک سؤال در بانک با دسته‌بندیِ کامل — هنگامِ ذخیره‌ی آزمون، بازی،
     * کاربرگ یا مأموریت. شناسه‌ی ردیفِ بانک را برمی‌گرداند تا سؤالِ آزمون/بازی
     * به همان ردیف پیوند بخورد.
     *
     *  - سؤالی که از خودِ بانک آمده (bank_id) دوباره ساخته نمی‌شود؛ فقط «تعدادِ
     *    استفاده» بالا می‌رود.
     *  - تکراری با اثرانگشتِ متن تشخیص داده می‌شود (نه برابریِ دقیقِ رشته)، در
     *    کلِ بانکِ همان مدرسه و همان پایه/درس؛ اگر ردیفِ قبلی مالِ همین معلم است
     *    و دسته‌بندیِ ناقص دارد، کامل می‌شود.
     */
    public static function autosave(User $teacher, array $q, array $meta = []): ?int
    {
        $prompt = trim((string) ($q['prompt'] ?? ''));
        $type = $q['type'] ?? 'mc';
        $type = $type === 'short' ? 'blank' : $type;
        if ($prompt === '' || ! in_array($type, ['mc', 'tf', 'desc', 'blank'], true)) {
            return null;
        }
        // سؤالِ «حالتِ نمونه» (بدونِ کلیدِ هوش مصنوعی) جای‌نگهدار است، نه سؤالِ واقعی — واردِ بانک نمی‌شود
        if (($q['source'] ?? null) === 'sample') {
            return null;
        }

        $choices = self::cleanChoices($q['choices'] ?? []);
        $answer = $q['answer'] ?? null;
        if ($type === 'blank' && ($answer === null || $answer === '')) {
            // پاسخِ کوتاهِ بازی در گزینه‌ی اول نگه داشته می‌شود
            $answer = $choices[0]['value'] ?? null;
            $choices = [];
        }
        if (in_array($type, ['mc', 'tf'], true) && ! collect($choices)->contains('correct', true)) {
            return null; // بدونِ پاسخِ درست به دردِ بانک نمی‌خورد
        }

        if (! empty($q['bank_id'])) {
            $src = self::visibleQuery($teacher)->whereKey($q['bank_id'])->first();
            if ($src && Curriculum::fingerprint($src->prompt) === Curriculum::fingerprint($prompt)) {
                if ($meta['count_use'] ?? true) {
                    $src->increment('used_count');
                }
                return $src->id;
            }
        }

        $grade = $meta['grade'] ?? null;
        $subject = $meta['subject'] ?? null;
        $fp = Curriculum::fingerprint($prompt);
        unset($meta['count_use']);
        $cat = array_filter([
            'level' => $meta['level'] ?? Curriculum::levelOf($grade),
            'grade' => $grade, 'subject' => $subject,
            'book' => $meta['book'] ?? $subject,
            'chapter_id' => $meta['chapter_id'] ?? null,
            'chapter' => $meta['chapter'] ?? null,
            'lesson_no' => $meta['lesson_no'] ?? null,
            'topic' => ($q['topic'] ?? null) ?: ($meta['topic'] ?? null),
            'goal' => ($q['goal'] ?? null) ?: ($meta['goal'] ?? null),
        ], fn ($v) => $v !== null && $v !== '');

        $dup = self::visibleQuery($teacher)
            ->where(fn ($w) => $w->where('fingerprint', $fp)->orWhere('prompt', $prompt))
            ->when($grade, fn ($w) => $w->where(fn ($x) => $x->whereNull('grade')->orWhere('grade', $grade)))
            ->first();
        if ($dup) {
            if ($dup->teacher_id === $teacher->id) {
                // فقط جاهای خالیِ دسته‌بندی را پر کن؛ چیزی را بازنویسی نکن
                $fill = [];
                foreach ($cat as $k => $v) {
                    if (empty($dup->{$k})) {
                        $fill[$k] = $v;
                    }
                }
                foreach (['bloom', 'hint', 'explanation'] as $k) {
                    if (empty($dup->{$k}) && ! empty($q[$k])) {
                        $fill[$k] = $q[$k];
                    }
                }
                $fill['fingerprint'] = $fp;
                $dup->update($fill);
            }
            if ($meta['count_use'] ?? true) {
                $dup->increment('used_count');
            }
            return $dup->id;
        }

        $row = SmartQuestionBank::create($cat + [
            'school_id' => $teacher->school_id, 'teacher_id' => $teacher->id,
            'scope' => 'school', 'type' => $type, 'prompt' => $prompt,
            'choices' => $choices, 'answer' => $answer,
            'explanation' => $q['explanation'] ?? null,
            'hint' => isset($q['hint']) ? mb_substr((string) $q['hint'], 0, 300) : (isset($q['hint1']) ? mb_substr((string) $q['hint1'], 0, 300) : null),
            'difficulty' => in_array($q['difficulty'] ?? null, ['easy', 'medium', 'hard'], true) ? $q['difficulty'] : 'medium',
            'bloom' => $q['bloom'] ?? null,
            'source' => $q['source'] ?? ($meta['source'] ?? 'manual'),
            'fingerprint' => $fp,
            'used_count' => 1,
        ]);
        return $row->id;
    }

    /** گزینه‌ها به شکلِ یکسان: [{value, correct}] */
    public static function cleanChoices($choices): array
    {
        return collect((array) $choices)
            ->map(fn ($c) => is_array($c) ? ['value' => trim((string) ($c['value'] ?? $c['text'] ?? '')), 'correct' => (bool) ($c['correct'] ?? false)] : null)
            ->filter(fn ($c) => $c && $c['value'] !== '')->values()->all();
    }

    /**
     * جست‌وجوی بانک برای «بازخوانی» در آزمون‌ساز و بازی‌ساز.
     * فیلترها: grade, subject, chapter_id, chapter, uncategorized, lesson_no, topic, types[], difficulty, source, search, exclude[]
     */
    public static function search(User $user, array $f): Builder
    {
        $types = array_values(array_intersect((array) ($f['types'] ?? []), ['mc', 'tf', 'desc', 'blank']));
        return self::visibleQuery($user)
            ->when($types, fn ($x) => $x->whereIn('type', $types))
            // «بدونِ پایه» و «عمومی» برچسب‌های درختِ دسته‌بندی برای سؤال‌های قدیمیِ بی‌برچسب‌اند
            ->when($f['grade'] ?? null, fn ($x, $g) => $g === 'بدونِ پایه' ? $x->whereNull('grade') : $x->where('grade', $g))
            ->when($f['subject'] ?? null, fn ($x, $s) => $s === 'عمومی'
                ? $x->whereNull('subject')->whereNull('book')
                : $x->where(fn ($w) => $w->where('subject', $s)->orWhere('book', $s)))
            ->when($f['uncategorized'] ?? false, fn ($x) => $x->whereNull('chapter_id')->where(fn ($w) => $w->whereNull('chapter')->orWhere('chapter', '')))
            ->when($f['chapter_id'] ?? null, fn ($x, $c) => $x->where('chapter_id', $c))
            ->when(! ($f['chapter_id'] ?? null) && ($f['chapter'] ?? null), fn ($x) => $x->where('chapter', $f['chapter']))
            ->when($f['lesson_no'] ?? null, fn ($x, $l) => $x->where('lesson_no', $l))
            ->when($f['topic'] ?? null, fn ($x, $t) => $x->where('topic', 'like', '%' . $t . '%'))
            ->when($f['difficulty'] ?? null, fn ($x, $d) => $x->where('difficulty', $d))
            ->when($f['source'] ?? null, fn ($x, $s) => $x->where('source', $s))
            ->when($f['search'] ?? null, fn ($x, $q) => $x->where('prompt', 'like', '%' . $q . '%'))
            ->when($f['exclude'] ?? null, fn ($x, $ids) => $x->whereNotIn('id', (array) $ids));
    }

    /**
     * درختِ دسته‌بندیِ بانکِ قابل‌دسترس: پایه → درس → فصل، با تعداد.
     * سؤال‌های قدیمی که فصل ندارند زیرِ «بدونِ فصل» می‌آیند.
     */
    public static function facetTree(User $user, array $types = []): array
    {
        $rows = self::visibleQuery($user)
            ->when($types, fn ($x) => $x->whereIn('type', $types))
            ->selectRaw('grade, COALESCE(subject, book) as subj, chapter_id, chapter, COUNT(*) as n')
            ->groupBy('grade', 'subj', 'chapter_id', 'chapter')->get();

        $labels = \App\Models\CurriculumChapter::whereIn('id', $rows->pluck('chapter_id')->filter()->unique())
            ->get()->mapWithKeys(fn ($c) => [$c->id => [$c->number, $c->label()]]);

        $tree = [];
        foreach ($rows as $r) {
            $g = $r->grade ?: 'بدونِ پایه';
            $s = $r->subj ?: 'عمومی';
            if ($r->chapter_id && isset($labels[$r->chapter_id])) {
                [$num, $label] = $labels[$r->chapter_id];
                $key = 'id:' . $r->chapter_id;
            } else {
                $num = 999;
                $label = $r->chapter ?: 'بدونِ فصل';
                $key = 'txt:' . ($r->chapter ?: '');
            }
            $tree[$g][$s][$key] = [
                'id' => $r->chapter_id && isset($labels[$r->chapter_id]) ? $r->chapter_id : null,
                'chapter' => $r->chapter_id ? null : ($r->chapter ?: null),
                'label' => $label, 'number' => $num,
                'count' => ($tree[$g][$s][$key]['count'] ?? 0) + (int) $r->n,
            ];
        }

        $order = array_flip(Levels::allGrades());
        $out = [];
        foreach ($tree as $g => $subjects) {
            $subs = [];
            foreach ($subjects as $s => $chapters) {
                $ch = array_values($chapters);
                usort($ch, fn ($a, $b) => [$a['number'], $a['label']] <=> [$b['number'], $b['label']]);
                $subs[] = ['subject' => $s, 'count' => array_sum(array_column($ch, 'count')), 'chapters' => $ch];
            }
            usort($subs, fn ($a, $b) => strcmp($a['subject'], $b['subject']));
            $out[] = ['grade' => $g, 'count' => array_sum(array_column($subs, 'count')), 'subjects' => $subs];
        }
        usort($out, fn ($a, $b) => ($order[$a['grade']] ?? 99) <=> ($order[$b['grade']] ?? 99));
        return $out;
    }

    /** یک ردیفِ بانک با همه‌ی جزئیات — تا هنگامِ افزودن به آزمون/بازی چیزی گم نشود. */
    public static function row(SmartQuestionBank $b, ?User $viewer = null): array
    {
        $answer = $b->answer;
        if (is_array($answer)) {
            $answer = count($answer) === 1 ? (string) reset($answer) : implode('، ', $answer);
        }
        return [
            'id' => $b->id, 'type' => $b->type, 'prompt' => $b->prompt,
            'choices' => self::cleanChoices($b->choices ?? []), 'answer' => $answer,
            'explanation' => $b->explanation, 'hint' => $b->hint,
            'difficulty' => $b->difficulty, 'bloom' => $b->bloom,
            'level' => $b->level, 'grade' => $b->grade, 'subject' => $b->subject ?: $b->book,
            'chapter_id' => $b->chapter_id, 'chapter' => $b->chapter, 'lesson_no' => $b->lesson_no,
            'topic' => $b->topic, 'source' => $b->source, 'used' => (int) $b->used_count,
            'author' => $b->relationLoaded('teacher') ? $b->teacher?->name : null,
            'mine' => $viewer ? $b->teacher_id === $viewer->id : false,
        ];
    }
}
