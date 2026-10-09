<?php

namespace App\Http\Controllers\Live;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Teacher\LiveContestController;
use App\Models\LiveContest;
use App\Models\LiveContestAnswer;
use App\Models\LiveContestPlayer;
use App\Support\Jalali;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** دانش‌آموز: «🏆 مسابقه‌ی زنده» با گوشی یا تبلت. */
class PlayController extends Controller
{
    private function allowed(Request $request, LiveContest $contest): void
    {
        abort_unless($contest->phase !== 'draft' && LiveContest::forStudent($request->user())->whereKey($contest->id)->exists(), 403);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $list = LiveContest::ready() ? LiveContest::forStudent($user)->latest()->limit(30)->get() : collect();
        $mine = LiveContestPlayer::where('student_id', $user->id)->whereIn('live_contest_id', $list->pluck('id'))->get()->keyBy('live_contest_id');

        return Inertia::render('Student/LiveContests', [
            'contests' => $list->map(function ($c) use ($mine) {
                $p = $mine[$c->id] ?? null;
                $rank = $p && $c->phase === 'end' ? LiveContestPlayer::where('live_contest_id', $c->id)->where('score', '>', (int) $p->score)->count() + 1 : null;

                return [
                    'id' => $c->id, 'title' => $c->title, 'phase' => $c->phase, 'questions' => $c->total(),
                    'when' => LiveContestController::when($c), 'date' => Jalali::format($c->created_at),
                    'score' => $p?->score, 'correct' => $p?->correct, 'rank' => $rank,
                ];
            })->values(),
        ]);
    }

    public function show(Request $request, LiveContest $contest): Response
    {
        $this->allowed($request, $contest);
        if ($contest->phase !== 'end') {
            LiveContestPlayer::firstOrCreate(['live_contest_id' => $contest->id, 'student_id' => $request->user()->id], ['last_seen_at' => now()]);
        }

        return Inertia::render('Student/LivePlay', ['contest' => ['id' => $contest->id, 'title' => $contest->title, 'total' => $contest->total(), 'seconds' => $contest->seconds]]);
    }

    /** وضعیتِ سبک برای گوشیِ دانش‌آموز (هر ۱٫۵ ثانیه). */
    public function poll(Request $request, LiveContest $contest): JsonResponse
    {
        $this->allowed($request, $contest);
        $user = $request->user();
        $contest->tick();
        $player = LiveContestPlayer::where('live_contest_id', $contest->id)->where('student_id', $user->id)->first();
        if (! $player && $contest->phase !== 'end') {
            $player = LiveContestPlayer::firstOrCreate(['live_contest_id' => $contest->id, 'student_id' => $user->id], ['last_seen_at' => now()]);
        } elseif ($player && (! $player->last_seen_at || $player->last_seen_at->lt(now()->subSeconds(10)))) {
            $player->forceFill(['last_seen_at' => now()])->saveQuietly();
        }
        $q = $contest->current;
        $question = $contest->live() ? ($contest->questions[$q] ?? null) : null;
        $mine = $question ? LiveContestAnswer::where('live_contest_id', $contest->id)->where('student_id', $user->id)->where('q_index', $q)->first() : null;
        $rank = $player && in_array($contest->phase, ['reveal', 'end'], true)
            ? LiveContestPlayer::where('live_contest_id', $contest->id)->where('score', '>', (int) $player->score)->count() + 1 : null;

        return response()->json([
            'phase' => $contest->phase, 'current' => $q, 'total' => $contest->total(), 'seconds' => $contest->seconds,
            'remaining' => $contest->remaining(),
            'starts_in' => $contest->starts_at && $contest->phase === 'lobby' ? max(0, now()->diffInSeconds($contest->starts_at, false)) : null,
            'question' => $question ? ['prompt' => $question['prompt'], 'choices' => $question['choices']] : null,
            'answer' => $contest->phase === 'reveal' && $question ? $question['answer'] : null,
            'mine' => $mine ? ['choice' => $mine->choice, 'correct' => $contest->phase === 'reveal' ? $mine->correct : null,
                'points' => $contest->phase === 'reveal' ? $mine->points : null] : null,
            'me' => $player ? ['score' => (int) $player->score, 'correct' => (int) $player->correct, 'streak' => (int) $player->streak, 'rank' => $rank] : null,
            'players' => $contest->phase === 'lobby' || $contest->phase === 'end' ? $contest->players()->count() : null,
            'podium' => $contest->phase === 'end' ? $contest->ranking()->take(3)->values() : null,
        ]);
    }

    public function answer(Request $request, LiveContest $contest): JsonResponse
    {
        $this->allowed($request, $contest);
        $data = $request->validate(['q' => ['required', 'integer', 'min:0'], 'choice' => ['required', 'integer', 'min:0', 'max:3']]);
        $res = $contest->answer($request->user(), (int) $data['q'], (int) $data['choice']);

        return response()->json($res, $res['ok'] ? 200 : 422);
    }
}
