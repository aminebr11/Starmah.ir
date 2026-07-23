<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\TeamScoreService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** «تیمِ ما» — دانش‌آموز مجموعِ امتیازِ تیم، اعضا و دفترِ ریزِ اخیر (امتیاز از کجا آمده) را می‌بیند. */
class TeamController extends Controller
{
    public function __invoke(Request $request, TeamScoreService $svc): Response
    {
        $user = $request->user();
        $classroom = $user->classrooms()->first();

        $teams = $classroom ? $svc->teams($classroom, $user->id) : [];
        $mine = collect($teams)->firstWhere('theme_id', $user->theme_id);
        $rank = collect($teams)->search(fn ($t) => $t['theme_id'] === $user->theme_id);
        // حریمِ خصوصی: دانش‌آموز فقط ریزِ امتیازِ خودش را می‌بیند؛ از هم‌تیمی‌ها فقط جمعِ کل.
        $ledger = ($classroom && $user->theme_id) ? $svc->ledger($user->theme_id, $classroom, 40, $user->id) : [];

        return Inertia::render('Student/Team', [
            'teams'  => $teams,
            'mine'   => $mine,
            'rank'   => $rank === false ? null : $rank + 1,
            'ledger' => $ledger,
        ]);
    }
}
