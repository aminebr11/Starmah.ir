<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** جدول رقابتی کلاس بر اساس مجموع XP. */
class LeaderboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $classroom = $user->classrooms()->with('teacher')->first();

        $rows = [];
        if ($classroom) {
            $rows = $classroom->students()->get()
                ->map(fn ($s) => [
                    'id'    => $s->id,
                    'name'  => $s->name,
                    'xp'    => $s->totalXp(),
                    'is_me' => $s->id === $user->id,
                ])
                ->sortByDesc('xp')->values()
                ->map(fn ($r, $i) => [...$r, 'rank' => $i + 1])->all();
        }

        return Inertia::render('Student/Leaderboard', [
            'classroom'   => $classroom?->name,
            'leaderboard' => $rows,
        ]);
    }
}
