<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\ClassContent;
use App\Models\ContentView;
use App\Models\EduGame;
use App\Models\EduGameAttempt;
use App\Models\Mission;
use App\Models\MissionCombo;
use App\Models\MissionCompletion;
use App\Models\SmartExam;
use App\Models\SmartExamAttempt;
use App\Models\Worksheet;
use App\Models\WorksheetSubmission;
use App\Services\GamificationService;
use App\Support\BankAccess;
use App\Support\Jalali;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * مأموریت‌های روزانه — سمتِ دانش‌آموز.
 *
 * هر مأموریت روزی یک‌بار امتیاز دارد. مأموریت‌های فعالیت‌محور (پادکست،
 * ویدیو، جزوه، کاربرگ، بازی، آزمون) فقط پس از انجامِ **واقعیِ** همان
 * فعالیت قابلِ دریافت‌اند — سرور خودش بررسی می‌کند، نه مرورگر.
 *
 * ── جعبه‌ی گنجِ روزانه ────────────────────────────────────────────────
 * وقتی دانش‌آموز همه‌ی مأموریت‌های امروزش را تمام کند، یک پاداشِ اضافه
 * باز می‌شود: ۲۵٪ مجموعِ امتیازِ مأموریت‌های آن روز، به‌علاوه‌ی پاداشِ
 * روزهای پیاپی. این پاداش هم روزی یک‌بار است و در جدولِ جداگانه‌ای
 * ثبت می‌شود تا هیچ‌وقت دوبار داده نشود.
 */
class MissionController extends Controller
{
    /** سهمِ جعبه‌ی گنج از مجموعِ امتیازِ مأموریت‌های روز. */
    private const COMBO_RATIO = 0.25;

    private const COMBO_MIN = 10;
    private const COMBO_MAX = 120;

    /** پاداشِ هر روزِ پیاپی (تا سقف). */
    private const STREAK_BONUS = 5;

    private const STREAK_BONUS_MAX = 50;

    /* ═══════════════════════ دامنه‌ی دسترسی ═══════════════════════ */

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
            ->where(fn ($q) => $q->whereNull('classroom_id')->orWhereIn('classroom_id', $cids ?: [0]))
            // هدف‌گیریِ تیمی: مأموریتِ بدونِ تیم برای همه، یا مأموریتِ تیمِ خودِ دانش‌آموز
            ->where(fn ($q) => $q->whereNull('theme_id')->orWhere('theme_id', $user->theme_id));
    }

    /* ═══════════════════════ فهرستِ مأموریت‌ها ═══════════════════════ */

    public function index(Request $request): Response
    {
        $user = $request->user();
        $missions = $this->availableQuery($user)->with('teacher:id,name')->get();
        $today = now()->toDateString();
        $doneToday = MissionCompletion::where('student_id', $user->id)
            ->whereDate('play_date', $today)->pluck('mission_id')->all();

        $cards = $missions->map(function (Mission $m) use ($user, $doneToday) {
            $type = $m->type ?? 'quiz';
            $done = in_array($m->id, $doneToday, true);

            return [
                'id' => $m->id, 'title' => $m->title, 'description' => $m->description, 'type' => $type,
                'subject' => $m->subject, 'lesson_no' => $m->lesson_no,
                'difficulty' => $m->difficulty, 'question_count' => $m->question_count,
                'xp_reward' => $m->xp_reward,
                'badge_name' => $m->badge_name, 'badge_icon' => $m->badge_icon,
                'teacher' => optional($m->teacher)->name,
                'resource_title' => $this->resourceTitle($m),
                'available' => $type !== 'quiz' || $this->bankQuery($m)->count() >= 1,
                'done_today' => $done,
                'link' => $this->linkFor($m),
                'claimable' => $type !== 'quiz' && ! $done && $this->activityDoneToday($user, $m),
            ];
        })->values();

        return Inertia::render('Student/Missions', [
            'missions' => $cards,
            'streak' => $this->streak($user),
            'combo' => $this->comboState($user, $cards->all()),
            'history' => $this->recentHistory($user),
        ]);
    }

    /* ═══════════════════════ جعبه‌ی گنجِ روزانه ═══════════════════════ */

    /**
     * وضعیتِ جعبه‌ی گنج برای امروز.
     *
     * @param  array<int,array>  $cards  کارت‌های همین لحظه (تا دوباره کوئری نزنیم)
     */
    private function comboState($user, array $cards): array
    {
        $total = count($cards);
        $done = count(array_filter($cards, fn ($c) => $c['done_today']));
        $poolXp = array_sum(array_column($cards, 'xp_reward'));
        $streak = $this->streak($user);

        $claimed = MissionCombo::where('student_id', $user->id)
            ->whereDate('play_date', now()->toDateString())->first();

        return [
            'total' => $total,
            'done' => $done,
            'xp' => $this->comboXp($poolXp, $streak),
            'streak' => $streak,
            'ready' => $total > 0 && $done >= $total && ! $claimed,
            'claimed' => (bool) $claimed,
            'claimed_xp' => $claimed ? (int) $claimed->xp_awarded : 0,
        ];
    }

    private function comboXp(int $poolXp, int $streak): int
    {
        $base = (int) round($poolXp * self::COMBO_RATIO);
        $base = max(self::COMBO_MIN, min(self::COMBO_MAX, $base));

        return $base + min(self::STREAK_BONUS_MAX, $streak * self::STREAK_BONUS);
    }

    /** بازکردنِ جعبه‌ی گنج — فقط وقتی همه‌ی مأموریت‌های امروز تمام شده باشد. */
    public function claimCombo(Request $request, GamificationService $game): RedirectResponse
    {
        $user = $request->user();
        $today = now()->toDateString();

        if (MissionCombo::where('student_id', $user->id)->whereDate('play_date', $today)->exists()) {
            return back()->with('flash', 'جعبه‌ی گنجِ امروز را قبلاً باز کرده‌ای 🎁');
        }

        $missions = $this->availableQuery($user)->get();
        if ($missions->isEmpty()) {
            return back()->with('flash', 'امروز مأموریتی برایت تعریف نشده.');
        }

        $doneIds = MissionCompletion::where('student_id', $user->id)
            ->whereDate('play_date', $today)->pluck('mission_id')->all();
        $remaining = $missions->reject(fn (Mission $m) => in_array($m->id, $doneIds, true));
        if ($remaining->isNotEmpty()) {
            return back()->with('flash', 'هنوز ' . Jalali::fa((string) $remaining->count()) . ' مأموریت مانده — همه را تمام کن تا جعبه باز شود!');
        }

        $streak = $this->streak($user);
        $xp = $this->comboXp((int) $missions->sum('xp_reward'), $streak);

        $game->award($user, $xp, '🎁 جعبه‌ی گنجِ روزانه — همه‌ی مأموریت‌های امروز', null, MissionCombo::class, null);
        MissionCombo::create([
            'student_id' => $user->id, 'play_date' => $today,
            'missions_done' => $missions->count(), 'xp_awarded' => $xp, 'streak' => $streak,
        ]);

        return back()->with('flash', 'جعبه‌ی گنج باز شد! +' . Jalali::fa((string) $xp) . ' امتیاز 🎁🎉');
    }

    /* ═══════════════════════ پخش و تاریخچه ═══════════════════════ */

    /** روزهای پیاپیِ انجامِ مأموریت — سوختِ اصلیِ انگیزه. */
    private function streak($user): int
    {
        $dates = MissionCompletion::where('student_id', $user->id)
            ->orderByDesc('play_date')->pluck('play_date')
            ->map(fn ($d) => $d->toDateString())->unique()->values();

        $streak = 0;
        $cursor = now()->startOfDay();
        foreach ($dates as $d) {
            if ($d === $cursor->toDateString()) {
                $streak++;
                $cursor->subDay();
            } elseif ($d === $cursor->copy()->subDay()->toDateString() && $streak === 0) {
                // دیروز انجام داده و امروز هنوز نه — رشته هنوز زنده است
                $streak++;
                $cursor->subDays(2);
            } else {
                break;
            }
        }

        return $streak;
    }

    /** هفت روزِ اخیر: چند مأموریت در هر روز — برای نوارِ تقویمِ کوچکِ صفحه. */
    private function recentHistory($user): array
    {
        $rows = MissionCompletion::where('student_id', $user->id)
            ->where('play_date', '>=', now()->subDays(6)->toDateString())
            ->get(['play_date', 'xp_awarded']);

        $out = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = now()->subDays($i);
            $key = $d->toDateString();
            $day = $rows->filter(fn ($r) => $r->play_date->toDateString() === $key);
            $out[] = [
                'date' => $key,
                'label' => ['ی', 'د', 'س', 'چ', 'پ', 'ج', 'ش'][(int) $d->dayOfWeek],
                'count' => $day->count(),
                'xp' => (int) $day->sum('xp_awarded'),
                'today' => $i === 0,
            ];
        }

        return $out;
    }

    /* ═══════════════════════ منابع و بررسیِ انجام ═══════════════════════ */

    private function linkFor(Mission $m): ?string
    {
        $rid = $m->resource_id;

        return match ($m->type) {
            'podcast', 'video', 'material' => '/class-content',
            'worksheet' => $rid ? '/worksheets/' . $rid : '/class-content',
            'game' => $rid ? '/game-world/' . $rid . '/play' : '/game-world',
            'exam' => $rid ? '/student/smart-exams/' . $rid . '/take' : '/student/smart-exams',
            default => null,
        };
    }

    private function resourceTitle(Mission $m): ?string
    {
        if (! $m->resource_id) {
            return null;
        }

        return match ($m->type) {
            'podcast', 'video', 'material' => optional(ClassContent::find($m->resource_id))->title,
            'worksheet' => optional(Worksheet::find($m->resource_id))->title,
            'game' => optional(EduGame::find($m->resource_id))->title,
            'exam' => optional(SmartExam::find($m->resource_id))->title,
            default => null,
        };
    }

    /**
     * آیا فعالیتِ موردنیازِ مأموریت امروز واقعاً انجام شده؟ (ضدِ تقلب)
     * اگر معلم موردِ مشخصی انتخاب کرده، فقط همان مورد به‌حساب می‌آید.
     */
    private function activityDoneToday($user, Mission $m): bool
    {
        $today = now()->toDateString();
        $rid = $m->resource_id;

        // محتوای کلاسی (پادکست/ویدیو/جزوه): تکمیلِ تأییدشده‌ی سرور ملاک است
        if (in_array($m->type, ['podcast', 'video', 'material'], true)) {
            return ContentView::where('student_id', $user->id)
                ->whereNotNull('completed_at')
                ->whereDate('completed_at', $today)
                ->when($rid, fn ($q) => $q->where('class_content_id', $rid))
                ->whereHas('content', fn ($q) => $q->where('type', $m->type)->where('teacher_id', $m->teacher_id))
                ->exists();
        }

        return match ($m->type) {
            'worksheet' => WorksheetSubmission::where('student_id', $user->id)
                ->whereNotNull('file_path')->whereDate('submitted_at', $today)
                ->when($rid, fn ($q) => $q->where('worksheet_id', $rid))
                ->whereHas('worksheet', fn ($q) => $q->where('teacher_id', $m->teacher_id))
                ->exists(),
            'game' => EduGameAttempt::where('student_id', $user->id)
                ->where('status', 'completed')->whereDate('completed_at', $today)
                ->when($rid, fn ($q) => $q->where('edu_game_id', $rid))
                ->whereHas('game', fn ($q) => $q->where('teacher_id', $m->teacher_id))
                ->exists(),
            'exam' => SmartExamAttempt::where('student_id', $user->id)
                ->whereIn('status', ['completed', 'needs_review'])
                ->whereDate('finished_at', $today)
                ->when($rid, fn ($q) => $q->where('smart_exam_id', $rid))
                ->whereHas('exam', fn ($q) => $q->where('teacher_id', $m->teacher_id))
                ->exists(),
            default => false,
        };
    }

    /** دریافتِ جایزهٔ مأموریت‌های فعالیت‌محور — یک‌بار در روز. */
    public function claim(Request $request, Mission $mission, GamificationService $game): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->availableQuery($user)->whereKey($mission->id)->exists(), 403);
        abort_unless(in_array($mission->type ?? 'quiz', Mission::ACTIVITY_TYPES, true), 400);

        $today = now()->toDateString();
        if (MissionCompletion::where('mission_id', $mission->id)->where('student_id', $user->id)->whereDate('play_date', $today)->exists()) {
            return back()->with('flash', 'جایزهٔ این مأموریت را امروز گرفته‌ای.');
        }
        if (! $this->activityDoneToday($user, $mission)) {
            return back()->with('flash', 'اول باید فعالیتِ این مأموریت را انجام بدهی، بعد جایزه بگیری.');
        }

        $game->award($user, $mission->xp_reward, '🎯 مأموریت روزانه — ' . $mission->title,
            $mission->teacher, Mission::class, $mission->id);
        MissionCompletion::create([
            'mission_id' => $mission->id, 'student_id' => $user->id, 'play_date' => $today,
            'score' => 1, 'total' => 1, 'xp_awarded' => $mission->xp_reward,
        ]);
        if ($mission->badge_name) {
            $this->awardBadge($user, $mission);
        }

        return back()->with('flash', 'آفرین! جایزهٔ مأموریت را گرفتی: +' . Jalali::fa((string) $mission->xp_reward) . ' امتیاز 🎉');
    }

    /* ═══════════════════════ مأموریتِ سؤالی ═══════════════════════ */

    /**
     * سؤال‌های مأموریت.
     * اگر معلم سؤالِ مشخصی سنجاق کرده باشد دقیقاً همان‌ها؛ وگرنه از
     * فیلترهای درس/شماره‌درس/سطح. دامنه همان بانکِ قابلِ دسترسِ معلم است.
     */
    private function bankQuery(Mission $m)
    {
        $teacher = $m->teacher;
        $base = $teacher
            ? BankAccess::visibleQuery($teacher)->whereIn('type', ['mc', 'tf'])
            : \App\Models\SmartQuestionBank::withoutGlobalScopes()
                ->where('school_id', $m->school_id)->where('teacher_id', $m->teacher_id)
                ->whereIn('type', ['mc', 'tf']);

        if ($ids = $m->question_ids) {
            return $base->whereIn('id', $ids);
        }

        return $base
            ->when($m->subject, fn ($q) => $q->where(fn ($w) => $w->where('subject', $m->subject)->orWhere('book', $m->subject)))
            ->when($m->lesson_no, fn ($q) => $q->where('lesson_no', $m->lesson_no))
            ->when($m->difficulty, fn ($q) => $q->where('difficulty', $m->difficulty));
    }

    public function play(Request $request, Mission $mission): Response
    {
        $user = $request->user();
        abort_unless($this->availableQuery($user)->whereKey($mission->id)->exists(), 403);
        abort_if(($mission->type ?? 'quiz') !== 'quiz', 400);

        $pool = $this->bankQuery($mission)->inRandomOrder()->limit(max(1, (int) $mission->question_count))->get();
        abort_if($pool->isEmpty(), 404, 'برای این مأموریت هنوز سؤالی در بانک نیست.');

        $token = (string) Str::uuid();
        $questions = [];
        $key = [];
        foreach ($pool->values() as $i => $q) {
            // گزینه‌ها هر بار درهم می‌شوند تا حفظ‌کردنِ «جایِ پاسخ» بی‌فایده باشد
            $choices = collect($q->choices ?? [])->shuffle()->values();
            $correct = $choices->first(fn ($c) => ! empty($c['correct']));
            $questions[] = [
                'i' => $i, 'type' => $q->type, 'prompt' => $q->prompt,
                'choices' => $choices->map(fn ($c) => ['value' => $c['value'] ?? ''])->values(),
            ];
            $key[$i] = ['answer' => $correct['value'] ?? null, 'explanation' => $q->explanation];
        }
        $request->session()->put("mission.$token", ['mission_id' => $mission->id, 'key' => $key]);

        return Inertia::render('Student/MissionPlay', [
            'mission' => [
                'id' => $mission->id, 'title' => $mission->title, 'description' => $mission->description,
                'subject' => $mission->subject, 'xp_reward' => $mission->xp_reward,
                'pass_percent' => $mission->pass_percent ?? 60,
                'badge_name' => $mission->badge_name, 'badge_icon' => $mission->badge_icon,
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
        $correct = 0;
        $total = count($key);
        $review = [];
        foreach ($data['answers'] as $ans) {
            $i = $ans['i'];
            if (! isset($key[$i])) {
                continue;
            }
            $ok = (string) ($ans['value'] ?? '') === (string) $key[$i]['answer'];
            $correct += $ok ? 1 : 0;
            $review[] = [
                'i' => $i, 'ok' => $ok,
                'answer' => $key[$i]['answer'],
                'explanation' => $key[$i]['explanation'] ?? null,
            ];
        }

        $today = now()->toDateString();
        $already = MissionCompletion::where('mission_id', $mission->id)
            ->where('student_id', $user->id)->whereDate('play_date', $today)->exists();

        $pass = ($mission->pass_percent ?? 60) / 100;
        $passed = $total > 0 && ($correct / $total) >= $pass;
        $xpGain = 0;
        $badge = null;
        if (! $already) {
            // امتیازِ متناسب با درصدِ درست
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
            'xp' => $xpGain, 'already' => $already, 'passed' => $passed,
            'badge' => $badge, 'review' => $review,
        ]);
    }

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
