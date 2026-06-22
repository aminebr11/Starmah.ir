<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Services\GamificationService;
use App\Services\ThemeEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PracticeController extends Controller
{
    public function __construct(
        private ThemeEngine $engine,
        private GamificationService $game,
    ) {}

    /** شروع جلسه‌ی تمرین: N سؤالِ تم‌دار می‌سازد و پاسخ‌ها را امن در session نگه می‌دارد. */
    public function start(Request $request, ?Skill $skill = null): Response
    {
        $user  = $request->user();
        $theme = $this->engine->for($user);
        $count = 5;

        $query = Skill::with('questions')->whereHas('questions');
        if ($skill && $skill->exists) {
            $query->whereKey($skill->id);
        }
        $skills = $query->get();
        abort_if($skills->isEmpty(), 404, 'سؤالی موجود نیست');

        $token = (string) Str::uuid();
        $questions = [];
        $key = [];

        for ($i = 0; $i < $count; $i++) {
            $s = $skills->random();
            $q = $s->questions->random();
            $r = $this->engine->renderQuestion($q, $theme);

            $questions[] = [
                'i'       => $i,
                'skill'   => $s->name,
                'prompt'  => $r['prompt'],
                'choices' => array_map(fn ($c) => ['value' => $c['value']], $r['choices']),
                'xp'      => $r['xp'],
            ];
            $key[$i] = [
                'answer'   => $r['answer'],
                'xp'       => $r['xp'],
                'skill_id' => $s->id,
            ];
        }

        $request->session()->put("practice.$token", $key);

        return Inertia::render('Student/Practice', [
            'token'     => $token,
            'questions' => $questions,
            'skillName' => $skill?->name,
        ]);
    }

    /** ثبت پاسخ‌ها، نمره‌دهی سمت سرور، و اعطای پاداش. */
    public function submit(Request $request)
    {
        $data = $request->validate([
            'token'           => ['required', 'string'],
            'answers'         => ['required', 'array'],
            'answers.*.i'     => ['required', 'integer'],
            'answers.*.value' => ['nullable'],
        ]);

        $key = $request->session()->pull("practice.{$data['token']}");
        abort_if(! $key, 419, 'جلسه‌ی تمرین منقضی شده');

        $user = $request->user();
        $correct = 0;
        $xp = 0;
        $perSkill = [];
        $details = [];

        foreach ($data['answers'] as $ans) {
            $i = $ans['i'];
            if (! isset($key[$i])) {
                continue;
            }
            $k = $key[$i];
            $ok = (string) ($ans['value'] ?? '') === (string) $k['answer'];

            $perSkill[$k['skill_id']] ??= ['correct' => 0, 'total' => 0];
            $perSkill[$k['skill_id']]['total']++;
            if ($ok) {
                $correct++;
                $xp += $k['xp'];
                $perSkill[$k['skill_id']]['correct']++;
            }
            $details[] = ['i' => $i, 'correct' => $ok, 'answer' => $k['answer']];
        }

        $total = count($key);
        $primarySkill = array_key_first($perSkill);

        $reward = $this->game->recordPractice(
            student: $user,
            score: $correct,
            max: $total,
            xp: $xp,
            skillId: $primarySkill,
            perSkill: $perSkill,
        );

        return response()->json([
            'correct'     => $correct,
            'total'       => $total,
            'xp'          => $xp,
            'new_badges'  => $reward['badges'],
            'details'     => $details,
        ]);
    }
}
