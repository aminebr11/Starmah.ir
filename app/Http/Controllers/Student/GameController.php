<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ActivityAward;
use App\Models\ClassActivity;
use App\Models\XpEntry;
use App\Services\GamificationService;
use App\Support\Jalali;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** بازی‌های دانش‌آموز — فهرست بازی‌های منتشرشده، بازی‌کردن و کسب XP (یک‌بار). */
class GameController extends Controller
{
    /** بازی‌های قابل‌بازی برای این دانش‌آموز. */
    private function playableQuery($user)
    {
        $classroomIds = $user->classrooms()->pluck('classrooms.id');
        return ClassActivity::whereIn('classroom_id', $classroomIds)
            ->where('type', 'game')
            ->where('status', 'active');
    }

    private function isLive(ClassActivity $g): bool
    {
        $at = $g->meta['scheduled_at'] ?? null;
        return empty($at) || now()->greaterThanOrEqualTo($at);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $played = ActivityAward::where('student_id', $user->id)->pluck('class_activity_id')->flip();

        $games = $this->playableQuery($user)->latest()->get()
            ->map(function ($g) use ($played) {
                $meta = $g->meta ?? [];
                return [
                    'id'      => $g->id,
                    'title'   => $g->title,
                    'desc'    => $g->description,
                    'subject' => $meta['subject'] ?? null,
                    'points'  => $g->points,
                    'count'   => count($meta['questions'] ?? []),
                    'live'    => $this->isLive($g),
                    'when'    => ! empty($meta['scheduled_at']) ? Jalali::format($meta['scheduled_at'], true) : null,
                    'played'  => $played->has($g->id),
                ];
            });

        return Inertia::render('Student/Games', [
            'games' => $games->values(),
        ]);
    }

    /** صفحه‌ی بازی (بدون افشای پاسخ درست). */
    public function play(Request $request, ClassActivity $classActivity): Response|RedirectResponse
    {
        $user = $request->user();
        abort_unless($classActivity->type === 'game', 404);
        $inClass = $user->classrooms()->where('classrooms.id', $classActivity->classroom_id)->exists();
        abort_unless($inClass, 403);

        if ($classActivity->status !== 'active' || ! $this->isLive($classActivity)) {
            return redirect()->route('games')->with('flash', 'این بازی هنوز فعال نیست.');
        }

        $meta = $classActivity->meta ?? [];
        $questions = collect($meta['questions'] ?? [])->map(fn ($q, $i) => [
            'i' => $i,
            'prompt' => $q['prompt'],
            'choices' => collect($q['choices'] ?? [])->map(fn ($c) => ['value' => $c['value']])->values(),
        ])->values();

        $award = ActivityAward::where('class_activity_id', $classActivity->id)
            ->where('student_id', $user->id)->first();

        return Inertia::render('Student/GamePlay', [
            'game' => [
                'id' => $classActivity->id, 'title' => $classActivity->title,
                'desc' => $classActivity->description, 'points' => $classActivity->points,
                'mode' => $meta['mode'] ?? 'quiz',
                'questions' => $questions,
            ],
            'alreadyPlayed' => $award ? ['points' => $award->points] : null,
        ]);
    }

    /** ثبت پاسخ‌ها، محاسبه‌ی نمره و اعطای XP (یک‌بار، متناسب با درست‌ها). */
    public function submit(Request $request, ClassActivity $classActivity, GamificationService $game): RedirectResponse
    {
        $user = $request->user();
        abort_unless($classActivity->type === 'game', 404);
        $inClass = $user->classrooms()->where('classrooms.id', $classActivity->classroom_id)->exists();
        abort_unless($inClass, 403);

        $data = $request->validate([
            'answers'   => ['required', 'array'],
            'answers.*' => ['nullable', 'integer'],
        ]);

        $questions = collect($classActivity->meta['questions'] ?? []);
        $total = $questions->count();
        $correct = 0;
        foreach ($questions as $i => $q) {
            $correctIdx = collect($q['choices'] ?? [])->search(fn ($c) => ! empty($c['correct']));
            if ($correctIdx !== false && (int) ($data['answers'][$i] ?? -1) === (int) $correctIdx) {
                $correct++;
            }
        }
        $earned = $total ? (int) round($classActivity->points * $correct / $total) : 0;

        // فقط بار اول XP می‌دهیم (idempotent با ActivityAward)
        $already = ActivityAward::where('class_activity_id', $classActivity->id)
            ->where('student_id', $user->id)->exists();

        if (! $already && $earned > 0) {
            DB::transaction(function () use ($classActivity, $user, $earned, $game) {
                $award = ActivityAward::create([
                    'class_activity_id' => $classActivity->id,
                    'student_id'        => $user->id,
                    'points'            => $earned,
                    'awarded_by'        => $classActivity->teacher_id,
                ]);
                XpEntry::create([
                    'student_id'  => $user->id,
                    'season_id'   => $game->activeSeasonId($user),
                    'amount'      => $earned,
                    'reason'      => '🎮 بازی — ' . $classActivity->title,
                    'source_type' => ActivityAward::class,
                    'source_id'   => $award->id,
                    'awarded_by'  => $classActivity->teacher_id,
                ]);
            });
        }

        return back()->with('flash', $already
            ? "این بازی را قبلاً انجام داده‌ای؛ نتیجه‌ی این دور: {$correct} از {$total} ✅"
            : "آفرین! {$correct} از {$total} درست — +{$earned} امتیاز 🎉");
    }
}
