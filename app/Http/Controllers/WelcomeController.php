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
    /**
     * ستاره‌های هفته — بیشترین امتیازِ مثبتِ ۷ روزِ اخیر، فقط دانش‌آموزان.
     *
     * پیش از این همه‌ی ردیف‌های امتیاز (حتی کسرِ امتیاز و کاربرانِ غیرِ
     * دانش‌آموز) جمع می‌شد، بر اساسِ «نام» گروه‌بندی می‌شد (دو هم‌نام یکی
     * می‌شدند) و مدرسه و کلاس معلوم نبود. حالا اگر این هفته امتیازی نبود،
     * برترین‌های ۳۰ روزِ اخیر و در نهایت کلِ دوره نشان داده می‌شود و عنوان هم
     * همان را می‌گوید.
     *
     * @return array{0: array<int,array>, 1: string}
     */
    private function weeklyStars(): array
    {
        $studentIds = fn () => DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', Roles::STUDENT)->where('model_has_roles.model_type', User::class)->select('model_has_roles.model_id');

        $query = fn ($since) => DB::table('xp_ledger')
            ->where('amount', '>', 0)
            ->whereIn('student_id', $studentIds())
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->groupBy('student_id')
            ->select('student_id', DB::raw('SUM(amount) as xp'))
            ->orderByDesc('xp')->limit(6)->get();

        $period = 'week';
        $rows = $query(now()->subDays(7));
        if ($rows->isEmpty()) {
            $period = 'month';
            $rows = $query(now()->subDays(30));
        }
        if ($rows->isEmpty()) {
            $period = 'all';
            $rows = $query(null);
        }

        $users = User::with(['school:id,name', 'theme:id,name,emoji', 'classrooms:id,name,grade'])
            ->whereIn('id', $rows->pluck('student_id'))->get()->keyBy('id');

        $out = [];
        foreach ($rows->values() as $i => $r) {
            $u = $users[$r->student_id] ?? null;
            if (! $u) {
                continue;
            }
            $class = $u->classrooms->first();
            $out[] = [
                'rank'   => $i + 1,
                'name'   => $u->name,
                'avatar' => $u->avatar_url,
                'school' => $u->school?->name,
                'class'  => $class?->name,
                'team'   => $u->theme ? trim(($u->theme->emoji ?? '') . ' ' . $u->theme->name) : null,
                'xp'     => (int) $r->xp,
            ];
        }

        return [$out, $period];
    }

    public function __invoke(): Response
    {
        [$stars, $period] = $this->weeklyStars();

        return Inertia::render('Welcome', [
            'weeklyTop' => $stars,
            'starsPeriod' => $period,
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
