<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Question;
use App\Models\User;
use App\Support\Roles;
use Inertia\Inertia;
use Inertia\Response;

/** صفحه‌ی اول سایت — هیرو، آمار، برترین‌های هفته. */
class WelcomeController extends Controller
{
    public function __invoke(): Response
    {
        $points = app(\App\Services\PointsAnalytics::class);
        $week = $points->weekTop(5);
        $year = $points->yearTop(null, 5);

        return Inertia::render('Welcome', [
            // برترین‌های همین هفته (شنبه تا جمعه) و برترین‌های کلِ سالِ تحصیلی
            'weeklyTop' => $week['rows'],
            'weekInfo' => $week['week'],
            'yearTop' => $year['rows'],
            'yearLabel' => $year['label'],
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
