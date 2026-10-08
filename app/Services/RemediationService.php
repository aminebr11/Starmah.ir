<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\Remediation;
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
 * - هر یادآوری ۳ نوبت دارد: همان روز، ۲ روز بعد، ۵ روز بعد از آن (فاصله‌ی رو به افزایش).
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

    public static function ready(): bool
    {
        static $ok = null;

        return $ok ??= (bool) rescue(fn () => Schema::hasTable('remediations'), false, false);
    }

    /* ═══════════════ ساخت ═══════════════ */

    /**
     * @param  array<int,array{q_key:string,bank_id:?int,objective_id:?int,question:array,lost_xp:int}>  $items
     * @return int تعدادِ یادآوریِ تازه
     */
    public function create(User $student, string $source, int $sourceId, ?int $ref, string $title, ?int $teacherId, array $items, ?string $dueOn = null): int
    {
        $dueOn = $dueOn && $dueOn > now()->toDateString() ? $dueOn : now()->toDateString();
        if (! self::ready() || ! $items) {
            return 0;
        }
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
                    'cap_xp' => $lost > 0 ? max(1, (int) floor($lost * self::SHARE)) : 0,
                    'steps' => self::STEPS, 'due_on' => $dueOn, 'status' => 'open',
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
        rescue(function () use ($student, $teacherId, $made, $title, $cap) {
            $ann = Announcement::create([
                'school_id' => $student->school_id, 'sender_id' => $teacherId, 'audience' => 'personal',
                'title' => '🔁 یادآوریِ جبرانی — ' . mb_substr($title, 0, 120),
                'body' => 'برای ' . Jalali::fa((string) $made->count()) . ' سؤالی که اشتباه زدی، تمرینِ جبرانی '
                    . ($made->first()->due_on && $made->first()->due_on->isFuture() ? 'از ' . Jalali::format($made->first()->due_on) . ' آماده می‌شود' : 'آماده است')
                    . ($cap > 0 ? '؛ با انجامش تا ' . Jalali::fa((string) $cap) . ' امتیاز را پس می‌گیری.' : '.'),
                'link' => '/missions/remedial/play',
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
        $rems = $this->due($student)->take(self::PER_SESSION);
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
            $similar = $this->similar($rem, $teacher, array_merge($used, $skip), $orig ? self::SIMILAR : self::SIMILAR + 1);
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
            $passed = $credit >= self::PASS;
            $xp = 0;
            DB::transaction(function () use ($rem, $passed, $credit, &$xp) {
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
                        $rem->due_on = now()->addDays(self::GAP_AFTER_PASS[$rem->step] ?? 3)->toDateString();
                    }
                } else {
                    $rem->due_on = now()->addDay()->toDateString(); // فردا دوباره
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
            'totals' => [
                'total' => $rems->count(), 'open' => $rems->where('status', 'open')->count(),
                'done' => $rems->where('status', 'done')->count(),
                'students' => $per->count(), 'recovered' => (int) $rems->sum('recovered_xp'),
                'rate' => $rems->count() ? (int) round($rems->where('status', 'done')->count() / $rems->count() * 100) : 0,
            ],
            'students' => $per, 'chapters' => $chapters, 'recent' => $recent,
        ];
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
