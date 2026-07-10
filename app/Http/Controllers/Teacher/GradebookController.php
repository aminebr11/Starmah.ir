<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Grade;
use App\Models\GradeColumn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** دفتر کلاسی حرفه‌ای — ستون‌های نمره (عددی/توصیفی) × دانش‌آموزان + پرینت. */
class GradebookController extends Controller
{
    public function index(Request $request): Response
    {
        $teacher = $request->user();
        $classroom = Classroom::where('teacher_id', $teacher->id)->first();

        $students = $classroom
            ? $classroom->students()->get(['users.id', 'name'])->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values()
            : collect();

        $columns = GradeColumn::where('classroom_id', $classroom?->id)
            ->with('grades')->latest()->get()
            ->map(fn ($c) => [
                'id' => $c->id, 'title' => $c->title, 'type' => $c->type, 'max' => (float) $c->max,
                'grades' => $c->grades->mapWithKeys(fn ($g) => [$g->student_id => ['score' => $g->score, 'text' => $g->text]]),
            ]);

        return Inertia::render('Teacher/Gradebook', [
            'classroom' => $classroom?->only('name'),
            'subjects'  => $classroom ? $classroom->subjectNames() : [],
            'students'  => $students,
            'columns'   => $columns,
        ]);
    }

    public function storeColumn(Request $request): RedirectResponse
    {
        $teacher = $request->user();
        $classroom = Classroom::where('teacher_id', $teacher->id)->firstOrFail();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:80'],
            'type'  => ['required', 'in:numeric,descriptive'],
            'max'   => ['nullable', 'numeric', 'min:1', 'max:100'],
        ]);

        GradeColumn::create([
            'school_id'    => $teacher->school_id,
            'classroom_id' => $classroom->id,
            'teacher_id'   => $teacher->id,
            'title'        => $data['title'],
            'type'         => $data['type'],
            'max'          => $data['max'] ?? 20,
            'graded_at'    => now(),
        ]);

        return back()->with('flash', 'ستون نمره ساخته شد ✅');
    }

    /** ذخیره‌ی نمره‌ی یک یا چند دانش‌آموز در یک ستون. */
    public function saveGrades(Request $request, GradeColumn $gradeColumn): RedirectResponse
    {
        abort_unless($gradeColumn->teacher_id === $request->user()->id, 403);
        $data = $request->validate([
            'grades'             => ['required', 'array'],
            'grades.*.student_id'=> ['required', 'integer'],
            'grades.*.score'     => ['nullable', 'numeric'],
            'grades.*.text'      => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($data['grades'] as $g) {
            $hasValue = ($g['score'] ?? null) !== null || ! empty($g['text']);
            if (! $hasValue) {
                continue;
            }
            Grade::updateOrCreate(
                ['grade_column_id' => $gradeColumn->id, 'student_id' => $g['student_id']],
                ['score' => $g['score'] ?? null, 'text' => $g['text'] ?? null]
            );
        }

        return back()->with('flash', 'نمرات ذخیره شد ✅');
    }

    public function destroyColumn(Request $request, GradeColumn $gradeColumn): RedirectResponse
    {
        abort_unless($gradeColumn->teacher_id === $request->user()->id, 403);
        $gradeColumn->delete();
        return back();
    }
}
