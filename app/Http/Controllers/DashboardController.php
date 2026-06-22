<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Skill;
use App\Models\XpEntry;
use App\Services\ThemeEngine;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ThemeEngine $engine): Response
    {
        $user = $request->user();

        // کلاس دانش‌آموز و جدول رقابتی (لیدربورد بر اساس XP)
        $classroom = $user->classrooms()->with('teacher')->first();

        $leaderboard = [];
        if ($classroom) {
            $leaderboard = $classroom->students()
                ->get()
                ->map(fn ($s) => [
                    'id'     => $s->id,
                    'name'   => $s->name,
                    'xp'     => $s->totalXp(),
                    'is_me'  => $s->id === $user->id,
                ])
                ->sortByDesc('xp')
                ->values()
                ->map(fn ($row, $i) => [...$row, 'rank' => $i + 1])
                ->all();
        }

        // یک سؤال نمونه‌ی روکش‌خورده برای «مسابقه‌ی امروز»
        $theme = $engine->for($user);
        $sampleSkill = Skill::with('questions')->whereHas('questions')->inRandomOrder()->first();
        $sample = null;
        if ($sampleSkill && $q = $sampleSkill->questions->random()) {
            $sample = [
                'skill' => $sampleSkill->name,
                ...$engine->renderQuestion($q, $theme),
            ];
        }

        return Inertia::render('Student/Dashboard', [
            'stats' => [
                'xp'       => $user->totalXp(),
                'badges'   => $user->badges()->count(),
                'classroom'=> $classroom?->name,
                'teacher'  => $classroom?->teacher?->name,
            ],
            'leaderboard' => $leaderboard,
            'sample'      => $sample,
        ]);
    }
}
