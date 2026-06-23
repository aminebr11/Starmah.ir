<?php

namespace App\Http\Controllers;

use App\Models\ActivityResult;
use App\Models\DisciplineRecord;
use App\Models\SkillMastery;
use App\Models\XpEntry;
use App\Support\Jalali;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** کارنامه: تسلط مهارت، نتایج اخیر، انضباط، نشان‌ها. */
class ProgressController extends Controller
{
    public function __invoke(Request $request, \App\Services\AnalyticsService $analytics): Response
    {
        $user = $request->user();
        $summary = $analytics->studentSummary($user);

        $mastery = SkillMastery::with('skill.topic')
            ->where('student_id', $user->id)
            ->get()
            ->map(fn ($m) => [
                'skill'   => $m->skill?->name,
                'topic'   => $m->skill?->topic?->name,
                'mastery' => $m->mastery,
            ]);

        $recent = ActivityResult::with('skill')
            ->where('student_id', $user->id)
            ->latest()->limit(8)->get()
            ->map(fn ($r) => [
                'skill'    => $r->skill?->name ?? 'تمرین',
                'score'    => $r->score,
                'max'      => $r->max_score,
                'accuracy' => $r->accuracy,
                'date'     => $r->created_at?->format('Y/m/d'),
            ]);

        $discipline = DisciplineRecord::where('student_id', $user->id)->latest()->limit(10)->get()
            ->map(fn ($d) => ['type' => $d->type, 'points' => $d->points, 'note' => $d->note, 'date' => Jalali::format($d->created_at)]);

        // دفتر امتیاز: هر جا امتیاز گرفته یا از دست داده (با تاریخ شمسی)
        $pointsLog = XpEntry::where('student_id', $user->id)->latest()->limit(20)->get()
            ->map(fn ($e) => [
                'amount' => $e->amount,
                'reason' => $e->reason ?? 'امتیاز',
                'date'   => Jalali::format($e->created_at),
            ]);

        // نمرات دفتر کلاسی این دانش‌آموز
        $grades = \App\Models\Grade::where('student_id', $user->id)->with('gradeColumn')->latest()->get()
            ->map(fn ($g) => [
                'title' => $g->gradeColumn?->title,
                'type'  => $g->gradeColumn?->type,
                'max'   => (float) ($g->gradeColumn?->max ?? 20),
                'score' => $g->score,
                'text'  => $g->text,
            ])->values();

        return Inertia::render('Student/Progress', [
            'stats' => [
                'xp'        => $user->totalXp(),
                'avg'       => (int) round($mastery->avg('mastery') ?? 0),
                'stars'     => (int) DisciplineRecord::where('student_id', $user->id)->where('type', 'star')->sum('points'),
                'badges'    => $user->badges()->count(),
            ],
            'mastery'    => $mastery,
            'recent'     => $recent,
            'points_log' => $pointsLog,
            'summary'    => $summary,
            'grades'     => $grades,
            'discipline' => $discipline,
            'badges'     => $user->badges()->get()->map(fn ($b) => ['name' => $b->name, 'emoji' => $b->emoji]),
        ]);
    }
}
