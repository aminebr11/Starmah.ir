<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\Mission;
use App\Models\MissionCompletion;
use App\Models\SmartQuestionBank;
use App\Services\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * مأموریت‌های روزانه‌ی دانش‌آموز — از مأموریت‌هایی که معلمِ کلاسش تعریف کرده،
 * سؤال‌ها از بانکِ سؤالِ همان معلم می‌آیند. هر مأموریت روزی یک‌بار امتیاز/نشان دارد.
 */
class MissionController extends Controller
{
    /** شناسه‌ی معلم‌ها و کلاس‌های دانش‌آموز. */
    private function scope($user): array
    {
        $classrooms = $user->classrooms()->with('teacher')->get();
        return [
            'teacher_ids' => $classrooms->pluck('teacher_id')->filter()->unique()->values()->all(),
            'classroom_ids' => $classrooms->pluck('id')->all(),
        ];
    }

    private function availableQuery($user)
    {
        ['teacher_ids' => $tids, 'classroom_ids' => $cids] = $this->scope($user);
        return Mission::whereIn('teacher_id', $tids ?: [0])
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('classroom_id')->orWhereIn('classroom_id', $cids ?: [0]));
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $missions = $this->availableQuery($user)->with('teacher:id,name')->get();
        $today = now()->toDateString();
        $doneToday = MissionCompletion::where('student_id', $user->id)
            ->where('play_date', $today)->pluck('mission_id')->all();

        $cards = $missions->map(fn (Mission $m) => [
            'id' => $m->id, 'title' => $m->title, 'subject' => $m->subject, 'lesson_no' => $m->lesson_no,
            'difficulty' => $m->difficulty, 'question_count' => $m->question_count, 'xp_reward' => $m->xp_reward,
            'badge_name' => $m->badge_name, 'badge_icon' => $m->badge_icon,
            'teacher' => optional($m->teacher)->name,
            'available' => $this->bankQuery($m)->count() >= 1,
            'done_today' => in_array($m->id, $doneToday, true),
        ])->values();

        return Inertia::render('Student/Missions', [
            'missions' => $cards,
            'streak' => $this->streak($user),
        ]);
    }

    /** پخشِ روزهای متوالیِ انجامِ مأموریت (برای انگیزه). */
    private function streak($user): int
    {
        $dates = MissionCompletion::where('student_id', $user->id)
            ->orderByDesc('play_date')->pluck('play_date')->map(fn ($d) => $d->toDateString())->unique()->values();
        $streak = 0; $cursor = now()->startOfDay();
        foreach ($dates as $d) {
            if ($d === $cursor->toDateString()) { $streak++; $cursor->subDay(); }
            elseif ($d === $cursor->copy()->subDay()->toDateString() && $streak === 0) { $streak++; $cursor->subDays(2); }
            else break;
        }
        return $streak;
    }

    private function bankQuery(Mission $m)
    {
        return SmartQuestionBank::withoutGlobalScopes()
            ->where('school_id', $m->school_id)
            ->where('teacher_id', $m->teacher_id)
            ->whereIn('type', ['mc', 'tf'])
            ->when($m->subject, fn ($q) => $q->where('subject', $m->subject))
            ->when($m->lesson_no, fn ($q) => $q->where('lesson_no', $m->lesson_no))
            ->when($m->difficulty, fn ($q) => $q->where('difficulty', $m->difficulty));
    }

    public function play(Request $request, Mission $mission): Response
    {
        $user = $request->user();
        abort_unless($this->availableQuery($user)->whereKey($mission->id)->exists(), 403);

        $pool = $this->bankQuery($mission)->inRandomOrder()->limit(max(1, (int) $mission->question_count))->get();
        abort_if($pool->isEmpty(), 404, 'برای این مأموریت هنوز سؤالی در بانک نیست.');

        $token = (string) Str::uuid();
        $questions = []; $key = [];
        foreach ($pool->values() as $i => $q) {
            $choices = collect($q->choices ?? [])->values();
            $correct = $choices->first(fn ($c) => ! empty($c['correct']));
            $questions[] = [
                'i' => $i, 'type' => $q->type, 'prompt' => $q->prompt,
                'choices' => $choices->map(fn ($c) => ['value' => $c['value'] ?? ''])->values(),
            ];
            $key[$i] = ['answer' => $correct['value'] ?? null];
        }
        $request->session()->put("mission.$token", ['mission_id' => $mission->id, 'key' => $key]);

        return Inertia::render('Student/MissionPlay', [
            'mission' => [
                'id' => $mission->id, 'title' => $mission->title, 'subject' => $mission->subject,
                'xp_reward' => $mission->xp_reward, 'badge_name' => $mission->badge_name, 'badge_icon' => $mission->badge_icon,
            ],
            'token' => $token,
            'questions' => $questions,
        ]);
    }

    public function submit(Request $request, GamificationService $game): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'answers' => ['required', 'array'],
            'answers.*.i' => ['required', 'integer'],
            'answers.*.value' => ['nullable'],
        ]);
        $sess = $request->session()->pull("mission.{$data['token']}");
        abort_if(! $sess, 419, 'جلسه‌ی مأموریت منقضی شده');

        $user = $request->user();
        $mission = Mission::find($sess['mission_id']);
        abort_if(! $mission, 404);

        $key = $sess['key'];
        $correct = 0; $total = count($key);
        foreach ($data['answers'] as $ans) {
            $i = $ans['i'];
            if (! isset($key[$i])) continue;
            if ((string) ($ans['value'] ?? '') === (string) $key[$i]['answer']) $correct++;
        }

        $today = now()->toDateString();
        $already = MissionCompletion::where('mission_id', $mission->id)
            ->where('student_id', $user->id)->where('play_date', $today)->exists();

        $passed = $total > 0 && ($correct / $total) >= 0.6;
        $xpGain = 0; $badge = null;
        if (! $already) {
            // امتیازِ متناسب با درصدِ درست، حداقل نیمی در صورتِ قبولی
            $xpGain = (int) round($mission->xp_reward * ($total ? $correct / $total : 0));
            if ($xpGain > 0) {
                $game->award($user, $xpGain, '🎯 مأموریت روزانه — ' . $mission->title,
                    $mission->teacher, Mission::class, $mission->id);
            }
            MissionCompletion::create([
                'mission_id' => $mission->id, 'student_id' => $user->id, 'play_date' => $today,
                'score' => $correct, 'total' => $total, 'xp_awarded' => $xpGain,
            ]);
            if ($passed && $mission->badge_name) {
                $badge = $this->awardBadge($user, $mission);
            }
        }

        return response()->json([
            'correct' => $correct, 'total' => $total,
            'xp' => $xpGain, 'already' => $already, 'passed' => $passed, 'badge' => $badge,
        ]);
    }

    /** نشانِ مأموریت را (در صورت نبودن) می‌سازد و به دانش‌آموز می‌دهد. */
    private function awardBadge($user, Mission $mission): ?array
    {
        $key = 'mission-' . $mission->id;
        $badge = Badge::firstOrCreate(['key' => $key], [
            'name' => $mission->badge_name,
            'emoji' => $mission->badge_icon ?: '🎖️',
            'description' => 'نشانِ مأموریتِ «' . $mission->title . '»',
        ]);
        if (! $user->badges()->where('badges.id', $badge->id)->exists()) {
            $user->badges()->attach($badge->id, ['awarded_at' => now()]);
        }
        return ['name' => $badge->name, 'emoji' => $badge->emoji];
    }
}
