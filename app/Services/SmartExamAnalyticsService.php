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

        $rows = $attempts->groupBy('student_id')->map(function ($g) {
            $best = $g->sortByDesc('score')->first();
            return [
                'name' => $best->student?->name,
                'attempts' => $g->count(),
                'score' => $best->score, 'max' => $best->max_score,
                'percent' => $best->max_score ? (int) round($best->score / $best->max_score * 100) : 0,
                'duration' => (int) $g->avg('duration_sec'),
                'status' => $best->status,
            ];
        })->sortByDesc('percent')->values();

        // درصد پاسخ صحیح هر سؤال + سؤال‌های دشوار
        $perQuestion = [];
        foreach ($exam->questions->values() as $i => $q) {
            $ans = \App\Models\SmartExamAnswer::whereIn('attempt_id', $attempts->pluck('id'))
                ->where('q_index', $i)->get();
            $answered = $ans->count();
            $correct = $ans->where('correct', true)->count();
            $perQuestion[] = [
                'i' => $i, 'prompt' => $q->prompt, 'topic' => $q->topic,
                'answered' => $answered, 'correct' => $correct,
                'pct' => $answered ? (int) round($correct / $answered * 100) : null,
            ];
        }
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

        return [
            'strengths' => $topics->where('pct', '>=', 70)->pluck('topic')->take(4)->values(),
            'weakTopics' => $topics->where('pct', '<', 60)->sortBy('pct')->values(),
            'wrong' => collect($wrong)->take(10)->values(),
            'topics' => $topics->sortByDesc('pct')->values(),
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
