<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\Remediation;
use App\Models\RemediationPlan;
use App\Models\SmartQuestionBank;
use App\Models\User;
use App\Models\XpEntry;
use App\Support\BankAccess;
use App\Support\Jalali;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «یادآوریِ جبرانی» — بعد از هر آزمون، بازی یا مأموریت، برای هر سؤالی که دانش‌آموز
 * در نهایت اشتباه زده، به‌طورِ خودکار و فقط برای خودِ او ساخته می‌شود.
 *
 * - زمان‌بندی را معلم برای هر کلاس اعلام می‌کند (RemediationPlan)؛ پیش‌فرض: ۳ نوبت —
 *   همان روز، ۲ روز بعد، ۵ روز بعد از آن (فاصله‌ی رو به افزایش).
 * - هر نوبت: همان سؤال (با گزینه‌های درهم) + تا ۲ سؤالِ مشابه از همان فصل (از بانک؛ اگر
 *   نبود و هوش مصنوعی فعال بود، نسخه‌ی مشابه با عدد/پاسخِ دیگر ساخته می‌شود).
 * - قبولیِ نوبت: دستِ‌کم ۶۰٪ (درستِ بارِ اول = کامل، با راهنما = ¾، تلاشِ دوم = ½).
 *   نوبتِ ردشده فردا دوباره می‌آید.
 * - امتیاز: حداکثر ۵۰٪ امتیازِ از دست‌رفته‌ی همان سؤال، در سه قسط (هر نوبتِ قبول یک قسط)،
 *   پس کسی که از اول درست زده همیشه جلوتر می‌ماند.
 * - همه‌ی پاسخ‌ها شاهدِ تسلط و مرورِ فاصله‌دارِ همان فصل‌اند.
 */
class RemediationService
{
    public const SHARE = 0.5;            // سقفِ جبران از امتیازِ از دست رفته
    public const STEPS = 3;
    public const GAP_AFTER_PASS = [1 => 2, 2 => 5]; // روزِ نوبتِ بعد پس از قبولیِ نوبتِ ۱ و ۲
    public const PASS = 0.6;
    public const PER_SESSION = 4;        // یادآوری‌های هر جلسه (حدودِ ۸ تا ۱۲ سؤال)
    public const SIMILAR = 2;
    public const AI_PER_BATCH = 3;       // سقفِ درخواستِ هوش مصنوعی برای هر آزمون/بازی

    public function __construct(private LearningService $learning) {}

    private static ?bool $ready = null;

    public static function ready(bool $fresh = false): bool
    {
        if ($fresh) {
            self::$ready = null;
        }

        return self::$ready ??= (bool) rescue(fn () => Schema::hasTable('remediations'), false, false);
    }

    /* ═══════════════ ساخت ═══════════════ */

    /**
     * @param  array<int,array{q_key:string,bank_id:?int,objective_id:?int,question:array,lost_xp:int}>  $items
     * @return int تعدادِ یادآوریِ تازه
     */
    public function create(User $student, string $source, int $sourceId, ?int $ref, string $title, ?int $teacherId, array $items, ?string $dueOn = null, bool $notify = true, ?int $capXp = null): int
    {
        if (! self::ready() || ! $items) {
            return 0;
        }
        // زمان‌بندیِ اعلام‌شده‌ی معلمِ کلاس
        $plan = RemediationPlan::forStudent($student, $teacherId);
        if ($source !== 'teacher' && (! $plan['enabled'] || empty($plan['sources'][$source] ?? true))) {
            return 0;
        }
        $first = now()->addDays($source === 'teacher' ? 0 : $plan['first_delay'])->toDateString();
        $dueOn = $dueOn && $dueOn > $first ? $dueOn : $first;
        $share = $plan['share'] / 100;
        $snap = ['gaps' => $plan['gaps'], 'retry' => $plan['retry'], 'pass' => $plan['pass']];
        $open = Remediation::where('student_id', $student->id)->where('status', 'open')->pluck('q_key')->flip();
        $labels = \App\Models\LearningObjective::whereIn('id', array_filter(array_column($items, 'objective_id')))->pluck('label', 'id');
        $made = collect();
        foreach ($items as $it) {
            // همین سؤال (یا همین سؤالِ بانک) هنوز یادآوریِ باز دارد → دوباره ساخته نمی‌شود
            if (isset($open[$it['q_key']])) {
                continue;
            }
            // سؤالی که نه خودش قابلِ تمرین است و نه فصلی برای سؤالِ مشابه دارد، به کار نمی‌آید
            if (! self::playable($it['question']) && empty($it['objective_id'])) {
                continue;
            }
            $lost = max(0, (int) $it['lost_xp']);
            $rem = Remediation::firstOrCreate(
                ['student_id' => $student->id, 'source' => $source, 'source_id' => $sourceId, 'q_key' => $it['q_key']],
                [
                    'school_id' => $student->school_id, 'teacher_id' => $teacherId, 'source_ref' => $ref,
                    'source_title' => mb_substr($title, 0, 200), 'bank_id' => $it['bank_id'] ?? null,
                    'objective_id' => $it['objective_id'] ?? null,
                    'objective_label' => $it['objective_id'] ? ($labels[$it['objective_id']] ?? null) : null,
                    'question' => $it['question'], 'lost_xp' => $lost,
                    'cap_xp' => $capXp ?? ($lost > 0 && $share > 0 ? max(1, (int) floor($lost * $share)) : 0),
                    'steps' => $plan['rounds'], 'due_on' => $dueOn, 'status' => 'open', 'plan' => $snap,
                ]
            );
            if ($rem->wasRecentlyCreated) {
                $made->push($rem);
                $open[$it['q_key']] = true;
            }
        }
        if ($made->isEmpty()) {
            return 0;
        }

        $cap = $made->sum('cap_xp');
        // فرستنده‌ی اعلان الزامی است: معلمِ همان آزمون/بازی، وگرنه معلمِ کلاسِ دانش‌آموز
        $teacherId ??= $student->classrooms()->value('classrooms.teacher_id');
        if ($notify && $teacherId) rescue(function () use ($student, $teacherId, $made, $title, $cap) {
            $ann = Announcement::create([
                'school_id' => $student->school_id, 'sender_id' => $teacherId, 'audience' => 'personal',
                'title' => '🔁 مرورِ اشتباه‌ها — ' . mb_substr($title, 0, 120),
                'body' => 'برای ' . Jalali::fa((string) $made->count()) . ' موردی که اشتباه زدی، مرورِ جبرانی '
                    . ($made->first()->due_on && $made->first()->due_on->isFuture() ? 'از ' . Jalali::format($made->first()->due_on) . ' آماده می‌شود' : 'آماده است')
                    . ($cap > 0 ? '؛ با انجامش تا ' . Jalali::fa((string) $cap) . ' امتیاز را پس می‌گیری.' : '.'),
                'link' => '/review',
            ]);
            $ann->recipients()->sync([$student->id]);
        }, null, true);

        // سؤالِ مشابه کم است؟ بعد از پاسخ (بی‌آنکه دانش‌آموز منتظر بماند) با هوش مصنوعی ساخته می‌شود
        $this->queueVariants($student, $made);

        return $made->count();
    }

    public static function playable(?array $q): bool
    {
        if (! $q || ! in_array($q['type'] ?? null, ['mc', 'tf'], true)) {
            return false;
        }
        $choices = collect($q['choices'] ?? [])->filter(fn ($c) => trim((string) ($c['value'] ?? '')) !== '');

        return $choices->count() >= 2 && $choices->contains(fn ($c) => ! empty($c['correct']));
    }

    /** نسخه‌ی سؤال برای ذخیره در یادآوری (پاسخ فقط در سرور می‌ماند). */
    public static function snapshot(object $q): array
    {
        return [
            'type' => $q->type === 'short' ? 'blank' : $q->type,
            'prompt' => (string) $q->prompt,
            'choices' => collect($q->choices ?? [])->map(fn ($c) => ['value' => (string) ($c['value'] ?? ''), 'correct' => ! empty($c['correct'])])->values()->all(),
            'explanation' => $q->explanation ?? null,
            'hint' => $q->hint ?? ($q->hint1 ?? null),
        ];
    }

    /* ═══════════════ دانش‌آموز ═══════════════ */

    public function due(User $student): Collection
    {
        if (! self::ready()) {
            return collect();
        }

        return Remediation::where('student_id', $student->id)->where('status', 'open')
            ->whereDate('due_on', '<=', now()->toDateString())
            ->orderBy('due_on')->orderBy('id')->get();
    }

    /** وضعیتِ کارتِ «جبرانِ اشتباه» برای تخته‌ی مأموریت. */
    public function summary(User $student): array
    {
        if (! self::ready()) {
            return ['enabled' => false];
        }
        $all = Remediation::where('student_id', $student->id)->where('created_at', '>=', now()->subDays(60))->get();
        $open = $all->where('status', 'open');
        $due = $open->filter(fn ($r) => $r->due_on && $r->due_on->lte(now()->endOfDay()));
        $next = $open->reject(fn ($r) => $due->contains($r))->sortBy('due_on')->first();

        return [
            'enabled' => true,
            'due' => $due->count(),
            'open' => $open->count(),
            'done' => $all->where('status', 'done')->count(),
            'xp_left' => (int) $open->sum(fn ($r) => max(0, $r->cap_xp - $r->recovered_xp)),
            'xp_recovered' => (int) $all->sum('recovered_xp'),
            'next' => $next?->due_on ? Jalali::format($next->due_on) : null,
            'chapters' => $due->pluck('objective_label')->filter()->unique()->take(3)->values(),
        ];
    }

    /**
     * سؤال‌های یک جلسه‌ی جبرانی برای موتورِ پرسشِ مأموریت.
     *
     * @return array<int,array{bank:SmartQuestionBank,objective_id:?int,rem_id:int}>
     */
    public function buildSession(User $student): array
    {
        $plan = RemediationPlan::forStudent($student);
        $rems = $this->due($student)->take($plan['per_session']);
        if ($rems->isEmpty()) {
            return [];
        }
        $teacher = $student->classrooms()->with('teacher')->get()->pluck('teacher')->filter()->first();
        $used = $rems->pluck('bank_id')->filter()->all();
        // سؤالی که امروز در بارِ اول درست جواب داده، امروز دوباره نمی‌آید
        $skip = \App\Models\PracticeAnswer::withoutGlobalScopes()->where('student_id', $student->id)
            ->where('created_at', '>=', now()->startOfDay())->where('correct', true)->where('first_try', true)
            ->whereNotNull('bank_id')->pluck('bank_id')->all();

        $items = [];
        $needAi = collect();
        foreach ($rems as $rem) {
            $mine = [];
            $orig = $this->originalQuestion($rem);
            if ($orig) {
                $mine[] = $orig;
            }
            // مرورِ بدونِ سؤالِ اصلی (نمره‌ی ضعیفِ معلم، مرورِ معلم، پاسخِ پنهان) → سؤال‌های بیشتری از همان فصل
            $similar = $this->similar($rem, $teacher, array_merge($used, $skip), $orig ? $plan['similar'] : max(3, $plan['similar'] + 2));
            foreach ($similar as $q) {
                $mine[] = $q;
                $used[] = $q->id;
            }
            if (! $similar->count()) {
                $needAi->push($rem);
            }
            if (! $mine) {
                // نه خودِ سؤال قابلِ تمرین است، نه سؤالِ مشابهی هست: اگر هوش مصنوعی هم کمکی نمی‌کند، بسته می‌شود
                if ($rem->ai_tried || ! self::aiOn()) {
                    $rem->update(['status' => 'closed', 'due_on' => null]);
                }
                continue;
            }
            foreach ($mine as $q) {
                $items[] = ['bank' => $q, 'objective_id' => $rem->objective_id, 'rem_id' => $rem->id];
            }
        }
        if ($needAi->isNotEmpty()) {
            $this->queueVariants($student, $needAi);
        }
        // ترتیبِ سؤال‌ها هم درهم (گزینه‌ها در موتورِ پرسش درهم می‌شوند)
        shuffle($items);

        return $items;
    }

    private function originalQuestion(Remediation $rem): ?SmartQuestionBank
    {
        // معلم پاسخِ آزمون را پنهان کرده و آزمون هنوز باز است → فقط سؤال‌های مشابه
        if (! empty($rem->question['no_original'])) {
            return null;
        }
        if ($rem->bank_id) {
            $b = SmartQuestionBank::withoutGlobalScopes()->find($rem->bank_id);
            if ($b && self::playable(['type' => $b->type, 'choices' => $b->choices])) {
                return $b;
            }
        }
        if (! self::playable($rem->question)) {
            return null;
        }
        // سؤالِ بدونِ بانک: یک نمونه‌ی ذخیره‌نشده فقط برای همین جلسه
        $q = new SmartQuestionBank();
        $q->forceFill([
            'type' => $rem->question['type'], 'prompt' => $rem->question['prompt'], 'choices' => $rem->question['choices'],
            'explanation' => $rem->question['explanation'] ?? null, 'hint' => $rem->question['hint'] ?? null,
            'objective_id' => $rem->objective_id,
        ]);

        return $q;
    }

    private function similar(Remediation $rem, ?User $teacher, array $exclude, int $n): Collection
    {
        if (! $rem->objective_id || ! $teacher || ! LearningService::bankReady()) {
            return collect();
        }

        return BankAccess::visibleQuery($teacher)
            ->where('objective_id', $rem->objective_id)
            ->whereIn('type', ['mc', 'tf'])
            ->where(fn ($q) => $q->whereNull('approval')->orWhere('approval', 'approved'))
            ->when($exclude, fn ($q) => $q->whereNotIn('id', array_values(array_unique($exclude))))
            ->inRandomOrder()->limit($n * 3)->get()
            ->filter(fn ($q) => self::playable(['type' => $q->type, 'choices' => $q->choices]))
            ->take($n)->values();
    }

    /**
     * پایانِ جلسه: هر یادآوری جداگانه داوری می‌شود.
     *
     * @param  array<int,array>  $items  وضعیتِ سمتِ سرورِ سؤال‌ها (با rem_id)
     */
    public function settle(User $student, array $items, GamificationService $game): array
    {
        $groups = collect($items)->filter(fn ($it) => ! empty($it['rem_id']))->groupBy('rem_id');
        $rems = Remediation::where('student_id', $student->id)->whereIn('id', $groups->keys())->get()->keyBy('id');
        $rows = [];
        $xpTotal = 0;
        $events = [];

        foreach ($groups as $remId => $list) {
            $rem = $rems[$remId] ?? null;
            if (! $rem || $rem->status !== 'open') {
                continue;
            }
            $played = $list->filter(fn ($it) => ($it['tries'] ?? 0) > 0);
            if ($played->isEmpty()) {
                continue;
            }
            $credit = $played->avg(fn ($it) => LearningService::credit($it['ok'] === true, $it['ok'] === true && $it['tries'] === 1, (bool) $it['hinted']));
            $rplan = RemediationPlan::normalize(($rem->plan ?? []) + ['rounds' => $rem->steps]);
            $passed = $credit >= $rplan['pass'] / 100;
            $xp = 0;
            DB::transaction(function () use ($rem, $passed, $credit, $rplan, &$xp) {
                $rem->tries++;
                $rem->last_played_at = now();
                $rem->last_credit = round($credit, 2);
                if ($passed) {
                    $rem->step++;
                    $left = max(0, $rem->cap_xp - $rem->recovered_xp);
                    $xp = $rem->step >= $rem->steps ? $left : min($left, (int) ceil($rem->cap_xp / max(1, $rem->steps)));
                    $rem->recovered_xp += $xp;
                    if ($rem->step >= $rem->steps) {
                        $rem->status = 'done';
                        $rem->due_on = null;
                    } else {
                        $rem->due_on = now()->addDays($rplan['gaps'][$rem->step - 1] ?? 3)->toDateString();
                    }
                } else {
                    $rem->due_on = now()->addDays($rplan['retry'])->toDateString(); // نوبتِ ردشده دوباره
                }
                $rem->save();
            });
            if ($xp > 0) {
                $game->award($student, $xp, '🔁 جبرانِ اشتباه — ' . ($rem->source_title ?: 'تمرین'), null, Remediation::class, $rem->id);
                $xpTotal += $xp;
            }
            foreach ($played as $it) {
                if (! empty($it['objective_id'])) {
                    $events[] = ['objective_id' => $it['objective_id'], 'bank_id' => $it['bank_id'] ?? null,
                        'correct' => $it['ok'] === true, 'first_try' => $it['ok'] === true && $it['tries'] === 1, 'hinted' => (bool) $it['hinted']];
                }
            }
            $rows[] = [
                'title' => $rem->source_title, 'chapter' => $rem->objective_label,
                'passed' => $passed, 'percent' => (int) round($credit * 100),
                'step' => $rem->step, 'steps' => $rem->steps, 'done' => $rem->status === 'done',
                'xp' => $xp, 'recovered' => $rem->recovered_xp, 'cap' => $rem->cap_xp,
                'next' => $rem->due_on ? Jalali::format($rem->due_on) : null,
            ];
        }
        $learned = $events ? $this->learning->record($student, $events, 'remedial') : ['band_ups' => []];

        return ['rows' => $rows, 'xp' => $xpTotal, 'band_ups' => $learned['band_ups'] ?? []];
    }

    /* ═══════════════ از سمتِ معلم ═══════════════ */

    /** نمره‌ی ضعیفِ معلم در یک فصل → مرورِ فردی با سؤال‌های همان فصل. */
    public function fromGrade(\App\Models\GradeColumn $col, User $student, float $fraction, int $objectiveId): int
    {
        $type = $col->score_type ?: $col->type;
        $max = $type === 'numeric' ? 20 : max(GamificationService::GRADE_XP[$type] ?? [0]);
        $lost = (int) max(0, round($max * (1 - $fraction)));

        return $this->create($student, 'grade', $col->id, $col->id, '📔 ' . ($col->title ?: 'نمره‌ی کلاسی'), $col->teacher_id, [[
            'q_key' => 'gc' . $col->id, 'bank_id' => null, 'objective_id' => $objectiveId,
            'question' => ['type' => 'none', 'prompt' => 'نمره‌ی «' . ($col->title ?: 'کلاسی') . '»', 'choices' => [], 'no_original' => true],
            'lost_xp' => $lost,
        ]]);
    }

    /**
     * معلم خودش برای یک یا چند دانش‌آموز در یک فصل مرور می‌فرستد.
     *
     * @param  array<int>  $studentIds
     */
    public function teacherAssign(User $teacher, array $studentIds, int $objectiveId, string $label, int $capXp = 6): int
    {
        $n = 0;
        $stamp = (int) now()->format('ymdHis');
        foreach (User::whereIn('id', $studentIds)->get() as $st) {
            $n += $this->create($st, 'teacher', $stamp, $objectiveId, '👩‍🏫 مرورِ معلم — ' . $label, $teacher->id, [[
                'q_key' => 'o' . $objectiveId, 'bank_id' => null, 'objective_id' => $objectiveId,
                'question' => ['type' => 'none', 'prompt' => 'مرورِ فصلِ «' . $label . '»', 'choices' => [], 'no_original' => true],
                'lost_xp' => 0,
            ]], null, true, $capXp);
        }

        return $n;
    }

    /** چند سؤالِ قابلِ تمرین در بانک برای این فصل هست؟ (برای هشدار به معلم) */
    public static function bankCount(User $teacher, int $objectiveId): int
    {
        if (! LearningService::bankReady()) {
            return 0;
        }

        return BankAccess::visibleQuery($teacher)->where('objective_id', $objectiveId)->whereIn('type', ['mc', 'tf'])
            ->where(fn ($q) => $q->whereNull('approval')->orWhere('approval', 'approved'))->count();
    }

    /* ═══════════════ اشتباه‌های گذشته ═══════════════ */

    /**
     * یک‌بار پس از نصب: اشتباه‌های آزمون و بازیِ روزهای اخیر هم مرورِ جبرانی می‌گیرند
     * (تا هسته‌ی تازه از همان روزِ اول برای همه‌ی دانش‌آموزان کار کند).
     *
     * روی هاستِ واقعی داده زیاد است و زمانِ هر درخواست محدود؛ پس کار تکه‌تکه و با
     * سقفِ زمان انجام می‌شود و جایِ ادامه (شناسه‌ی آخرین تلاش) در تنظیمات می‌ماند.
     * اگر تمام نشد، بازدیدهای بعدی (بعد از پاسخ به کاربر) ادامه‌اش می‌دهند.
     *
     * @return array{students:int,items:int,done:bool}
     */
    public function backfill(int $days = 60, float $budget = 15.0): array
    {
        if (! self::ready() || ! LearningService::bankReady()) {
            return ['students' => 0, 'items' => 0, 'done' => true];
        }
        $state = json_decode((string) rescue(fn () => \App\Models\Setting::get('remediation_backfill'), '', false), true) ?: [];
        if (! empty($state['done'])) {
            return ['students' => 0, 'items' => 0, 'done' => true];
        }
        $t0 = microtime(true);
        $over = fn () => microtime(true) - $t0 > $budget;
        $since = now()->subDays($days);
        $made = [];
        $senders = [];
        $state += ['exam' => 0, 'game' => 0, 'exam_done' => false];

        // آزمون‌ها: برای هر آزمون و دانش‌آموز فقط تلاشی که امتیازش حساب شده (وگرنه اولین تلاشِ تمام‌شده)
        if (! $state['exam_done']) {
            $finished = true;
            \App\Models\SmartExamAttempt::whereNotNull('finished_at')->where('finished_at', '>=', $since)->where('id', '>', $state['exam'])
                ->with(['exam.questions', 'answers', 'student'])
                ->chunkById(40, function ($chunk) use (&$state, &$made, &$senders, &$finished, $over) {
                    foreach ($chunk as $att) {
                        if ($over()) {
                            $finished = false;

                            return false;
                        }
                        $state['exam'] = $att->id;
                        $sibling = \App\Models\SmartExamAttempt::where('smart_exam_id', $att->smart_exam_id)->where('student_id', $att->student_id)
                            ->whereNotNull('finished_at')->where('id', '!=', $att->id);
                        $first = $att->rewarded ? true : ! (clone $sibling)->where('rewarded', true)->exists() && ! (clone $sibling)->where('id', '<', $att->id)->exists();
                        if ($first && ($n = $this->backfillExam($att))) {
                            $made[$att->student_id] = ($made[$att->student_id] ?? 0) + $n;
                            $senders[$att->student_id] = $att->exam->teacher_id;
                        }
                    }
                });
            $state['exam_done'] = $finished;
        }

        // بازی‌ها: پاسخِ هر سؤال در progress
        $gamesDone = false;
        if ($state['exam_done'] && ! $over()) {
            $gamesDone = true;
            \App\Models\EduGameAttempt::where('status', 'completed')->where('completed_at', '>=', $since)->where('id', '>', $state['game'])
                ->with(['game.questions', 'student'])
                ->chunkById(40, function ($chunk) use (&$state, &$made, &$senders, &$gamesDone, $over) {
                    foreach ($chunk as $att) {
                        if ($over()) {
                            $gamesDone = false;

                            return false;
                        }
                        $state['game'] = $att->id;
                        if ($n = $this->backfillGame($att)) {
                            $made[$att->student_id] = ($made[$att->student_id] ?? 0) + $n;
                            $senders[$att->student_id] = $att->game->teacher_id;
                        }
                    }
                });
        }
        $state['done'] = $state['exam_done'] && $gamesDone;
        rescue(fn () => \App\Models\Setting::put('remediation_backfill', json_encode($state)), null, false);

        // برای هر دانش‌آموز فقط یک اعلان (فرستنده: معلمِ همان آزمون/بازی)
        foreach ($made as $sid => $n) {
            rescue(function () use ($sid, $n, $senders) {
                $st = User::find($sid);
                if (! $st?->school_id || empty($senders[$sid])) {
                    return;
                }
                $ann = Announcement::create(['school_id' => $st->school_id, 'sender_id' => $senders[$sid], 'audience' => 'personal',
                    'title' => '🔁 مرورِ اشتباه‌های من آماده است',
                    'body' => 'برای ' . Jalali::fa((string) $n) . ' سؤالی که در آزمون‌ها و بازی‌های اخیر اشتباه زدی، مرورِ جبرانی ساخته شد؛ انجامش بده و بخشی از امتیازت را پس بگیر.',
                    'link' => '/review']);
                $ann->recipients()->sync([$sid]);
            }, null, false);
        }

        return ['students' => count($made), 'items' => array_sum($made), 'done' => $state['done']];
    }

    /** اگر تبدیلِ اشتباه‌های گذشته نیمه‌کاره مانده، بعد از پاسخ به کاربر کمی دیگر جلو برود. */
    public static function continueBackfill(): void
    {
        if (! self::ready()) {
            return;
        }
        $state = json_decode((string) rescue(fn () => \App\Models\Setting::get('remediation_backfill'), '', false), true) ?: [];
        if (! $state || ! empty($state['done'])) {
            return; // هنوز مایگریشن اجرا نشده یا کار تمام است
        }
        $run = fn () => rescue(fn () => app(self::class)->backfill(60, 10), null, true);
        function_exists('Illuminate\Support\defer') ? \Illuminate\Support\defer($run) : app()->terminating($run);
    }

    private function backfillExam(\App\Models\SmartExamAttempt $att): int
    {
        $exam = $att->exam;
        if (! $exam || ! $att->student) {
            return 0;
        }
        $qs = $exam->questions->keyBy('id');
        $autoMax = $exam->questions->whereIn('type', ['mc', 'tf', 'blank'])->sum('points');
        $bankObj = SmartQuestionBank::withoutGlobalScopes()->whereIn('id', $exam->questions->pluck('bank_id')->filter())->pluck('objective_id', 'id');
        $stillOpen = $exam->closes_at && $exam->closes_at->isFuture();
        $hide = ! $stillOpen && empty(($exam->rules ?? [])['show_answer'] ?? true);
        $items = [];
        // فقط پاسخِ «قطعاً غلط» — تشریحیِ تصحیح‌نشده (null) نه
        foreach ($att->answers->filter(fn ($a) => $a->correct !== null && ! (bool) $a->correct) as $ans) {
            $q = $qs[$ans->question_id] ?? null;
            if (! $q || $autoMax <= 0) {
                continue;
            }
            $items[] = ['q_key' => $q->bank_id ? 'b' . $q->bank_id : 'e' . $q->id, 'bank_id' => $q->bank_id,
                'objective_id' => \App\Support\Objectives::forQuestion($exam, $q, $q->bank_id ? ($bankObj[$q->bank_id] ?? null) : null),
                'question' => self::snapshot($q) + ($hide ? ['no_original' => true] : []),
                'lost_xp' => $att->rewarded ? (int) round(100 * max(1, (int) $q->points) / $autoMax) : 0];
        }

        return $items ? $this->create($att->student, 'exam', $att->id, $exam->id, $exam->title, $exam->teacher_id, $items,
            $stillOpen ? $exam->closes_at->copy()->addDay()->toDateString() : null, false) : 0;
    }

    private function backfillGame(\App\Models\EduGameAttempt $att): int
    {
        $game = $att->game;
        $detail = (array) (($att->progress ?? [])['answers'] ?? []);
        if (! $game || ! $att->student || ! $detail) {
            return 0;
        }
        $bankObj = SmartQuestionBank::withoutGlobalScopes()->whereIn('id', $game->questions->pluck('bank_id')->filter())->pluck('objective_id', 'id');
        $items = [];
        foreach ($game->questions->values() as $i => $q) {
            if (isset($detail[$i]) && empty($detail[$i]['correct'])) {
                $items[] = ['q_key' => $q->bank_id ? 'b' . $q->bank_id : 'g' . $q->id, 'bank_id' => $q->bank_id,
                    'objective_id' => \App\Support\Objectives::forQuestion($game, $q, $q->bank_id ? ($bankObj[$q->bank_id] ?? null) : null),
                    'question' => self::snapshot($q), 'lost_xp' => max(1, (int) $q->points)];
            }
        }

        return $items ? $this->create($att->student, 'game', $att->id, $game->id, $game->title, $game->teacher_id, $items, null, false) : 0;
    }

    /* ═══════════════ معلم ═══════════════ */

    /** نمای کلیِ کلاس برای معلم: هر دانش‌آموز، هر فصل و آخرین یادآوری‌ها. */
    public function overview(Classroom $classroom): ?array
    {
        if (! self::ready()) {
            return null;
        }
        $students = $classroom->students()->get(['users.id', 'users.name'])->keyBy('id');
        $rems = Remediation::whereIn('student_id', $students->keys())->where('created_at', '>=', now()->subDays(120))
            ->orderByDesc('id')->get();
        $today = now()->endOfDay();

        $per = $students->map(function ($s) use ($rems, $today) {
            $mine = $rems->where('student_id', $s->id);
            $open = $mine->where('status', 'open');

            return [
                'id' => $s->id, 'name' => $s->name,
                'total' => $mine->count(),
                'open' => $open->count(),
                'due' => $open->filter(fn ($r) => $r->due_on && $r->due_on->lte($today))->count(),
                'overdue' => $open->filter(fn ($r) => $r->due_on && $r->due_on->lt(now()->subDays(3)->startOfDay()))->count(),
                'done' => $mine->where('status', 'done')->count(),
                'recovered' => (int) $mine->sum('recovered_xp'),
                'cap' => (int) $mine->sum('cap_xp'),
                'last' => optional($mine->max('last_played_at'), fn ($d) => Jalali::format($d)),
                'last_raw' => optional($mine->max('last_played_at'), fn ($d) => $d->timestamp),
            ];
        })->filter(fn ($r) => $r['total'] > 0)->values();

        $chapters = $rems->groupBy(fn ($r) => $r->objective_label ?: 'بدونِ فصل')->map(fn ($g, $label) => [
            'label' => $label,
            'students' => $g->pluck('student_id')->unique()->count(),
            'total' => $g->count(),
            'open' => $g->where('status', 'open')->count(),
            'done' => $g->where('status', 'done')->count(),
        ])->sortByDesc('total')->values();

        $recent = $rems->take(80)->map(fn ($r) => [
            'id' => $r->id, 'student' => $students[$r->student_id]->name ?? '—', 'student_id' => $r->student_id,
            'source' => Remediation::SOURCE_LABELS[$r->source] ?? $r->source, 'title' => $r->source_title,
            'chapter' => $r->objective_label, 'prompt' => mb_substr((string) ($r->question['prompt'] ?? ''), 0, 140),
            'status' => $r->status, 'step' => $r->step, 'steps' => $r->steps, 'tries' => $r->tries,
            'recovered' => $r->recovered_xp, 'cap' => $r->cap_xp,
            'due' => $r->due_on ? Jalali::format($r->due_on) : null,
            'created' => Jalali::format($r->created_at), 'created_raw' => $r->created_at?->timestamp,
        ])->values();

        return [
            // برای فرمِ «فرستادنِ مرور»
            'grade' => $classroom->grade,
            'subjects' => self::subjectsFor($classroom),
            'roster' => $students->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values(),
            'totals' => [
                'total' => $rems->count(), 'open' => $rems->where('status', 'open')->count(),
                'done' => $rems->where('status', 'done')->count(),
                'students' => $per->count(), 'recovered' => (int) $rems->sum('recovered_xp'),
                'rate' => $rems->count() ? (int) round($rems->where('status', 'done')->count() / $rems->count() * 100) : 0,
            ],
            'students' => $per, 'chapters' => $chapters, 'recent' => $recent,
        ];
    }

    /**
     * معلم زمان‌بندیِ کلاس را عوض کرد → مرورهای بازِ همین کلاس هم با زمان‌بندیِ تازه ادامه می‌دهند
     * (نوبت‌های قبول‌شده و امتیازِ برگشته سرِ جایش می‌ماند).
     */
    public function applyPlan(Classroom $classroom, array $plan): int
    {
        if (! self::ready()) {
            return 0;
        }
        $snap = ['gaps' => $plan['gaps'], 'retry' => $plan['retry'], 'pass' => $plan['pass']];
        $n = 0;
        Remediation::whereIn('student_id', $classroom->students()->pluck('users.id'))->where('status', 'open')
            ->get()->each(function ($r) use ($plan, $snap, &$n) {
                $r->update(['steps' => max($r->step + 1, $plan['rounds']), 'plan' => $snap]);
                $n++;
            });

        return $n;
    }

    /** فهرستِ مرورهای خودِ دانش‌آموز برای صفحه‌ی «مرورِ اشتباه‌های من». */
    public function studentBoard(User $student): array
    {
        if (! self::ready()) {
            return ['enabled' => false];
        }
        $plan = RemediationPlan::forStudent($student);
        $today = now()->endOfDay();
        $rows = Remediation::where('student_id', $student->id)->where('created_at', '>=', now()->subDays(90))
            ->orderBy('due_on')->orderByDesc('id')->get()
            ->map(fn ($r) => [
                'id' => $r->id, 'source' => Remediation::SOURCE_LABELS[$r->source] ?? $r->source,
                'title' => $r->source_title, 'chapter' => $r->objective_label,
                'prompt' => ! empty($r->question['no_original']) ? null : mb_substr((string) ($r->question['prompt'] ?? ''), 0, 160),
                'status' => $r->status, 'step' => $r->step, 'steps' => $r->steps,
                'due' => $r->due_on ? Jalali::format($r->due_on) : null,
                'ready' => $r->status === 'open' && $r->due_on && $r->due_on->lte($today),
                'days' => $r->status === 'open' && $r->due_on ? max(0, (int) now()->startOfDay()->diffInDays($r->due_on->copy()->startOfDay(), false)) : null,
                'recovered' => $r->recovered_xp, 'cap' => $r->cap_xp,
                'created' => Jalali::format($r->created_at),
            ]);

        return [
            'enabled' => true,
            'plan' => $plan,
            'summary' => $this->summary($student),
            'ready' => $rows->where('ready', true)->values(),
            'waiting' => $rows->where('status', 'open')->where('ready', false)->values(),
            'done' => $rows->where('status', 'done')->sortByDesc('id')->take(30)->values(),
        ];
    }

    /** نامِ درس‌های کلاس (از کتاب‌های پایه؛ اگر نبود، درس‌هایی که برای این پایه فصل دارند). */
    public static function subjectsFor(Classroom $classroom): array
    {
        $names = collect(rescue(fn () => $classroom->subjectNames(), [], false))
            ->map(fn ($s) => is_array($s) ? ($s['name'] ?? null) : $s)->filter()->values();
        if ($names->isEmpty() && $classroom->grade) {
            $names = \App\Models\CurriculumChapter::withoutGlobalScopes()->where('grade', $classroom->grade)
                ->where('is_active', true)->distinct()->orderBy('subject')->pluck('subject');
        }

        return $names->unique()->values()->all();
    }

    /** راه‌اندازیِ مجددِ آزمون/بازی: یادآوری‌ها و امتیازِ جبرانیِ همان تلاش‌ها هم پاک می‌شود. */
    public static function forgetSources(string $source, $sourceIds, ?array $studentIds = null): int
    {
        if (! self::ready()) {
            return 0;
        }
        $q = Remediation::where('source', $source)->whereIn('source_id', collect($sourceIds)->all())
            ->when($studentIds, fn ($w) => $w->whereIn('student_id', $studentIds));
        $ids = $q->pluck('id');
        if ($ids->isEmpty()) {
            return 0;
        }
        XpEntry::where('source_type', Remediation::class)->whereIn('source_id', $ids)->delete();

        return Remediation::whereIn('id', $ids)->delete();
    }

    /* ═══════════════ سؤالِ مشابه با هوش مصنوعی ═══════════════ */

    private function queueVariants(User $student, Collection $rems): void
    {
        $rems = $rems->filter(fn ($r) => ! $r->ai_tried && $r->objective_id)->take(self::AI_PER_BATCH);
        if ($rems->isEmpty() || ! self::aiOn()) {
            return;
        }
        Remediation::whereIn('id', $rems->pluck('id'))->update(['ai_tried' => true]);
        $ids = $rems->pluck('id')->all();
        $run = function () use ($ids, $student) {
            foreach (Remediation::whereIn('id', $ids)->get() as $rem) {
                rescue(fn () => $this->makeVariants($rem, $student), null, true);
            }
        };
        // بعد از فرستادنِ پاسخ اجرا می‌شود تا دانش‌آموز منتظرِ هوش مصنوعی نماند
        function_exists('Illuminate\Support\defer') ? \Illuminate\Support\defer($run) : app()->terminating($run);
    }

    public static function aiAvailable(): bool
    {
        return self::aiOn();
    }

    private static function aiOn(): bool
    {
        return (bool) rescue(fn () => \App\Support\SmartLab::flag('smart_ai_enabled')
            && \App\Support\AiConfig::family() !== 'off' && \App\Support\AiConfig::key(), false, false);
    }

    private function makeVariants(Remediation $rem, User $student): void
    {
        $teacher = $rem->teacher_id ? User::find($rem->teacher_id) : null;
        $obj = \App\Models\LearningObjective::find($rem->objective_id);
        if (! $teacher || ! $obj) {
            return;
        }
        // فقط اگر هنوز سؤالِ مشابه نیست (شاید معلم در این فاصله اضافه کرده باشد)
        if ($this->similar($rem, $teacher, array_filter([$rem->bank_id]), 1)->isNotEmpty()) {
            return;
        }
        $res = app(SmartExamAiService::class)->generate([
            'grade' => $obj->grade, 'subject' => $obj->subject, 'chapter_id' => $obj->chapter_id, 'chapter' => $obj->chapter,
            'topic' => $obj->topic ?: $obj->label, 'count' => self::SIMILAR, 'types' => ['mc'], 'difficulty' => 'easy',
            'audience' => 'mission', 'school_id' => $teacher->school_id, 'teacher_id' => $teacher->id,
            'instructions' => 'سؤال‌های «مشابه» برای تمرینِ جبرانی بساز: همان مهارت و همان سطح، ولی با عدد، مثال و پاسخِ متفاوت. سؤالِ اصلی: «'
                . mb_substr((string) ($rem->question['prompt'] ?? ''), 0, 300) . '»',
            'avoid' => [(string) ($rem->question['prompt'] ?? '')],
        ]);
        foreach (($res['ok'] ?? false) ? ($res['questions'] ?? []) : [] as $q) {
            BankAccess::autosave($teacher, $q, [
                'level' => $obj->level, 'grade' => $obj->grade, 'subject' => $obj->subject,
                'chapter_id' => $obj->chapter_id, 'chapter' => $obj->chapter, 'topic' => $obj->topic, 'source' => 'ai',
            ]);
        }
    }
}
