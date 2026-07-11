<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Grade;
use App\Models\GradeColumn;
use App\Services\GamificationService;
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
    public function index(Request $request): Response
    {
        $teacher = $request->user();
        $classroom = Classroom::where('teacher_id', $teacher->id)->first();

        $students = $classroom
            ? $classroom->students()->get(['users.id', 'name'])->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values()
            : collect();

        $activities = GradeColumn::where('classroom_id', $classroom?->id)
            ->with('grades')->orderByDesc('graded_at')->orderByDesc('id')->get()
            ->map(fn ($c) => [
                'id' => $c->id, 'title' => $c->title,
                'score_type' => $c->score_type ?: $c->type,
                'lesson' => $c->lesson, 'topic' => $c->topic,
                'max' => (float) $c->max,
                'date' => $c->graded_at?->toDateString(),
                'jdate' => $c->graded_at ? Jalali::format($c->graded_at) : null,
                'grades' => $c->grades->mapWithKeys(fn ($g) => [$g->student_id => [
                    'score' => $g->score, 'text' => $g->text, 'feedback' => $g->feedback,
                ]]),
            ]);

        return Inertia::render('Teacher/Gradebook', [
            'classroom' => $classroom?->only('name'),
            'subjects'  => $classroom ? $classroom->subjectNames() : [],
            'students'  => $students,
            'activities'=> $activities,
            'descriptiveOptions' => array_keys(GamificationService::GRADE_XP['descriptive']),
            'homeworkOptions'    => array_keys(GamificationService::GRADE_XP['homework']),
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
                'max'          => $data['max'] ?? 20,
                'graded_at'    => $data['date'] ?? now(),
            ]);
            $this->persistGrades($col, $data['grades'] ?? [], $game, $teacher);
        });

        return back()->with('flash', 'فعالیت و نمرات ثبت شد ✅');
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

        return back()->with('flash', 'نمرات ذخیره شد ✅');
    }

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

        $ann = \App\Models\Announcement::create([
            'school_id' => $col->school_id,
            'sender_id' => $teacher->id,
            'title'     => '📔 نمره‌ی کلاسی — ' . ($col->lesson ?: $col->title),
            'audience'  => 'personal',
            'body'      => $body,
        ]);
        $ann->recipients()->sync([$grade->student_id]);
    }

    public function destroyColumn(Request $request, GradeColumn $gradeColumn): RedirectResponse
    {
        abort_unless($gradeColumn->teacher_id === $request->user()->id, 403);
        // برگشت XP نمرات این فعالیت
        foreach ($gradeColumn->grades as $g) {
            \App\Models\XpEntry::where('source_type', Grade::class)->where('source_id', $g->id)->delete();
        }
        $gradeColumn->delete();
        return back()->with('flash', 'فعالیت حذف شد 🗑️');
    }
}
