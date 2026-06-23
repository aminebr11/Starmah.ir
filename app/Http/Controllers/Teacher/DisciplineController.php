<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\DisciplineRecord;
use App\Models\DisciplineTopic;
use App\Models\User;
use App\Services\GamificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** انضباط: موضوعات (تشویق/تخلف با امتیاز) + ثبت برای دانش‌آموزان. */
class DisciplineController extends Controller
{
    public function index(Request $request): Response
    {
        $teacher = $request->user();
        $classroom = Classroom::where('teacher_id', $teacher->id)->first();

        $students = $classroom
            ? $classroom->students()->with('theme')->get()->map(fn ($s) => [
                'id' => $s->id, 'name' => $s->name, 'group' => $s->theme?->name, 'emoji' => $s->theme?->emoji,
            ])->values()
            : collect();

        $topics = DisciplineTopic::where('is_active', true)
            ->where(fn ($q) => $q->where('school_id', $teacher->school_id)->orWhereNull('school_id'))
            ->orderBy('kind')->get()
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'kind' => $t->kind, 'points' => $t->points]);

        $records = DisciplineRecord::where('recorded_by', $teacher->id)
            ->with('student:id,name')->latest()->limit(40)->get()
            ->map(fn ($r) => [
                'student' => $r->student?->name, 'title' => $r->title ?? $r->note, 'points' => $r->points,
                'kind' => $r->points >= 0 ? 'positive' : 'negative', 'date' => $r->jalaliDate(),
            ]);

        return Inertia::render('Teacher/Discipline', [
            'students' => $students,
            'topics'   => $topics,
            'records'  => $records,
        ]);
    }

    public function storeTopic(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'   => ['required', 'string', 'max:80'],
            'kind'   => ['required', 'in:positive,negative'],
            'points' => ['required', 'integer'],
        ]);
        $points = $data['kind'] === 'negative' ? -abs($data['points']) : abs($data['points']);

        DisciplineTopic::create([
            'school_id'  => $request->user()->school_id,
            'name'       => $data['name'],
            'kind'       => $data['kind'],
            'points'     => $points,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('flash', 'موضوع انضباطی ساخته شد ✅');
    }

    public function destroyTopic(Request $request, DisciplineTopic $disciplineTopic): RedirectResponse
    {
        if ($disciplineTopic->school_id === $request->user()->school_id) {
            $disciplineTopic->update(['is_active' => false]);
        }
        return back();
    }

    /** ثبت یک یا چند موضوع برای یک یا چند دانش‌آموز. */
    public function record(Request $request, GamificationService $game): RedirectResponse
    {
        $data = $request->validate([
            'topic_ids'     => ['required', 'array', 'min:1'],
            'topic_ids.*'   => ['integer', 'exists:discipline_topics,id'],
            'student_ids'   => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'exists:users,id'],
            'note'          => ['nullable', 'string', 'max:255'],
        ]);

        $teacher = $request->user();
        $topics = DisciplineTopic::whereIn('id', $data['topic_ids'])->get();
        $students = User::whereIn('id', $data['student_ids'])->get();
        $count = 0;

        DB::transaction(function () use ($topics, $students, $teacher, $data, $game, &$count) {
            foreach ($students as $student) {
                foreach ($topics as $topic) {
                    DisciplineRecord::create([
                        'school_id'    => $teacher->school_id,
                        'student_id'   => $student->id,
                        'classroom_id' => $student->classrooms()->value('classrooms.id'),
                        'topic_id'     => $topic->id,
                        'recorded_by'  => $teacher->id,
                        'type'         => $topic->kind === 'positive' ? 'star' : 'warning',
                        'title'        => $topic->name,
                        'points'       => $topic->points,
                        'note'         => $data['note'] ?? null,
                    ]);
                    $game->awardXp($student, $topic->points,
                        ($topic->kind === 'positive' ? '⭐ تشویق' : '⚠️ انضباط') . ' — ' . $topic->name, $teacher);
                    $count++;
                }
            }
        });

        return back()->with('flash', "{$count} مورد انضباطی ثبت شد ✅");
    }
}
