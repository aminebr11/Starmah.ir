<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Grade;
use App\Models\GradeColumn;
use App\Services\GamificationService;
use App\Services\MasteryService;
use App\Support\Jalali;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * دفتر کلاسی فعالیت‌محور (بازسازی طرح قدیم):
 * هر «فعالیت» = درس + موضوع + نوع نمره (عددی/توصیفی/تکلیف) + عنوان + تاریخ،
 * و برای هر دانش‌آموز نمره/ارزیابی + بازخورد ثبت می‌شود. امتیاز در موتور واحد XP می‌نشیند.
 */
class GradebookController extends Controller
{
    public function index(Request $request, MasteryService $mastery): Response
    {
        $teacher = $request->user();
        $classroom = Classroom::where('teacher_id', $teacher->id)->first();

        $students = $classroom
            ? $classroom->students()->get(['users.id', 'name'])->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values()
            : collect();

        $columns = GradeColumn::where('classroom_id', $classroom?->id)
            ->with('grades')->orderByDesc('graded_at')->orderByDesc('id')->get();

        // اثرِ هر فعالیت بر تسلطِ درسیِ دانش‌آموزان (یک‌بار جمع‌آوریِ شواهد برای کلِ کلاس)
        $impact = [];
        try {
            $impact = $mastery->gradeImpact(
                $students->pluck('id')->all(),
                $columns->map(fn ($c) => $c->only('id', 'lesson', 'title', 'topic', 'chapter_id'))->all()
            );
        } catch (\Throwable $e) {
            report($e); // نبودِ تحلیلِ تسلط نباید دفترِ نمره را از کار بیندازد
        }

        $activities = $columns
            ->map(fn ($c) => [
                'id' => $c->id, 'title' => $c->title,
                'score_type' => $c->score_type ?: $c->type,
                'lesson' => $c->lesson, 'topic' => $c->topic,
                'chapter_id' => $c->chapter_id, 'chapter' => $c->chapter_id ? \App\Support\Objectives::chapterName((int) $c->chapter_id) : null,
                'max' => (float) $c->max,
                'date' => $c->graded_at?->toDateString(),
                'jdate' => $c->graded_at ? Jalali::format($c->graded_at) : null,
                'grades' => $c->grades->mapWithKeys(fn ($g) => [$g->student_id => [
                    'score' => $g->score, 'text' => $g->text, 'feedback' => $g->feedback,
                ]]),
                'mastery' => $impact[$c->id] ?? null,
            ]);

        return Inertia::render('Teacher/Gradebook', [
            'classroom' => $classroom?->only('name', 'grade'),
            'subjects'  => $classroom ? $classroom->subjectNames() : [],
            'students'  => $students,
            'activities'=> $activities,
            'descriptiveOptions' => array_keys(GamificationService::GRADE_XP['descriptive']),
            'homeworkOptions'    => array_keys(GamificationService::GRADE_XP['homework']),
            'highlight' => $request->session()->get('gradebook_highlight'),
        ]);
    }

    /** ساخت فعالیت جدید همراه با نمرات و بازخوردها. */
    public function storeActivity(Request $request, GamificationService $game): RedirectResponse
    {
        $teacher = $request->user();
        $classroom = Classroom::where('teacher_id', $teacher->id)->firstOrFail();

        $data = $request->validate([
            'title'                => ['required', 'string', 'max:100'],
            'score_type'           => ['required', 'in:numeric,descriptive,homework'],
            'lesson'               => ['nullable', 'string', 'max:80'],
            'topic'                => ['nullable', 'string', 'max:120'],
            'chapter_id'           => ['nullable', 'integer'],
            'max'                  => ['nullable', 'numeric', 'min:1', 'max:100'],
            'date'                 => ['nullable', 'date'],
            'grades'               => ['array'],
            'grades.*.student_id'  => ['required', 'integer'],
            'grades.*.score'       => ['nullable', 'numeric'],
            'grades.*.text'        => ['nullable', 'string', 'max:40'],
            'grades.*.feedback'    => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data, $classroom, $teacher, $game) {
            $col = GradeColumn::create([
                'school_id'    => $teacher->school_id,
                'classroom_id' => $classroom->id,
                'teacher_id'   => $teacher->id,
                'title'        => $data['title'],
                'type'         => $data['score_type'] === 'numeric' ? 'numeric' : 'descriptive',
                'score_type'   => $data['score_type'],
                'lesson'       => $data['lesson'] ?? null,
                'topic'        => $data['topic'] ?? null,
                'chapter_id'   => self::validChapter($data['chapter_id'] ?? null),
                'max'          => $data['max'] ?? 20,
                'graded_at'    => $data['date'] ?? now(),
            ]);
            $this->persistGrades($col, $data['grades'] ?? [], $game, $teacher);
            $this->colId = $col->id;
        });
        MasteryService::forgetMany(collect($data['grades'] ?? [])->pluck('student_id')->all());
        rescue(fn () => \App\Services\LearningService::fromGradeColumn(GradeColumn::find($this->colId)), null, true);

        return back()->with('flash', 'فعالیت و نمرات ثبت شد ✅ اثرِ آن در تسلطِ هر دانش‌آموز در «سوابق نمرات» دیده می‌شود.')
            ->with('gradebook_highlight', $this->colId);
    }

    /** فقط فصلِ موجود پذیرفته می‌شود. */
    private static function validChapter($id): ?int
    {
        $id = (int) $id;

        return $id && \App\Support\QuestionChapter::labels([$id]) ? $id : null;
    }

    /** ذخیره/ویرایش نمرات یک فعالیتِ موجود. */
    public function saveGrades(Request $request, GradeColumn $gradeColumn, GamificationService $game): RedirectResponse
    {
        abort_unless($gradeColumn->teacher_id === $request->user()->id, 403);
        $data = $request->validate([
            'grades'              => ['required', 'array'],
            'grades.*.student_id' => ['required', 'integer'],
            'grades.*.score'      => ['nullable', 'numeric'],
            'grades.*.text'       => ['nullable', 'string', 'max:40'],
            'grades.*.feedback'   => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(fn () => $this->persistGrades($gradeColumn, $data['grades'], $game, $request->user()));
        MasteryService::forgetMany(collect($data['grades'])->pluck('student_id')->all());
        rescue(fn () => \App\Services\LearningService::fromGradeColumn($gradeColumn->fresh()), null, true);

        return back()->with('flash', 'نمرات ذخیره شد ✅')->with('gradebook_highlight', $gradeColumn->id);
    }

    /**
     * ویرایشِ کاملِ یک فعالیت: مشخصات (عنوان، درس، موضوع، نوعِ نمره، بارم، تاریخ)
     * به‌همراهِ نمرات. اگر نمره‌ی دانش‌آموزی در ویرایش پاک شود، نمره و امتیازش
     * هم حذف می‌شود؛ با عوضِ نوعِ نمره، ارزیابی‌های ناسازگارِ قبلی کنار می‌روند.
     */
    public function updateActivity(Request $request, GradeColumn $gradeColumn, GamificationService $game): RedirectResponse
    {
        $teacher = $request->user();
        abort_unless($gradeColumn->teacher_id === $teacher->id, 403);

        $data = $request->validate([
            'title'                => ['required', 'string', 'max:100'],
            'score_type'           => ['required', 'in:numeric,descriptive,homework'],
            'lesson'               => ['nullable', 'string', 'max:80'],
            'topic'                => ['nullable', 'string', 'max:120'],
            'chapter_id'           => ['nullable', 'integer'],
            'max'                  => ['nullable', 'numeric', 'min:1', 'max:100'],
            'date'                 => ['nullable', 'date'],
            'grades'               => ['array'],
            'grades.*.student_id'  => ['required', 'integer'],
            'grades.*.score'       => ['nullable', 'numeric'],
            'grades.*.text'        => ['nullable', 'string', 'max:40'],
            'grades.*.feedback'    => ['nullable', 'string', 'max:255'],
        ], [
            'title.required' => 'عنوانِ فعالیت را بنویسید.',
        ]);

        $grades = $data['grades'] ?? [];
        $type = $data['score_type'];
        $allowed = $type === 'numeric' ? null : array_keys(GamificationService::GRADE_XP[$type]);
        foreach ($grades as &$g) {
            // مقدارِ ناسازگار با نوعِ تازه دور ریخته می‌شود
            if ($type === 'numeric') {
                $g['text'] = null;
            } else {
                $g['score'] = null;
                if (! in_array($g['text'] ?? null, $allowed, true)) $g['text'] = null;
            }
        }
        unset($g);

        DB::transaction(function () use ($gradeColumn, $data, $grades, $type, $allowed, $game, $teacher) {
            $gradeColumn->fill([
                'title'      => $data['title'],
                'type'       => $type === 'numeric' ? 'numeric' : 'descriptive',
                'score_type' => $type,
                'lesson'     => $data['lesson'] ?? null,
                'topic'      => $data['topic'] ?? null,
                'chapter_id' => self::validChapter($data['chapter_id'] ?? null),
                'max'        => $type === 'numeric' ? ($data['max'] ?? $gradeColumn->max ?? 20) : ($gradeColumn->max ?: 20),
                'graded_at'  => $data['date'] ?? $gradeColumn->graded_at ?? now(),
            ])->save();

            // نمره‌هایی که در ویرایش خالی شدند حذف می‌شوند
            $empty = collect($grades)->filter(fn ($g) => ($g['score'] ?? null) === null && empty($g['text']) && empty($g['feedback']))
                ->pluck('student_id')->all();
            if ($empty) {
                foreach (Grade::where('grade_column_id', $gradeColumn->id)->whereIn('student_id', $empty)->get() as $old) {
                    \App\Models\XpEntry::where('source_type', Grade::class)->where('source_id', $old->id)->delete();
                    $old->delete();
                }
            }

            $this->persistGrades($gradeColumn, $grades, $game, $teacher);

            // با عوض‌شدنِ نوع، نمره‌های ناسازگارِ باقی‌مانده (که در فرم نبودند) کنار می‌روند
            foreach ($gradeColumn->grades()->get() as $gr) {
                $ok = $type === 'numeric' ? $gr->score !== null : in_array($gr->text, $allowed, true);
                if (! $ok && empty($gr->feedback)) {
                    \App\Models\XpEntry::where('source_type', Grade::class)->where('source_id', $gr->id)->delete();
                    $gr->delete();
                } elseif (! $ok) {
                    $gr->forceFill(['score' => null, 'text' => null])->save();
                }
            }

            // تغییرِ بارم/درس/عنوان روی امتیازِ نمره‌های بی‌تغییر هم اثر دارد
            foreach ($gradeColumn->grades()->get() as $gr) {
                $game->awardForGrade($gr, $gradeColumn, $teacher);
            }
        });

        MasteryService::forgetMany($gradeColumn->grades()->pluck('student_id')->merge(collect($grades)->pluck('student_id'))->all());
        rescue(fn () => \App\Services\LearningService::fromGradeColumn($gradeColumn->fresh()), null, true);

        return back()->with('flash', 'فعالیت ویرایش شد ✅ تسلطِ دانش‌آموزان با مشخصاتِ تازه دوباره حساب شد.')
            ->with('gradebook_highlight', $gradeColumn->id);
    }

    /** شناسه‌ی فعالیتِ تازه‌ساخته (برای برجسته‌کردن در سوابق). */
    private ?int $colId = null;

    private function persistGrades(GradeColumn $col, array $grades, GamificationService $game, $teacher): void
    {
        foreach ($grades as $g) {
            $hasValue = ($g['score'] ?? null) !== null || ! empty($g['text']) || ! empty($g['feedback']);
            if (! $hasValue) {
                continue;
            }
            $grade = Grade::updateOrCreate(
                ['grade_column_id' => $col->id, 'student_id' => $g['student_id']],
                ['score' => $g['score'] ?? null, 'text' => $g['text'] ?? null, 'feedback' => $g['feedback'] ?? null]
            );
            $changed = $grade->wasRecentlyCreated || $grade->wasChanged('score') || $grade->wasChanged('text');
            $game->awardForGrade($grade, $col, $teacher);
            if ($changed) {
                $this->notifyGrade($col, $grade, $teacher);
            }
        }
    }

    /** اعلانِ نمره در کارتابلِ دانش‌آموز. */
    private function notifyGrade(GradeColumn $col, Grade $grade, $teacher): void
    {
        $type = $col->score_type ?: $col->type;
        $value = $type === 'numeric'
            ? ($grade->score !== null ? "{$grade->score} از {$col->max}" : '—')
            : ($grade->text ?: '—');
        $lesson = $col->lesson ? $col->lesson . ' — ' : '';
        $body = "نمره‌ی جدید: {$lesson}{$col->title}\nارزیابی: {$value}";
        if ($grade->feedback) {
            $body .= "\nبازخورد معلم: {$grade->feedback}";
        }

        $payload = [
            'school_id' => $col->school_id,
            'sender_id' => $teacher->id,
            'title'     => '📔 نمره‌ی کلاسی — ' . ($col->lesson ?: $col->title),
            'audience'  => 'personal',
            'body'      => $body,
        ];
        // کلیک روی اعلان باید مستقیم تبِ «نمرات کلاسی» کارنامه را باز کند،
        // نه صفحه‌ی کلیِ اطلاعیه‌ها.
        if (\App\Support\DbSchema::hasColumn('announcements', 'link')) {
            $payload['link'] = '/report?tab=grades';
        }

        $ann = \App\Models\Announcement::create($payload);
        $ann->recipients()->sync([$grade->student_id]);

        // پیامک — فقط اگر مدرسه این رویداد را روشن کرده باشد
        if ($student = \App\Models\User::find($grade->student_id)) {
            \App\Support\SmsGateway::event('grade', $student,
                "{$student->name} عزیز، نمره‌ی تازه ثبت شد: {$lesson}{$col->title} — {$value}", $teacher);
        }
    }

    public function destroyColumn(Request $request, GradeColumn $gradeColumn): RedirectResponse
    {
        abort_unless($gradeColumn->teacher_id === $request->user()->id, 403);
        // برگشت XP نمرات این فعالیت
        foreach ($gradeColumn->grades as $g) {
            \App\Models\XpEntry::where('source_type', Grade::class)->where('source_id', $g->id)->delete();
        }
        $ids = $gradeColumn->grades->pluck('student_id')->all();
        $gradeColumn->delete();
        MasteryService::forgetMany($ids);
        return back()->with('flash', 'فعالیت حذف شد 🗑️');
    }
}
