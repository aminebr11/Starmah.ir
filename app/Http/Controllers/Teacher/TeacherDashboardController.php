<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\DisciplineRecord;
use App\Support\Jalali;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeacherDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $teacher = $request->user();
        app(\App\Services\BirthdayService::class)->runForSchool($teacher->school_id);

        $classrooms = Classroom::withCount('students')
            ->where('teacher_id', $teacher->id)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'grade' => $c->grade,
                'join_code' => $c->join_code, 'students' => $c->students_count,
            ]);

        $announcements = Announcement::forUser($teacher)
            ->with('sender:id,name')->latest()->limit(5)->get()
            ->map(fn ($a) => [
                'id' => $a->id, 'title' => $a->title, 'body' => $a->body,
                'personal' => $a->audience === 'personal',
                'sender' => $a->sender?->name, 'date' => Jalali::format($a->created_at),
            ]);

        return Inertia::render('Teacher/Dashboard', [
            'classrooms' => $classrooms,
            'announcements' => $announcements,
            'totals' => [
                'classrooms'  => $classrooms->count(),
                'students'    => $classrooms->sum('students'),
                'assignments' => Assignment::where('teacher_id', $teacher->id)->count(),
                'stars'       => DisciplineRecord::where('recorded_by', $teacher->id)->where('type', 'star')->count(),
            ],
        ]);
    }

    /** دفتر نمره: دانش‌آموزان × میانگین تسلط/امتیاز. */
    public function gradebook(Request $request): Response
    {
        $teacher = $request->user();
        $classroom = Classroom::where('teacher_id', $teacher->id)->first();

        $rows = [];
        if ($classroom) {
            $rows = $classroom->students()->get()->map(fn ($s) => [
                'id' => $s->id, 'name' => $s->name,
                'xp' => $s->totalXp(),
                'mastery' => (int) round($s->skillMastery()->avg('mastery') ?? 0),
                'stars' => DisciplineRecord::where('student_id', $s->id)->where('type', 'star')->sum('points'),
            ])->sortByDesc('xp')->values();
        }

        return Inertia::render('Teacher/Gradebook', [
            'classroom' => $classroom?->only('name'),
            'rows' => $rows,
        ]);
    }

    /** انضباط: لیست رکوردها + دانش‌آموزان برای ثبت سریع. */
    public function discipline(Request $request): Response
    {
        $teacher = $request->user();
        $classroom = Classroom::where('teacher_id', $teacher->id)->first();

        $students = $classroom ? $classroom->students()->get(['users.id', 'name'])
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]) : collect();

        $records = DisciplineRecord::where('recorded_by', $teacher->id)
            ->with('student:id,name')->latest()->limit(40)->get()
            ->map(fn ($r) => [
                'student' => $r->student?->name, 'type' => $r->type,
                'points' => $r->points, 'note' => $r->note,
                'date' => Jalali::format($r->created_at),
            ]);

        return Inertia::render('Teacher/Discipline', [
            'students' => $students->values(),
            'records'  => $records,
        ]);
    }

    /** گزارش کلی کلاس (تحلیل عملکرد). */
    public function reports(Request $request, \App\Services\AnalyticsService $analytics): Response
    {
        $classroom = Classroom::where('teacher_id', $request->user()->id)->first();
        return Inertia::render('Teacher/Reports', [
            'classroom' => $classroom?->only('name'),
            'report'    => $classroom ? $analytics->classroomReport($classroom) : null,
        ]);
    }

    public function show(Request $request, Classroom $classroom): Response
    {
        abort_unless($classroom->teacher_id === $request->user()->id, 403);

        $students = $classroom->students()->with('theme:id,name,emoji')->get()->map(fn ($s) => [
            'id'   => $s->id,
            'name' => $s->name,
            'phone' => $s->phone,
            'national_id' => $s->national_id,
            'theme_id' => $s->theme_id,
            'team' => $s->theme ? "{$s->theme->emoji} {$s->theme->name}" : null,
            'xp'   => $s->totalXp(),
            'avg'  => (int) round($s->skillMastery()->avg('mastery') ?? 0),
        ])->sortByDesc('xp')->values();

        $themes = \App\Models\Theme::where('is_active', true)->where('key', '!=', 'brand')
            ->orderBy('sort')->get(['id', 'name', 'emoji'])
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'emoji' => $t->emoji]);

        return Inertia::render('Teacher/Classroom', [
            'classroom' => ['id' => $classroom->id, 'name' => $classroom->name, 'join_code' => $classroom->join_code],
            'students'  => $students,
            'themes'    => $themes,
        ]);
    }

    /** تغییر تیم/گروهِ یک دانش‌آموزِ کلاس خودم (فقط معلم؛ دانش‌آموز نمی‌تواند). */
    public function setTeam(Request $request, \App\Models\User $user): \Illuminate\Http\RedirectResponse
    {
        $teacher = $request->user();
        $inMyClass = Classroom::where('teacher_id', $teacher->id)
            ->whereHas('students', fn ($q) => $q->where('users.id', $user->id))->exists();
        abort_unless($inMyClass, 403);

        $data = $request->validate(['theme_id' => ['required', 'exists:themes,id']]);
        $theme = \App\Models\Theme::where('is_active', true)->findOrFail($data['theme_id']);
        $user->update(['theme_id' => $theme->id]);

        \App\Models\AuditLog::record($teacher, 'تغییر تیم دانش‌آموز', "تیمِ «{$user->name}» به «{$theme->emoji} {$theme->name}» تغییر کرد");

        return back()->with('flash', "تیمِ «{$user->name}» به «{$theme->name}» تغییر کرد ✅");
    }

    /** میان‌بر: کلاسِ خودِ معلم (برای منوی «دانش‌آموزان من»). */
    public function myClass(Request $request): Response
    {
        $classroom = Classroom::where('teacher_id', $request->user()->id)->first();
        if (! $classroom) {
            return Inertia::render('Teacher/Classroom', ['classroom' => null, 'students' => []]);
        }
        return $this->show($request, $classroom);
    }
}
