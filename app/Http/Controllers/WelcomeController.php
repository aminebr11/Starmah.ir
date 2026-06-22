<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Question;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** صفحه‌ی اول سایت — هیرو، آمار، برترین‌های هفته. */
class WelcomeController extends Controller
{
    public function __invoke(): Response
    {
        $top = fn ($since = null) => DB::table('xp_ledger')
            ->join('users', 'users.id', '=', 'xp_ledger.student_id')
            ->when($since, fn ($q) => $q->where('xp_ledger.created_at', '>=', $since))
            ->groupBy('users.id', 'users.name')
            ->select('users.name', DB::raw('SUM(xp_ledger.amount) as xp'))
            ->orderByDesc('xp')->limit(5)->get();

        $weekly = $top(now()->subDays(7));
        if ($weekly->isEmpty()) {
            $weekly = $top();
        }

        return Inertia::render('Welcome', [
            'weeklyTop' => $weekly->values()->map(fn ($r, $i) => [
                'rank' => $i + 1, 'name' => $r->name, 'xp' => (int) $r->xp,
            ]),
            'stats' => [
                'students'   => User::role(Roles::STUDENT)->count(),
                'classrooms' => Classroom::count(),
                'activities' => Question::count(),
            ],
        ]);
    }
}
