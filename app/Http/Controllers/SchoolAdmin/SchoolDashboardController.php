<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** پیشخان و صفحات مدیر مدرسه (فقط مدرسه‌ی خودش). */
class SchoolDashboardController extends Controller
{
    public function overview(Request $request): Response
    {
        $schoolId = $request->user()->school_id;
        $school = $request->user()->school;

        return Inertia::render('SchoolAdmin/Overview', [
            'school' => $school?->only('name', 'city', 'plan', 'status', 'seats'),
            'stats' => [
                'teachers' => User::role(Roles::TEACHER)->where('school_id', $schoolId)->count(),
                'students' => User::role(Roles::STUDENT)->where('school_id', $schoolId)->count(),
                'classes'  => Classroom::where('school_id', $schoolId)->count(),
            ],
            'classes' => Classroom::where('school_id', $schoolId)->withCount('students')->with('teacher:id,name')->get()
                ->map(fn ($c) => ['name' => $c->name, 'teacher' => $c->teacher?->name, 'students' => $c->students_count, 'code' => $c->join_code]),
        ]);
    }

    public function students(Request $request): Response
    {
        $schoolId = $request->user()->school_id;

        $students = User::role(Roles::STUDENT)->where('school_id', $schoolId)->get()
            ->map(function ($s) {
                $class = $s->classrooms()->with('teacher:id,name')->first();
                return [
                    'id' => $s->id, 'name' => $s->name, 'phone' => $s->phone,
                    'class' => $class?->name, 'teacher' => $class?->teacher?->name,
                    'xp' => $s->totalXp(),
                ];
            })->sortByDesc('xp')->values();

        return Inertia::render('SchoolAdmin/Students', ['students' => $students]);
    }

    public function announcements(): Response
    {
        return Inertia::render('SchoolAdmin/Announcements');
    }

    public function reports(Request $request): Response
    {
        $schoolId = $request->user()->school_id;
        return Inertia::render('SchoolAdmin/Reports', [
            'classes' => Classroom::where('school_id', $schoolId)->withCount('students')->get(['id', 'name']),
        ]);
    }
}
