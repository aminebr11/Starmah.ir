<?php

namespace App\Services;

use App\Models\SmartExam;
use App\Models\SmartExamAttempt;
use App\Models\User;

/** تحلیل نتایج آزمون هوشمند — گزارش معلم و تحلیل فردیِ دانش‌آموز. */
class SmartExamAnalyticsService
{
    /** گزارش کاملِ یک آزمون برای معلم. */
    public function examReport(SmartExam $exam): array
    {
        $exam->loadMissing('questions');
        $attempts = $exam->attempts()->with('student:id,name')->get();
        $completed = $attempts->where('status', 'completed');

        $rows = $attempts->groupBy('student_id')->map(function ($g, $sid) {
            $best = $g->sortByDesc('score')->first();
            return [
                'student_id' => $sid,
                'name' => $best->student?->name,
                'attempts' => $g->count(),
                'score' => $best->score, 'max' => $best->max_score,
                'percent' => $best->max_score ? (int) round($best->score / $best->max_score * 100) : 0,
                'duration' => (int) $g->avg('duration_sec'),
                'status' => $best->status,
            ];
        })->sortByDesc('percent')->values();

        // نگاشتِ تلاش→دانش‌آموز (برای نامِ کسانی که غلط زدند + جدولِ سؤال‌به‌سؤالِ هر دانش‌آموز)
        $attemptStudent = $attempts->pluck('student.name', 'id');
        $allAnswers = \App\Models\SmartExamAnswer::whereIn('attempt_id', $attempts->pluck('id'))->get();

        // درصد پاسخ صحیح هر سؤال + نامِ اشتباه‌کنندگان + سؤال‌های دشوار
        $perQuestion = [];
        foreach ($exam->questions->values() as $i => $q) {
            $ans = $allAnswers->where('q_index', $i);
            $answered = $ans->count();
            $correct = $ans->where('correct', true)->count();
            $wrongNames = $ans->where('correct', false)
                ->map(fn ($a) => $attemptStudent[$a->attempt_id] ?? null)->filter()->unique()->values()->all();
            $perQuestion[] = [
                'i' => $i, 'prompt' => $q->prompt, 'topic' => $q->topic, 'type' => $q->type,
                'answered' => $answered, 'correct' => $correct,
                'wrong' => max(0, $answered - $correct - $ans->whereNull('correct')->count()),
                'wrongNames' => $wrongNames,
                'pct' => $answered ? (int) round($correct / $answered * 100) : null,
            ];
        }

        // پاسخِ هر دانش‌آموز به هر سؤال (بهترین تلاش)
        $bestAttemptIds = $attempts->groupBy('student_id')->map(fn ($g) => $g->sortByDesc('score')->first()->id);
        $studentAnswers = $bestAttemptIds->map(function ($aid, $sid) use ($attemptStudent, $allAnswers, $exam) {
            $byIdx = $allAnswers->where('attempt_id', $aid)->keyBy('q_index');
            return [
                'name' => $attemptStudent[$aid] ?? '—',
                'perQuestion' => $exam->questions->values()->map(function ($q, $i) use ($byIdx) {
                    $a = $byIdx->get($i);
                    return ['i' => $i, 'type' => $q->type, 'ok' => $a ? ($a->correct === null ? null : (bool) $a->correct) : null, 'blank' => ! $a];
                })->values(),
            ];
        })->values();
        $hard = collect($perQuestion)->filter(fn ($p) => $p['pct'] !== null && $p['pct'] < 50)
            ->sortBy('pct')->take(6)->values();

        // مباحث ضعیف
        $weakTopics = collect($perQuestion)->filter(fn ($p) => $p['topic'] && $p['pct'] !== null)
            ->groupBy('topic')->map(fn ($g, $t) => ['topic' => $t, 'pct' => (int) round($g->avg('pct'))])
            ->filter(fn ($x) => $x['pct'] < 60)->sortBy('pct')->values();

        $percents = $rows->pluck('percent');
        return [
            'summary' => [
                'targeted' => $this->targetCount($exam),
                'started' => $rows->count(),
                'completed' => $completed->groupBy('student_id')->count(),
                'avg' => $percents->count() ? (int) round($percents->avg()) : 0,
                'pass' => $percents->count() ? (int) round($percents->filter(fn ($p) => $p >= 50)->count() / $percents->count() * 100) : 0,
                'avgDuration' => $completed->count() ? (int) round($completed->avg('duration_sec')) : 0,
            ],
            'rows' => $rows,
            'perQuestion' => $perQuestion,
            'studentAnswers' => $studentAnswers,
            'hard' => $hard,
            'weakTopics' => $weakTopics,
            'buckets' => $this->buckets($percents->all()),
        ];
    }

    /** تحلیل فردیِ یک تلاش برای دانش‌آموز (نقاط قوت/ضعف + پیشنهاد). */
    public function studentAnalysis(SmartExamAttempt $attempt): array
    {
        $attempt->loadMissing('answers', 'exam.questions');
        $exam = $attempt->exam;
        $qByIndex = $exam->questions->values();

        $wrong = [];
        $byTopic = [];
        foreach ($attempt->answers as $a) {
            $q = $qByIndex[$a->q_index] ?? null;
            $topic = $q?->topic ?: ($exam->topic ?: 'عمومی');
            $byTopic[$topic] ??= ['correct' => 0, 'total' => 0];
            $byTopic[$topic]['total']++;
            if ($a->correct) {
                $byTopic[$topic]['correct']++;
            } elseif ($q) {
                $wrong[] = ['prompt' => $q->prompt, 'explanation' => $q->explanation, 'topic' => $topic];
            }
        }

        $topics = collect($byTopic)->map(fn ($v, $t) => [
            'topic' => $t, 'pct' => $v['total'] ? (int) round($v['correct'] / $v['total'] * 100) : 0,
        ])->values();

        // پاسخنامه‌ی کامل: هر سؤال، پاسخِ دانش‌آموز و پاسخِ درست
        $ansByIndex = $attempt->answers->keyBy('q_index');
        $review = $qByIndex->map(function ($q, $i) use ($ansByIndex) {
            $a = $ansByIndex->get($i);
            $mine = $a ? (is_array($a->value) ? ($a->value['value'] ?? '') : $a->value) : '';
            $type = $q->type;
            $correctVal = null;
            if (in_array($type, ['mc', 'tf'], true)) {
                $ci = collect($q->choices ?? [])->search(fn ($c) => ! empty($c['correct']));
                $correctVal = $ci !== false ? ($q->choices[$ci]['value'] ?? null) : null;
            } elseif ($type === 'blank') {
                $correctVal = is_array($q->answer) ? ($q->answer[0] ?? '') : (string) $q->answer;
            }
            return [
                'i' => $i, 'type' => $type, 'prompt' => $q->prompt,
                'choices' => collect($q->choices ?? [])->map(fn ($c) => (string) ($c['value'] ?? ''))->all(),
                'mine' => (string) ($mine ?? ''),
                'correct' => $type === 'desc' ? null : (string) $correctVal,
                'is_correct' => $a ? (bool) $a->correct : null,
                'explanation' => $q->explanation,
            ];
        })->values();

        return [
            'strengths' => $topics->where('pct', '>=', 70)->pluck('topic')->take(4)->values(),
            'weakTopics' => $topics->where('pct', '<', 60)->sortBy('pct')->values(),
            'wrong' => collect($wrong)->take(10)->values(),
            'topics' => $topics->sortByDesc('pct')->values(),
            'review' => $review,
        ];
    }

    private function targetCount(SmartExam $exam): int
    {
        $exam->loadMissing('targets');
        if ($exam->targets->isEmpty()) {
            return 0;
        }
        $ids = collect();
        foreach ($exam->targets as $t) {
            if ($t->student_id) {
                $ids->push($t->student_id);
            } elseif ($t->classroom_id) {
                $ids = $ids->merge(\App\Models\Classroom::find($t->classroom_id)?->students()->pluck('users.id') ?? []);
            } elseif ($t->theme_id) {
                $ids = $ids->merge(User::where('theme_id', $t->theme_id)->pluck('id'));
            }
        }
        return $ids->unique()->count();
    }

    private function buckets(array $percents): array
    {
        $b = ['۰-۲۰' => 0, '۲۱-۴۰' => 0, '۴۱-۶۰' => 0, '۶۱-۸۰' => 0, '۸۱-۱۰۰' => 0];
        foreach ($percents as $p) {
            $k = $p <= 20 ? '۰-۲۰' : ($p <= 40 ? '۲۱-۴۰' : ($p <= 60 ? '۴۱-۶۰' : ($p <= 80 ? '۶۱-۸۰' : '۸۱-۱۰۰')));
            $b[$k]++;
        }
        return $b;
    }
}
