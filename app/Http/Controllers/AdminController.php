<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\School;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** نمای کلان مدرسه (school_admin / super_admin). */
class AdminController extends Controller
{
    public function overview(Request $request): Response
    {
        $user = $request->user();
        $school = $user->school;

        return Inertia::render('Admin/Overview', [
            'school' => $school ? [
                'name' => $school->name, 'plan' => $school->plan, 'status' => $school->status,
                'seats' => $school->seats, 'ends_at' => $school->subscription_ends_at?->format('Y/m/d'),
            ] : null,
            'totals' => [
                'students'   => User::role(Roles::STUDENT)->count(),
                'teachers'   => User::role(Roles::TEACHER)->count(),
                'classrooms' => Classroom::count(),
            ],
            'classrooms' => Classroom::withCount('students')->with('teacher:id,name')->get()
                ->map(fn ($c) => [
                    'name' => $c->name, 'teacher' => $c->teacher?->name, 'students' => $c->students_count,
                ]),
        ]);
    }
}
