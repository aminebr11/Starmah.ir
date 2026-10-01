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

        // تسلط از همه‌ی فعالیت‌ها: هر درس یک ردیف، مبحث‌ها زیرِ آن
        $detail = app(\App\Services\MasteryService::class)->forStudent($user->id);
        $mastery = collect($detail['subjects'])->flatMap(fn ($s) => collect($s['topics'])->filter(fn ($t) => $t['mastery'] !== null)
            ->map(fn ($t) => ['skill' => $t['name'], 'topic' => $s['name'], 'mastery' => $t['mastery']])
            ->prepend(['skill' => $s['name'], 'topic' => 'کلِ درس', 'mastery' => $s['mastery']]))
            ->filter(fn ($r) => $r['mastery'] !== null)->values();

        $recent = ActivityResult::with('skill')
            ->where('student_id', $user->id)
            ->latest()->limit(8)->get()
            ->map(fn ($r) => [
                'skill'    => $r->skill?->name ?? 'تمرین',
                'score'    => $r->score,
                'max'      => $r->max_score,
                'accuracy' => $r->accuracy,
                'date'     => Jalali::format($r->created_at),
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
                'avg'       => $detail['overall'],
                'stars'     => (int) DisciplineRecord::where('student_id', $user->id)->where('type', 'star')->sum('points'),
                'badges'    => $user->badges()->count(),
            ],
            'mastery'    => $mastery,
            'masteryDetail' => $detail,
            'masteryLevels' => \App\Services\MasteryService::LEVELS,
            'recent'     => $recent,
            'points_log' => $pointsLog,
            'summary'    => $summary,
            'grades'     => $grades,
            'discipline' => $discipline,
            'badges'     => $user->badges()->get()->map(fn ($b) => ['name' => $b->name, 'emoji' => $b->emoji]),
        ]);
    }
}
