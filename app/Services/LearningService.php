<?php

namespace App\Services;

use App\Models\LearningObjective;
use App\Models\ObjectiveReview;
use App\Models\PracticeAnswer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * هسته‌ی یادگیری: ثبتِ پاسخ‌ها، زمان‌بندیِ مرورِ فاصله‌دار و تشخیصِ «ارتقای سطح».
 *
 * عددِ تسلط را خودش حساب نمی‌کند — منبعِ واحدِ تسلط MasteryService است و
 * پاسخ‌های ثبت‌شده در practice_answers یکی از شواهدِ آن است.
 *
 * جعبه‌ی لایتنر برای هر هدف در هر جلسه (نه هر پاسخ) جابه‌جا می‌شود:
 * همه درست در بارِ اول ← یک جعبه جلوتر؛ یک غلط ← جعبه‌ی ۱؛ درست با تلاشِ دوم/راهنما ← همان جعبه.
 */
class LearningService
{
    /** فاصله‌ی مرورِ هر جعبه (روز). */
    public const INTERVALS = [1 => 1, 2 => 3, 3 => 7, 4 => 14, 5 => 30];

    /** رتبه‌ی سطح‌های تسلط (کلیدهای MasteryService::LEVELS). */
    public const LEVEL_RANK = ['beginning' => 1, 'developing' => 2, 'proficient' => 3, 'master' => 4];

    public function __construct(private MasteryService $mastery) {}

    /** اگر به‌روزرسانیِ کد پیش از اجرای مایگریشن برسد، هیچ صفحه‌ای نباید ۵۰۰ بدهد. */
    public static function ready(): bool
    {
        static $ok = null;

        return $ok ??= \Illuminate\Support\Facades\Schema::hasTable('objective_reviews')
            && \Illuminate\Support\Facades\Schema::hasTable('practice_answers');
    }

    /** ستونِ «هدفِ درسی» در بانک هم آماده است؟ (پیش از اجرای مایگریشن، ثبتِ آزمون نباید بشکند) */
    public static function bankReady(): bool
    {
        static $ok = null;

        return $ok ??= self::ready() && \Illuminate\Support\Facades\Schema::hasColumn('smart_question_bank', 'objective_id');
    }

    /**
     * نمره‌ی معلم در دفترِ نمره → زمان‌بندیِ مرور.
     * فقط نمره‌ی ضعیف (زیرِ ۵۰٪) فصل را به «مرورِ فردا» می‌برد؛ نمره‌ی خوب جعبه را جلو
     * نمی‌برد تا ذخیره‌ی دوباره‌ی یک ستون، مرور را بی‌جهت عقب نیندازد. تسلط را خودِ
     * موتورِ تسلط از نمره‌ها می‌خواند (اینجا شاهدِ تکراری ثبت نمی‌شود).
     */
    public static function fromGradeColumn(?\App\Models\GradeColumn $col): void
    {
        if (! $col || ! $col->chapter_id || ! self::ready()) {
            return;
        }
        $classroom = \App\Models\Classroom::find($col->classroom_id);
        $subject = $col->lesson ?: MasteryService::guessSubject($col->title);
        if (! $subject) {
            return;
        }
        $oid = \App\Support\Objectives::idFor(['grade' => $classroom?->grade, 'subject' => $subject, 'chapter_id' => $col->chapter_id]);
        $type = $col->score_type ?: $col->type;
        foreach ($col->grades()->with('student')->get() as $g) {
            $f = MasteryService::gradeFraction($type, $g->score, $g->text, $col->max);
            if ($f !== null && $f < 0.5 && $g->student) {
                app(self::class)->record($g->student, [['objective_id' => $oid, 'correct' => false]], 'grade', $col->id, false);
                // نمره‌ی ضعیفِ معلم → مرورِ فردی با سؤال‌های همان فصل (یک‌بار برای هر ستون)
                rescue(fn () => app(RemediationService::class)->fromGrade($col, $g->student, $f, $oid), null, true);
            }
        }
    }

    /** کسرِ ۰ تا ۱ یک پاسخ برای موتورِ تسلط. */
    public static function credit(bool $correct, bool $firstTry, bool $hinted): float
    {
        $c = $correct ? ($firstTry ? 1.0 : 0.5) : 0.0;

        return $hinted ? min($c, 0.75) : $c;
    }

    /**
     * @param  array<int,array{objective_id:?int,bank_id?:?int,correct:bool,first_try?:bool,hinted?:bool}>  $events
     * @param  bool  $asEvidence  پاسخ‌های مأموریت/مرور شاهدِ تسلط‌اند؛ آزمون و بازی شواهدِ خودشان را دارند و فقط زمان‌بندیِ مرور را به‌روز می‌کنند.
     * @return array{band_ups: array<int,array{label:string,subject:?string,from:string,to:string}>}
     */
    public function record(User $student, array $events, string $source, ?int $sourceId = null, bool $asEvidence = true): array
    {
        $events = array_values(array_filter($events, fn ($e) => ! empty($e['objective_id'])));
        if (! $events || ! self::ready()) {
            return ['band_ups' => []];
        }

        $before = $asEvidence ? $this->topicLevels($student) : [];

        DB::transaction(function () use ($student, $events, $source, $sourceId, $asEvidence) {
            $now = now();
            $byObjective = [];
            $log = [];
            foreach ($events as $e) {
                $oid = (int) $e['objective_id'];
                $byObjective[$oid][] = $e;
                if ($asEvidence) {
                    $log[] = [
                        'school_id' => $student->school_id, 'student_id' => $student->id, 'objective_id' => $oid,
                        'bank_id' => $e['bank_id'] ?? null, 'source' => $source, 'source_id' => $sourceId,
                        'correct' => (bool) $e['correct'], 'first_try' => (bool) ($e['first_try'] ?? true),
                        'hinted' => (bool) ($e['hinted'] ?? false), 'created_at' => $now,
                    ];
                }
            }
            if ($log) {
                PracticeAnswer::insert($log);
            }

            $rows = ObjectiveReview::withoutGlobalScopes()->where('student_id', $student->id)
                ->whereIn('objective_id', array_keys($byObjective))->get()->keyBy('objective_id');

            foreach ($byObjective as $oid => $list) {
                $r = $rows[$oid] ?? new ObjectiveReview([
                    'school_id' => $student->school_id, 'student_id' => $student->id, 'objective_id' => $oid,
                    'box' => 1, 'attempts' => 0, 'correct' => 0, 'best_level' => 0,
                ]);
                $anyWrong = false;
                $clean = true;
                foreach ($list as $e) {
                    $ok = (bool) $e['correct'];
                    $r->attempts++;
                    $r->correct += $ok ? 1 : 0;
                    $anyWrong = $anyWrong || ! $ok;
                    $clean = $clean && $ok && ($e['first_try'] ?? true) && ! ($e['hinted'] ?? false);
                }
                $r->box = $anyWrong ? 1 : ($clean ? min(5, (int) $r->box + 1) : (int) $r->box);
                $r->due_at = $now->copy()->startOfDay()->addDays(self::INTERVALS[$r->box]);
                $r->last_seen_at = $now;
                $r->save();
            }
        });

        if (! $asEvidence) {
            return ['band_ups' => []];
        }

        return ['band_ups' => $this->bandUps($student, $before, array_unique(array_map(fn ($e) => (int) $e['objective_id'], $events)))];
    }

    /**
     * سطح‌هایی که بعد از این جلسه بالا رفته‌اند — فقط اولین بار که هدف به آن سطح می‌رسد
     * (تا با بالا و پایین رفتنِ عدد، پاداش تکرار نشود).
     */
    private function bandUps(User $student, array $before, array $objectiveIds): array
    {
        MasteryService::forget($student->id);
        $after = $this->topicLevels($student);
        $objectives = LearningObjective::whereIn('id', $objectiveIds)->get(['id', 'subject', 'label']);
        $reviews = ObjectiveReview::withoutGlobalScopes()->where('student_id', $student->id)
            ->whereIn('objective_id', $objectiveIds)->get()->keyBy('objective_id');

        $ups = [];
        foreach ($objectives as $o) {
            $k = self::topicKey($o->subject, $o->label);
            $from = $before[$k] ?? null;
            $to = $after[$k] ?? null;
            $toRank = self::LEVEL_RANK[$to] ?? 0;
            $rev = $reviews[$o->id] ?? null;
            if (! $rev || $toRank <= (int) $rev->best_level) {
                continue;
            }
            $isUp = $from !== null && $toRank > (self::LEVEL_RANK[$from] ?? 0);
            $rev->update(['best_level' => $toRank]);
            if ($isUp) {
                $ups[] = ['label' => $o->label, 'subject' => $o->subject, 'from' => $from, 'to' => $to];
            }
        }

        return $ups;
    }

    /** سطحِ هر مبحث از موتورِ تسلط: «درس|مبحث» => کلیدِ سطح. */
    public function topicLevels(User $student): array
    {
        MasteryService::forget($student->id);
        $out = [];
        foreach ($this->mastery->forStudent($student->id)['subjects'] ?? [] as $s) {
            foreach ($s['topics'] ?? [] as $t) {
                if ($t['mastery'] !== null && ! empty($t['level']['key'])) {
                    $out[self::topicKey($s['name'], $t['name'])] = $t['level']['key'];
                }
            }
        }

        return $out;
    }

    public static function topicKey(?string $subject, ?string $topic): string
    {
        return MasteryService::subject($subject) . '|' . MasteryService::cleanTopic($topic);
    }
}
