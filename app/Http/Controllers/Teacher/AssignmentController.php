<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentController extends Controller
{
    public function create(Request $request): Response
    {
        $teacher = $request->user();

        return Inertia::render('Teacher/AssignmentCreate', [
            'classrooms' => Classroom::where('teacher_id', $teacher->id)->get(['id', 'name']),
            'subjects'   => Subject::with('topics.skills')->where('is_active', true)->get()
                ->map(fn ($s) => [
                    'id' => $s->id, 'name' => $s->name,
                    'skills' => $s->topics->flatMap->skills->map(fn ($sk) => ['id' => $sk->id, 'name' => $sk->name])->values(),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'classroom_id'   => ['required', 'exists:classrooms,id'],
            'title'          => ['required', 'string', 'max:120'],
            'type'           => ['required', 'in:homework,quiz,exam,practice'],
            'skill_ids'      => ['required', 'array', 'min:1'],
            'skill_ids.*'    => ['integer', 'exists:skills,id'],
            'question_count' => ['required', 'integer', 'min:3', 'max:30'],
            'due_at'         => ['nullable', 'date'],
        ]);

        Assignment::create([
            'school_id'      => $request->user()->school_id,
            'classroom_id'   => $data['classroom_id'],
            'teacher_id'     => $request->user()->id,
            'title'          => $data['title'],
            'type'           => $data['type'],
            'skill_ids'      => $data['skill_ids'],
            'question_count' => $data['question_count'],
            'due_at'         => $data['due_at'] ?? null,
            'is_published'   => true,
        ]);

        return redirect()->route('teacher.dashboard')->with('flash', 'تکلیف ساخته شد ✅');
    }
}
