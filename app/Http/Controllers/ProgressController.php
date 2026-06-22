<?php

namespace App\Http\Controllers;

use App\Models\ActivityResult;
use App\Models\DisciplineRecord;
use App\Models\SkillMastery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** کارنامه: تسلط مهارت، نتایج اخیر، انضباط، نشان‌ها. */
class ProgressController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

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
            ->map(fn ($d) => ['type' => $d->type, 'points' => $d->points, 'note' => $d->note, 'date' => $d->created_at?->format('Y/m/d')]);

        return Inertia::render('Student/Progress', [
            'stats' => [
                'xp'        => $user->totalXp(),
                'avg'       => (int) round($mastery->avg('mastery') ?? 0),
                'stars'     => (int) DisciplineRecord::where('student_id', $user->id)->where('type', 'star')->sum('points'),
                'badges'    => $user->badges()->count(),
            ],
            'mastery'    => $mastery,
            'recent'     => $recent,
            'discipline' => $discipline,
            'badges'     => $user->badges()->get()->map(fn ($b) => ['name' => $b->name, 'emoji' => $b->emoji]),
        ]);
    }
}
