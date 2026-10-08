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
use App\Models\LearningObjective;
use App\Models\ObjectiveReview;
use App\Models\ReviewCompletion;
use App\Models\SmartExam;
use App\Models\SmartExamAttempt;
use App\Models\Worksheet;
use App\Models\WorksheetSubmission;
use App\Services\GamificationService;
use App\Services\LearningService;
use App\Services\MasteryService;
use App\Services\ReviewMissionBuilder;
use App\Support\Objectives;
use App\Support\Streak;
use App\Support\BankAccess;
use App\Support\MissionAccess;
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

    /** امتیازِ پایه‌ی «مرورِ امروز». */
    private const REVIEW_XP = 15;

    /** پاداش‌های رشد (تلاش و پیشرفت، نه فقط درستی). */
    private const XP_PERSONAL_BEST = 5;
    private const XP_RETRY_FIX = 1;
    private const XP_RETRY_FIX_MAX = 5;
    private const XP_LEVEL_UP = 10;

    private const GENERIC_HINT = 'صورتِ سؤال را یک بارِ دیگر آرام و با دقت بخوان 🔍';

    /* ═══════════════════════ دامنه‌ی دسترسی ═══════════════════════ */

    /** قاعده‌ی دسترسی در MissionAccess است تا فیدِ اعلان‌ها هم همان را ببیند. */
    private function availableQuery($user)
    {
        return MissionAccess::availableFor($user);
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
            'review' => $this->reviewState($user),
            'remedial' => rescue(fn () => app(\App\Services\RemediationService::class)->summary($user), ['enabled' => false], true),
            'mastery' => $this->masteryChips($user),
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

    /** روزهای پیاپیِ تمرین (مأموریت یا مرور) — سوختِ اصلیِ انگیزه. */
    private function streak($user): int
    {
        return Streak::days($user);
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

        return $this->startSession($request, 'quiz', $mission, $pool->map(fn ($q) => ['bank' => $q, 'objective_id' => null])->all(), [
            'id' => $mission->id, 'title' => $mission->title, 'description' => $mission->description,
            'subject' => $mission->subject, 'xp_reward' => $mission->xp_reward,
            'pass_percent' => $mission->pass_percent ?? 60,
            'badge_name' => $mission->badge_name, 'badge_icon' => $mission->badge_icon,
        ]);
    }

    /** صفحه‌ی «مرورِ اشتباه‌های من»: آماده‌ی امروز، در صف (با تاریخ)، جبران‌شده، و زمان‌بندیِ معلم. */
    public function reviewBoard(Request $request, \App\Services\RemediationService $rem): Response
    {
        // جدولِ مرور هنوز ساخته نشده (مایگریشنِ نصب اجرا نشده) → همین‌جا یک‌بار دوباره تلاش شود
        if (! \App\Services\RemediationService::ready()) {
            \App\Support\AutoMigrate::ensure(true);
            \App\Services\RemediationService::ready(true);
        }
        $board = rescue(fn () => $rem->studentBoard($request->user()), function ($e) {
            \App\Support\AutoMigrate::forget(); // اگر ستون/جدولی کم بود، درخواستِ بعد دوباره بررسی شود

            return ['enabled' => false, 'error' => true];
        }, true);
        \App\Services\RemediationService::continueBackfill();

        return Inertia::render('Student/Review', ['board' => $board]);
    }

    /** «جبرانِ اشتباه» — یادآوری‌های سررسیدِ همین دانش‌آموز (همان سؤال‌های اشتباه + مشابه). */
    public function remedialPlay(Request $request, \App\Services\RemediationService $rem): Response|RedirectResponse
    {
        $user = $request->user();
        $items = $rem->buildSession($user);
        if (! $items) {
            $s = $rem->summary($user);

            return redirect()->route('review')->with('flash', ($s['open'] ?? 0) > 0 && $s['next']
                ? "مرورِ اشتباه‌های امروز را انجام دادی ✅ نوبتِ بعدی: {$s['next']}"
                : 'فعلاً مرورِ اشتباهی نداری — آفرین! 🌟');
        }

        return $this->startSession($request, 'remedial', null, $items, [
            'id' => null, 'title' => 'مرورِ اشتباه‌های من',
            'description' => 'سؤال‌هایی که اشتباه زده بودی (با گزینه‌های جابه‌جا) و چند سؤالِ شبیهشان — درستشان کن و بخشی از امتیازت را پس بگیر!',
            'subject' => null, 'xp_reward' => 0, 'pass_percent' => \App\Models\RemediationPlan::forStudent($user)['pass'],
            'badge_name' => null, 'badge_icon' => '🔁',
        ]);
    }

    /** «مرورِ امروز» — سؤال‌های خودکار از هدف‌های جاری و سررسیدِ همین دانش‌آموز. */
    public function reviewPlay(Request $request, ReviewMissionBuilder $builder): Response|RedirectResponse
    {
        $user = $request->user();
        $items = $builder->build($user);
        if (count($items) < ReviewMissionBuilder::MIN) {
            return redirect()->route('missions')->with('flash', 'هنوز سؤالِ کافی برای مرور نیست — اول چند مأموریت انجام بده 🙂');
        }

        return $this->startSession($request, 'review', null, $items, [
            'id' => null, 'title' => 'مرورِ فاصله‌دار',
            'description' => 'این‌ها را چند روز پیش یاد گرفتی — مرورشان کن تا همیشه یادت بماند!',
            'subject' => null, 'xp_reward' => self::REVIEW_XP, 'pass_percent' => 0,
            'badge_name' => null, 'badge_icon' => '🔁',
        ]);
    }

    /**
     * جلسه‌ی پرسش: کلیدِ پاسخ، راهنما و وضعیتِ هر سؤال فقط در session سرور می‌ماند.
     *
     * @param  array<int,array{bank:\App\Models\SmartQuestionBank,objective_id:?int}>  $items
     */
    private function startSession(Request $request, string $kind, ?Mission $mission, array $items, array $meta): Response
    {
        $token = (string) Str::uuid();
        $questions = [];
        $state = [];
        foreach (array_values($items) as $i => $it) {
            $q = $it['bank'];
            // گزینه‌ها هر بار درهم می‌شوند تا حفظ‌کردنِ «جایِ پاسخ» بی‌فایده باشد
            $choices = collect($q->choices ?? [])->shuffle()->values();
            $correct = $choices->first(fn ($c) => ! empty($c['correct']));
            $oid = LearningService::ready() ? ($it['objective_id'] ?: ($q->objective_id ?: Objectives::idFor($q))) : null;
            $questions[] = [
                'i' => $i, 'type' => $q->type, 'prompt' => $q->prompt,
                'choices' => $choices->map(fn ($c) => ['value' => $c['value'] ?? ''])->values(),
                'objective' => $oid,
            ];
            $state[$i] = [
                'answer' => $correct['value'] ?? null, 'explanation' => $q->explanation, 'hint' => $q->hint,
                'choices' => $choices->pluck('value')->all(), 'bank_id' => $q->id, 'objective_id' => $oid,
                'tries' => 0, 'ok' => null, 'hinted' => false, 'picked' => [],
                'rem_id' => $it['rem_id'] ?? null,
            ];
        }
        $request->session()->put("mission.$token", ['kind' => $kind, 'mission_id' => $mission?->id, 'items' => $state]);

        $labels = LearningService::ready()
            ? LearningObjective::whereIn('id', array_filter(array_column($questions, 'objective')))->pluck('label', 'id')
            : collect();
        foreach ($questions as &$qq) {
            $qq['objective'] = $labels[$qq['objective']] ?? null;
        }
        unset($qq);

        return Inertia::render('Student/MissionPlay', [
            'mission' => $meta + ['kind' => $kind],
            'token' => $token,
            'questions' => $questions,
        ]);
    }

    private function sessionItem(Request $request, string $token, int $i): array
    {
        $sess = $request->session()->get("mission.$token");
        abort_if(! $sess, 419, 'جلسه‌ی مأموریت منقضی شده');
        abort_unless(isset($sess['items'][$i]), 404);

        return $sess;
    }

    private static function finished(array $item): bool
    {
        return $item['ok'] === true || $item['tries'] >= 2;
    }

    /**
     * بررسیِ یک پاسخ — بازخوردِ همان لحظه. هر سؤال دو فرصت دارد؛ پاسخِ درست و
     * توضیح فقط بعد از جوابِ درست یا تلاشِ دوم فرستاده می‌شود.
     */
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string'], 'i' => ['required', 'integer'], 'value' => ['nullable']]);
        $sess = $this->sessionItem($request, $data['token'], $data['i']);
        $item = &$sess['items'][$data['i']];

        if (! self::finished($item)) {
            $item['tries']++;
            $item['picked'][] = (string) ($data['value'] ?? '');
            $item['ok'] = (string) ($data['value'] ?? '') === (string) $item['answer'];
            $request->session()->put("mission.{$data['token']}", $sess);
        }

        $done = self::finished($item);

        return response()->json([
            'ok' => (bool) $item['ok'], 'tries' => $item['tries'], 'done' => $done,
            'retry' => ! $done,
        ] + ($done ? ['answer' => $item['answer'], 'explanation' => $item['explanation']] : []));
    }

    /** راهنما: متنِ راهنمای سؤال + حذفِ یک گزینه‌ی غلط (اگر بیش از دو گزینه باشد). یک‌بار برای هر سؤال. */
    public function hint(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string'], 'i' => ['required', 'integer']]);
        $sess = $this->sessionItem($request, $data['token'], $data['i']);
        $item = &$sess['items'][$data['i']];
        abort_if(self::finished($item), 409, 'این سؤال تمام شده است.');

        if (! $item['hinted']) {
            $item['hinted'] = true;
            $wrong = array_values(array_filter($item['choices'], fn ($c) => (string) $c !== (string) $item['answer'] && ! in_array((string) $c, $item['picked'], true)));
            $item['removed'] = count($item['choices']) > 2 && $wrong ? $wrong[array_rand($wrong)] : null;
            $request->session()->put("mission.{$data['token']}", $sess);
        }

        return response()->json([
            'hint' => $item['hint'] ?: (empty($item['removed']) ? self::GENERIC_HINT : null),
            'remove' => $item['removed'] ?? null,
        ]);
    }

    /**
     * پایانِ جلسه. نمره فقط از وضعیتِ سمتِ سرور حساب می‌شود (پاسخ‌های ارسالیِ مرورگر نادیده گرفته می‌شوند):
     * درست در بارِ اول = کامل، در تلاشِ دوم = نیم، با راهنما = سه‌چهارم.
     */
    public function submit(Request $request, GamificationService $game, LearningService $learning): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string']]);
        $sess = $request->session()->pull("mission.{$data['token']}");
        abort_if(! $sess || empty($sess['items']), 419, 'جلسه‌ی مأموریت منقضی شده');

        $user = $request->user();
        $kind = $sess['kind'] ?? 'quiz';
        if ($kind === 'remedial') {
            return $this->submitRemedial($user, $sess['items'], $game);
        }
        $mission = $kind === 'quiz' ? Mission::find($sess['mission_id']) : null;
        abort_if($kind === 'quiz' && ! $mission, 404);

        $items = $sess['items'];
        $total = count($items);
        $correct = 0;
        $credit = 0.0;
        $retryFixes = 0;
        $events = [];
        $review = [];
        foreach ($items as $i => $it) {
            $ok = $it['ok'] === true;
            $firstTry = $ok && $it['tries'] === 1;
            $correct += $ok ? 1 : 0;
            $credit += LearningService::credit($ok, $firstTry, (bool) $it['hinted']);
            $retryFixes += ($ok && $it['tries'] === 2) ? 1 : 0;
            if ($it['tries'] > 0) {
                $events[] = ['objective_id' => $it['objective_id'], 'bank_id' => $it['bank_id'], 'correct' => $ok, 'first_try' => $firstTry, 'hinted' => (bool) $it['hinted']];
            }
            $review[] = [
                'i' => $i, 'ok' => $ok, 'tries' => $it['tries'], 'hinted' => (bool) $it['hinted'],
                'answer' => $it['answer'], 'explanation' => $it['explanation'] ?? null,
            ];
        }
        $ratio = $total ? $credit / $total : 0;

        $today = now()->toDateString();
        $already = $kind === 'quiz'
            ? MissionCompletion::where('mission_id', $mission->id)->where('student_id', $user->id)->whereDate('play_date', $today)->exists()
            : ReviewCompletion::where('student_id', $user->id)->whereDate('play_date', $today)->exists();

        $passed = $kind === 'review' ? true : ($total > 0 && $ratio >= ($mission->pass_percent ?? 60) / 100);
        $xpGain = 0;
        $badge = null;
        $growth = [];
        $badges = [];

        if (! $already) {
            $base = $kind === 'quiz' ? (int) $mission->xp_reward : self::REVIEW_XP;
            $label = $kind === 'quiz' ? '🎯 مأموریت روزانه — ' . $mission->title : '🔁 مرورِ امروز';
            $xpGain = (int) round($base * $ratio);

            // رکوردِ شخصی: بهتر از بهترین نتیجه‌ی روزهای قبلِ همین مأموریت
            $prevBest = null;
            if ($kind === 'quiz') {
                $prevBest = MissionCompletion::where('mission_id', $mission->id)->where('student_id', $user->id)
                    ->whereDate('play_date', '<', $today)->where('total', '>', 0)->get()
                    ->sortByDesc(fn ($c) => $c->score / $c->total)->first();
            }

            if ($xpGain > 0) {
                $game->award($user, $xpGain, $label, $mission?->teacher, $kind === 'quiz' ? Mission::class : ReviewCompletion::class, $mission?->id);
            }
            if ($kind === 'quiz') {
                MissionCompletion::create([
                    'mission_id' => $mission->id, 'student_id' => $user->id, 'play_date' => $today,
                    'score' => $correct, 'total' => $total, 'xp_awarded' => $xpGain,
                ]);
                if ($passed && $mission->badge_name) {
                    $badge = $this->awardBadge($user, $mission);
                }
            } else {
                ReviewCompletion::create([
                    'student_id' => $user->id, 'play_date' => $today,
                    'score' => $correct, 'total' => $total, 'xp_awarded' => $xpGain,
                ]);
            }

            // شواهدِ یادگیری فقط بارِ اولِ روز (تکرارِ بلافاصله بعد از دیدنِ پاسخ‌ها شاهدِ واقعی نیست)
            $learned = $learning->record($user, $events, $kind === 'quiz' ? 'mission' : 'review', $mission?->id);

            // یادآوریِ جبرانی برای سؤال‌هایی که در نهایت اشتباه ماند (فقط مأموریت، نه خودِ مرور)
            if ($kind === 'quiz') {
                $remedial = (int) rescue(function () use ($items, $mission, $user, $total) {
                    $completion = MissionCompletion::where('mission_id', $mission->id)->where('student_id', $user->id)
                        ->whereDate('play_date', now()->toDateString())->latest('id')->first();
                    $rows = [];
                    $banks = \App\Models\SmartQuestionBank::withoutGlobalScopes()->whereIn('id', array_filter(array_column($items, 'bank_id')))->get()->keyBy('id');
                    foreach ($items as $it) {
                        if ($it['ok'] === false && ! empty($it['bank_id']) && isset($banks[$it['bank_id']])) {
                            $rows[] = ['q_key' => 'b' . $it['bank_id'], 'bank_id' => $it['bank_id'], 'objective_id' => $it['objective_id'] ?? null,
                                'question' => \App\Services\RemediationService::snapshot($banks[$it['bank_id']]),
                                'lost_xp' => (int) round(($mission->xp_reward ?: 0) / max(1, $total))];
                        }
                    }

                    return app(\App\Services\RemediationService::class)->create($user, 'mission', $completion?->id ?? $mission->id, $mission->id, $mission->title, $mission->teacher_id, $rows);
                }, 0, true);
            }

            if ($prevBest && $total > 0 && $correct / $total > $prevBest->score / $prevBest->total) {
                $growth[] = ['icon' => '🚀', 'title' => 'رکوردِ شخصیِ تازه!',
                    'sub' => 'دفعه‌ی قبل ' . Jalali::fa((string) $prevBest->score) . ' از ' . Jalali::fa((string) $prevBest->total)
                        . ' — امروز ' . Jalali::fa((string) $correct) . ' از ' . Jalali::fa((string) $total),
                    'xp' => self::XP_PERSONAL_BEST];
            }
            if ($retryFixes > 0) {
                $growth[] = ['icon' => '🔁', 'title' => Jalali::fa((string) $retryFixes) . ' اشتباه را خودت درست کردی',
                    'sub' => 'با تلاشِ دوم', 'xp' => min(self::XP_RETRY_FIX_MAX, $retryFixes * self::XP_RETRY_FIX)];
            }
            foreach ($learned['band_ups'] as $up) {
                $growth[] = ['icon' => '⬆️', 'title' => $up['label'] . ' یک سطح بالا رفت!',
                    'sub' => null, 'xp' => self::XP_LEVEL_UP,
                    'from' => MasteryService::LEVELS[array_search($up['from'], array_column(MasteryService::LEVELS, 'key'))]['label'] ?? null,
                    'to' => MasteryService::LEVELS[array_search($up['to'], array_column(MasteryService::LEVELS, 'key'))]['label'] ?? null];
            }
            foreach ($growth as $g) {
                $game->award($user, $g['xp'], '🌱 پیشرفت — ' . $g['title'], null, 'growth', $mission?->id);
            }

            $badges = $game->checkBadges($user);
        }

        return response()->json([
            'kind' => $kind,
            'correct' => $correct, 'total' => $total, 'percent' => (int) round($ratio * 100),
            'xp' => $xpGain, 'xp_total' => $xpGain + array_sum(array_column($growth, 'xp')),
            'already' => $already, 'passed' => $passed,
            'badge' => $badge, 'badges' => $badges, 'growth' => $growth,
            'streak' => $this->streak($user),
            'review' => $review,
            'remedial_made' => $remedial ?? 0,
        ]);
    }

    /** پایانِ جلسه‌ی «جبرانِ اشتباه». */
    private function submitRemedial($user, array $items, GamificationService $game): JsonResponse
    {
        $total = count($items);
        $correct = 0;
        $credit = 0.0;
        $review = [];
        foreach ($items as $i => $it) {
            $ok = $it['ok'] === true;
            $correct += $ok ? 1 : 0;
            $credit += LearningService::credit($ok, $ok && $it['tries'] === 1, (bool) $it['hinted']);
            $review[] = ['i' => $i, 'ok' => $ok, 'tries' => $it['tries'], 'hinted' => (bool) $it['hinted'],
                'answer' => $it['answer'], 'explanation' => $it['explanation'] ?? null];
        }
        $res = app(\App\Services\RemediationService::class)->settle($user, $items, $game);
        $growth = [];
        foreach ($res['band_ups'] as $up) {
            $growth[] = ['icon' => '⬆️', 'title' => $up['label'] . ' یک سطح بالا رفت!', 'sub' => null, 'xp' => self::XP_LEVEL_UP,
                'from' => MasteryService::LEVELS[array_search($up['from'], array_column(MasteryService::LEVELS, 'key'))]['label'] ?? null,
                'to' => MasteryService::LEVELS[array_search($up['to'], array_column(MasteryService::LEVELS, 'key'))]['label'] ?? null];
        }
        foreach ($growth as $g) {
            $game->award($user, $g['xp'], '🌱 پیشرفت — ' . $g['title'], null, 'growth', null);
        }
        $passed = collect($res['rows'])->where('passed', true)->count();

        return response()->json([
            'kind' => 'remedial',
            'correct' => $correct, 'total' => $total, 'percent' => $total ? (int) round($credit / $total * 100) : 0,
            'xp' => $res['xp'], 'xp_total' => $res['xp'] + array_sum(array_column($growth, 'xp')),
            'already' => false, 'passed' => $passed > 0 && $passed === count($res['rows']),
            'badge' => null, 'badges' => [], 'growth' => $growth, 'streak' => $this->streak($user),
            'review' => $review, 'remedial' => $res['rows'],
        ]);
    }

    /** وضعیتِ کارتِ «مرورِ امروز» در تخته‌ی مأموریت. */
    private function reviewState($user): array
    {
        $builder = app(ReviewMissionBuilder::class);
        $done = $builder->isDoneToday($user);
        $items = $done ? [] : $builder->build($user);

        return [
            'available' => ! $done && count($items) >= ReviewMissionBuilder::MIN,
            'done_today' => $done,
            'count' => count($items),
            'objectives' => count(array_unique(array_column($items, 'objective_id'))),
            'xp' => self::REVIEW_XP,
        ];
    }

    /** سطحِ دانش‌آموز در مبحث‌ها (از موتورِ تسلط) + این‌که موعدِ مرورش رسیده یا نه. */
    private function masteryChips($user): array
    {
        if (! LearningService::ready()) {
            return [];
        }
        $due = ObjectiveReview::withoutGlobalScopes()->where('student_id', $user->id)
            ->whereDate('due_at', '<=', now()->toDateString())->with('objective:id,subject,label')->get()
            ->mapWithKeys(fn ($r) => [LearningService::topicKey($r->objective?->subject, $r->objective?->label) => true]);

        $rows = [];
        foreach (app(MasteryService::class)->forStudent($user->id)['subjects'] ?? [] as $s) {
            foreach ($s['topics'] ?? [] as $t) {
                $rows[] = [
                    'subject' => $s['name'], 'topic' => $t['name'], 'mastery' => $t['mastery'],
                    'level' => $t['level'], 'n' => $t['n'],
                    'due' => isset($due[LearningService::topicKey($s['name'], $t['name'])]),
                ];
            }
        }
        // ضعیف‌ترها و سررسیدها بالاتر — همان‌هایی که باید تمرین شوند
        usort($rows, fn ($a, $b) => [$b['due'], $a['mastery'] ?? 101] <=> [$a['due'], $b['mastery'] ?? 101]);

        return array_slice($rows, 0, 6);
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
