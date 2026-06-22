<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeacherDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $teacher = $request->user();

        $classrooms = Classroom::withCount('students')
            ->where('teacher_id', $teacher->id)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'grade' => $c->grade,
                'join_code' => $c->join_code, 'students' => $c->students_count,
            ]);

        return Inertia::render('Teacher/Dashboard', [
            'classrooms' => $classrooms,
            'totals' => [
                'classrooms' => $classrooms->count(),
                'students'   => $classrooms->sum('students'),
            ],
        ]);
    }

    public function show(Request $request, Classroom $classroom): Response
    {
        abort_unless($classroom->teacher_id === $request->user()->id, 403);

        $students = $classroom->students()->get()->map(fn ($s) => [
            'id'   => $s->id,
            'name' => $s->name,
            'xp'   => $s->totalXp(),
            'avg'  => (int) round($s->skillMastery()->avg('mastery') ?? 0),
        ])->sortByDesc('xp')->values();

        return Inertia::render('Teacher/Classroom', [
            'classroom' => ['id' => $classroom->id, 'name' => $classroom->name, 'join_code' => $classroom->join_code],
            'students'  => $students,
        ]);
    }
}
