<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ClassActivity;
use App\Models\Classroom;
use App\Services\GamificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** فعالیت‌های کلاسی + امتیازدهی (بازی/آزمون/تکلیف/پادکست...). */
class ActivityController extends Controller
{
    public function index(Request $request): Response
    {
        $teacher = $request->user();
        $classroom = Classroom::where('teacher_id', $teacher->id)->first();

        $students = $classroom
            ? $classroom->students()->with('theme')->get()
                ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'group' => $s->theme?->name, 'emoji' => $s->theme?->emoji])
                ->values()
            : collect();

        // تیم‌ها (گروه‌ها) برای امتیازدهی گروهی
        $groups = $students->groupBy('group')->map(fn ($g, $name) => [
            'name' => $name, 'emoji' => $g->first()['emoji'], 'ids' => $g->pluck('id')->values(),
        ])->values();

        $activities = ClassActivity::where('classroom_id', $classroom?->id)
            ->withCount('awards')->latest()->limit(50)->get()
            ->map(fn ($a) => [
                'id' => $a->id, 'type' => $a->type, 'type_label' => ClassActivity::typeLabel($a->type),
                'title' => $a->title, 'points' => $a->points, 'status' => $a->status,
                'awarded' => $a->awards_count, 'scheduled' => $a->scheduledJalali(),
            ]);

        return Inertia::render('Teacher/Activities', [
            'classroom'  => $classroom?->only('id', 'name'),
            'students'   => $students,
            'groups'     => $groups,
            'activities' => $activities,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $teacher = $request->user();
        $classroom = Classroom::where('teacher_id', $teacher->id)->firstOrFail();

        $data = $request->validate([
            'type'         => ['required', 'in:game,exam,homework,podcast,online_exam,custom'],
            'title'        => ['required', 'string', 'max:120'],
            'description'  => ['nullable', 'string', 'max:500'],
            'points'       => ['required', 'integer', 'min:1', 'max:1000'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        ClassActivity::create([
            'school_id'    => $teacher->school_id,
            'classroom_id' => $classroom->id,
            'teacher_id'   => $teacher->id,
            ...$data,
        ]);

        return back()->with('flash', 'فعالیت ثبت شد ✅');
    }

    /** اعطای امتیاز به دانش‌آموزها (لیست id) یا یک تیم یا کل کلاس. */
    public function award(Request $request, ClassActivity $classActivity, GamificationService $game): RedirectResponse
    {
        abort_unless($classActivity->teacher_id === $request->user()->id, 403);

        $data = $request->validate([
            'student_ids'   => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $count = 0;
        foreach (\App\Models\User::whereIn('id', $data['student_ids'])->get() as $student) {
            if ($game->awardActivity($classActivity, $student, $request->user())) {
                $count++;
            }
        }

        return back()->with('flash', "به {$count} دانش‌آموز امتیاز داده شد ✅ (+{$classActivity->points})");
    }
}
