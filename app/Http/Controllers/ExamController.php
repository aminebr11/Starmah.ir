<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Skill;
use App\Services\GamificationService;
use App\Services\ThemeEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** آزمون آنلاین — دانش‌آموز آزمون‌های منتشرشده‌ی کلاسش را می‌دهد. */
class ExamController extends Controller
{
    public function __construct(private ThemeEngine $engine, private GamificationService $game) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $classroomId = $user->classrooms()->value('classrooms.id');

        $assignments = Assignment::where('classroom_id', $classroomId)
            ->where('is_published', true)->latest()->get();

        $subs = AssignmentSubmission::where('student_id', $user->id)
            ->whereIn('assignment_id', $assignments->pluck('id'))->get()->keyBy('assignment_id');

        $list = $assignments->map(fn ($a) => [
            'id' => $a->id, 'title' => $a->title, 'type' => $a->type,
            'count' => $a->question_count,
            'done' => isset($subs[$a->id]),
            'score' => $subs[$a->id]->score ?? null,
            'max' => $subs[$a->id]->max_score ?? null,
        ]);

        return Inertia::render('Student/Exams', ['exams' => $list]);
    }

    public function take(Request $request, Assignment $assignment): Response
    {
        $user = $request->user();
        abort_unless($assignment->is_published, 404);
        $theme = $this->engine->for($user);

        $skills = Skill::with('questions')->whereHas('questions')
            ->when($assignment->skill_ids, fn ($q) => $q->whereIn('id', $assignment->skill_ids))->get();
        abort_if($skills->isEmpty(), 404, 'سؤالی موجود نیست');

        $count = min($assignment->question_count, 12);
        $token = (string) Str::uuid();
        $questions = []; $key = [];
        for ($i = 0; $i < $count; $i++) {
            $s = $skills->random(); $q = $s->questions->random();
            $r = $this->engine->renderQuestion($q, $theme);
            $questions[] = ['i' => $i, 'skill' => $s->name, 'prompt' => $r['prompt'],
                'choices' => array_map(fn ($c) => ['value' => $c['value']], $r['choices'])];
            $key[$i] = ['answer' => $r['answer']];
        }
        $request->session()->put("exam.$token", $key);

        return Inertia::render('Student/ExamTake', [
            'assignment' => $assignment->only('id', 'title'),
            'token' => $token, 'questions' => $questions,
        ]);
    }

    public function submit(Request $request, Assignment $assignment)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'answers' => ['required', 'array'],
        ]);
        $key = $request->session()->pull("exam.{$data['token']}");
        abort_if(! $key, 419, 'جلسه منقضی شد');

        $user = $request->user();
        $correct = 0;
        foreach ($data['answers'] as $ans) {
            $i = $ans['i'] ?? null;
            if (isset($key[$i]) && (string) ($ans['value'] ?? '') === (string) $key[$i]['answer']) {
                $correct++;
            }
        }
        $total = count($key);
        $points = (int) round($correct / max(1, $total) * 100); // امتیاز بر اساس درصد

        AssignmentSubmission::updateOrCreate(
            ['assignment_id' => $assignment->id, 'student_id' => $user->id],
            ['score' => $correct, 'max_score' => $total, 'accuracy' => round($correct / max(1, $total) * 100, 2),
             'status' => 'completed', 'submitted_at' => now()]
        );

        $this->game->awardXp($user, $points, '💻 آزمون آنلاین — ' . $assignment->title);

        return response()->json(['correct' => $correct, 'total' => $total, 'points' => $points]);
    }
}
