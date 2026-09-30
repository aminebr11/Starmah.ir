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
            // دنیاها (تیم‌ها)ی واقعیِ سایت برای بخشِ «دنیای خودت را انتخاب کن»
            'worlds' => \App\Models\Theme::where('is_active', true)->where('key', '!=', 'brand')
                ->orderBy('sort')->get(['id', 'key', 'name', 'emoji', 'skin'])
                ->map(fn ($t) => [
                    'key' => $t->key, 'name' => $t->name, 'emoji' => $t->emoji,
                    'p1' => $t->skin['p1'] ?? '#5b8def', 'p2' => $t->skin['p2'] ?? '#3a67c8',
                    'acc' => $t->skin['acc'] ?? '#ffd87a',
                    'character' => $t->skin['character'] ?? ($t->skin['mascot'] ?? $t->emoji),
                    'hero' => $t->skin['hero'] ?? '⭐',
                ])->values(),
            'stats' => [
                'students'   => User::role(Roles::STUDENT)->count(),
                'classrooms' => Classroom::count(),
                'activities' => Question::count(),
            ],
        ]);
    }
}
