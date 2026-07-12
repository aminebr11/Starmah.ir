<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\EduGame;
use App\Models\EduGameAttempt;
use App\Models\User;
use App\Services\GamificationService;
use App\Support\Jalali;
use App\Support\LevelConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/** «دنیای بازی‌های آموزشی» — صفحه‌ی دانش‌آموز + اجرای بازی. */
class EduGameWorldController extends Controller
{
    /** بازی‌هایی که برای این دانش‌آموز در دسترس‌اند (بدون فیلترِ وضعیت). */
    private function baseQuery(User $user)
    {
        $teacherIds = $user->classrooms()->with('teacher')->get()->pluck('teacher_id')->filter()->unique();
        return EduGame::whereIn('teacher_id', $teacherIds)
            ->where('status', 'published')
            ->with(['template', 'theme', 'targets'])
            ->withCount('questions');
    }

    private function targeted(EduGame $g, User $user): bool
    {
        if ($g->targets->isEmpty()) return true; // همه‌ی دانش‌آموزانِ کلاس
        return $g->targets->contains(fn ($t) => $t->student_id === $user->id)
            || $g->targets->contains(fn ($t) => $t->theme_id && $t->theme_id === $user->theme_id);
    }

    public function world(Request $request): Response
    {
        $user = $request->user();

        // ورود به دنیای بازی‌ها = خوانده‌شدن اعلان‌های «بازی جدید»
        \Illuminate\Support\Facades\DB::table('announcement_recipients')
            ->where('user_id', $user->id)->whereNull('read_at')
            ->whereIn('announcement_id', \App\Models\Announcement::where('title', 'like', '🎮%')->pluck('id'))
            ->update(['read_at' => now()]);

        $games = $this->baseQuery($user)->latest()->get()->filter(fn ($g) => $this->targeted($g, $user));

        $attempts = EduGameAttempt::where('student_id', $user->id)
            ->whereIn('edu_game_id', $games->pluck('id'))->get()->keyBy('edu_game_id');

        $cards = $games->map(function ($g) use ($attempts, $user) {
            $att = $attempts->get($g->id);
            $live = $g->isLive();
            $status = ! $live ? 'locked'
                : ($att && $att->status === 'completed' ? 'done'
                    : ($att ? 'in_progress' : 'new'));
            return [
                'id' => $g->id, 'title' => $g->title, 'desc' => $g->description,
                'template' => optional($g->template)->name, 'icon' => optional($g->template)->icon ?? '🎮',
                'subject' => $g->subject, 'grade' => $g->grade, 'difficulty' => $g->difficulty,
                'theme' => optional($g->theme)->name, 'theme_emoji' => optional($g->theme)->emoji,
                'teacher' => optional($g->teacher)->name,
                'cover' => $g->cover_path ? Storage::disk('public')->url($g->cover_path) : null,
                'questions' => $g->questions_count,
                'maxPoints' => (int) $g->questions()->sum('points'),
                'status' => $status,
                'score' => $att?->score,
                'lockReason' => ! $live && $g->publish_at && now()->lessThan($g->publish_at)
                    ? 'انتشار: ' . Jalali::format($g->publish_at, true)
                    : (! $live ? 'به‌زودی' : null),
                'when' => $g->publish_at ? Jalali::format($g->publish_at, true) : null,
            ];
        })->values();

        // پروفایل + گروه + امتیاز + مدال‌ها + رتبه
        $xp = $user->totalXp();
        $classroom = $user->classrooms()->with('teacher')->first();
        $members = $classroom ? $classroom->students()->with('theme')->get()
            ->map(fn ($s) => ['id' => $s->id, 'xp' => $s->totalXp(), 'theme_id' => $s->theme_id])
            ->sortByDesc('xp')->values() : collect();
        $groupMembers = $members->where('theme_id', $user->theme_id)->values();
        $rankGroup = $groupMembers->search(fn ($m) => $m['id'] === $user->id);

        return Inertia::render('Student/GameWorld', [
            'me' => [
                'name' => $user->name, 'xp' => $xp,
                'level' => LevelConfig::levelOf($xp, LevelConfig::xpPerLevel($user->school_id)),
                'badges' => $user->badges()->count(),
                'rank_group' => $rankGroup === false ? null : $rankGroup + 1,
                'group_size' => $groupMembers->count(),
            ],
            'cards' => $cards,
            'stats' => [
                'active' => $cards->whereIn('status', ['new', 'in_progress'])->count(),
                'done' => $cards->where('status', 'done')->count(),
                'locked' => $cards->where('status', 'locked')->count(),
            ],
        ]);
    }

    public function play(Request $request, EduGame $eduGame): Response|RedirectResponse
    {
        $user = $request->user();
        $eduGame->load(['template', 'theme', 'targets', 'questions']);
        abort_unless($this->canAccess($eduGame, $user), 403);
        if (! $eduGame->isLive()) {
            return redirect()->route('gameworld')->with('flash', 'این بازی هنوز در دسترس نیست.');
        }

        $att = EduGameAttempt::firstOrCreate(
            ['edu_game_id' => $eduGame->id, 'student_id' => $user->id],
            ['max_score' => (int) $eduGame->questions->sum('points')]
        );

        return Inertia::render('Student/GamePlayer', [
            'game' => [
                'id' => $eduGame->id, 'title' => $eduGame->title, 'desc' => $eduGame->description,
                'template' => $eduGame->template_key, 'template_name' => optional($eduGame->template)->name,
                'board_html' => optional($eduGame->template)->board_html,
                'board_css' => optional($eduGame->template)->board_css,
                'difficulty' => $eduGame->difficulty,
                'rules' => $eduGame->rules ?? [],
                'theme' => $eduGame->theme ? [
                    'name' => $eduGame->theme->name, 'emoji' => $eduGame->theme->emoji,
                    'skin' => $eduGame->theme->skin,
                ] : null,
                'questions' => $eduGame->questions->map(fn ($q, $i) => [
                    'i' => $i, 'type' => $q->type, 'prompt' => $q->prompt,
                    'media' => $q->media_path,
                    'choices' => collect($q->choices ?? [])->map(fn ($c) => [
                        'value' => $c['value'] ?? '', 'correct' => (bool) ($c['correct'] ?? false),
                    ])->values(),
                    'hint1' => $q->hint1, 'hint2' => $q->hint2,
                    'explanation' => $q->explanation, 'points' => $q->points,
                ])->values(),
            ],
            'attempt' => [
                'status' => $att->status, 'progress' => $att->progress ?? [],
                'score' => $att->score,
            ],
        ]);
    }

    /** ذخیره‌ی پیشرفت (ادامه‌دادن بعدی) — بدون پایان. */
    public function progress(Request $request, EduGame $eduGame): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canAccess($eduGame, $user), 403);
        $data = $request->validate([
            'progress' => ['required', 'array'],
            'hints_used' => ['nullable', 'integer', 'min:0'],
        ]);
        EduGameAttempt::where('edu_game_id', $eduGame->id)->where('student_id', $user->id)
            ->update(['progress' => $data['progress'], 'hints_used' => $data['hints_used'] ?? 0]);
        return back();
    }

    /** پایان بازی: امتیازدهیِ معتبرِ سمت سرور + XP (یک‌بار). */
    public function finish(Request $request, EduGame $eduGame, GamificationService $game): RedirectResponse
    {
        $user = $request->user();
        $eduGame->load('questions');
        abort_unless($this->canAccess($eduGame, $user), 403);

        $data = $request->validate([
            'answers' => ['required', 'array'],       // { i: choiceIndex }
            'hints_used' => ['nullable', 'integer', 'min:0'],
            'duration_sec' => ['nullable', 'integer', 'min:0'],
        ]);

        // امتیازدهیِ authoritative از روی دیتابیس
        $score = 0; $max = 0; $detail = [];
        foreach ($eduGame->questions->values() as $i => $q) {
            $max += $q->points;
            $correctIdx = collect($q->choices ?? [])->search(fn ($c) => ! empty($c['correct']));
            $picked = $data['answers'][$i] ?? null;
            $ok = $correctIdx !== false && (int) $picked === (int) $correctIdx;
            if ($ok) $score += $q->points;
            $detail[$i] = ['picked' => $picked, 'correct' => $ok];
        }

        $att = EduGameAttempt::firstOrCreate(
            ['edu_game_id' => $eduGame->id, 'student_id' => $user->id],
            ['max_score' => $max]
        );
        $firstCompletion = $att->status !== 'completed';
        $bestScore = max($att->score, $score);

        $att->update([
            'score' => $bestScore, 'max_score' => $max,
            'progress' => ['answers' => $detail],
            'status' => 'completed', 'hints_used' => $data['hints_used'] ?? $att->hints_used,
            'duration_sec' => $data['duration_sec'] ?? $att->duration_sec,
            'completed_at' => now(),
        ]);

        // XP فقط بار اولِ تکمیل (idempotent با منبعِ Attempt)
        if ($firstCompletion && $bestScore > 0) {
            $game->award($user, $bestScore, '🎮 بازی — ' . $eduGame->title,
                $eduGame->teacher, EduGameAttempt::class, $att->id);
        }

        $correctCount = collect($detail)->where('correct', true)->count();
        $total = $eduGame->questions->count();
        return back()->with('flash', $firstCompletion
            ? "آفرین! {$correctCount} از {$total} درست — +{$bestScore} امتیاز 🎉"
            : "دوباره بازی کردی؛ بهترین نتیجه‌ات ثبت است ({$correctCount} از {$total}).");
    }

    private function canAccess(EduGame $game, User $user): bool
    {
        $teaches = $user->classrooms()->whereHas('teacher', fn ($q) => $q->where('users.id', $game->teacher_id))->exists();
        if (! $teaches) return false;
        $game->relationLoaded('targets') || $game->load('targets');
        return $this->targeted($game, $user);
    }
}
