<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use App\Services\VisitAnalytics;
use App\Support\Roles;
use App\Support\VisitTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** گزارشِ کاملِ بازدید و مشارکت برای ادمینِ کل. */
class VisitReportController extends Controller
{
    public function index(Request $request, VisitAnalytics $va): Response
    {
        $days = VisitAnalytics::period($request->query('days', 30));
        $from = now()->subDays($days - 1)->startOfDay()->toDateString();
        $today = now()->toDateString();
        $onlineSince = now()->subMinutes(VisitTracker::ONLINE_MINUTES);

        $todayRow = DB::table('visits')->where('date', $today)->selectRaw(
            'SUM(CASE WHEN user_id IS NULL THEN 0 ELSE 1 END) m, SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) g, SUM(hits) h'
        )->first();
        $period = DB::table('visits')->where('date', '>=', $from)->selectRaw(
            'COUNT(DISTINCT user_id) m, SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) g, SUM(hits) h, SUM(seconds) s, SUM(CASE WHEN user_id IS NULL THEN 0 ELSE 1 END) md'
        )->first();

        $kpis = [
            'online' => User::where('last_seen_at', '>=', $onlineSince)->count(),
            'online_guests' => DB::table('visits')->where('date', $today)->whereNull('user_id')->where('last_at', '>=', $onlineSince)->count(),
            'today_members' => (int) ($todayRow->m ?? 0), 'today_guests' => (int) ($todayRow->g ?? 0), 'today_hits' => (int) ($todayRow->h ?? 0),
            'members' => (int) ($period->m ?? 0), 'guest_visits' => (int) ($period->g ?? 0), 'hits' => (int) ($period->h ?? 0),
            'avg_minutes' => ($period->md ?? 0) ? (int) round(($period->s ?? 0) / 60 / $period->md) : 0,
            'users' => User::count(),
            'never' => User::whereNull('last_seen_at')->whereNull('last_login_at')->count(),
        ];

        // مشارکتِ هر نقش
        $activeByRole = DB::table('visits')->where('date', '>=', $from)->whereNotNull('user_id')
            ->groupBy('role')->selectRaw('role, COUNT(DISTINCT user_id) a, SUM(seconds) s, SUM(hits) h')->get()->keyBy('role');
        $roles = collect([Roles::STUDENT, Roles::TEACHER, Roles::PARENT, Roles::SCHOOL_ADMIN])->map(function ($r) use ($activeByRole) {
            $total = User::role($r)->count();
            $a = $activeByRole[$r] ?? null;

            return [
                'role' => $r, 'label' => VisitAnalytics::ROLE_LABELS[$r], 'total' => $total,
                'active' => (int) ($a->a ?? 0), 'rate' => $total ? (int) round(($a->a ?? 0) / $total * 100) : 0,
                'minutes' => (int) round(($a->s ?? 0) / 60), 'hits' => (int) ($a->h ?? 0),
                'online' => User::role($r)->where('last_seen_at', '>=', now()->subMinutes(VisitTracker::ONLINE_MINUTES))->count(),
            ];
        });

        // رتبه‌بندیِ مدارس بر اساسِ نرخِ مشارکت
        $usersBySchool = DB::table('users')->join('model_has_roles', function ($j) {
            $j->on('model_has_roles.model_id', '=', 'users.id')->where('model_has_roles.model_type', User::class);
        })->join('roles', 'roles.id', '=', 'model_has_roles.role_id')->whereNotNull('users.school_id')
            ->groupBy('users.school_id', 'roles.name')->selectRaw('users.school_id sid, roles.name r, COUNT(*) c')->get()->groupBy('sid');
        $activeBySchool = DB::table('visits')->where('date', '>=', $from)->whereNotNull('user_id')->whereNotNull('school_id')
            ->groupBy('school_id', 'role')->selectRaw('school_id sid, role r, COUNT(DISTINCT user_id) a, SUM(seconds) s, SUM(hits) h')->get()->groupBy('sid');
        $schools = School::orderBy('name')->get(['id', 'name', 'city'])->map(function ($s) use ($usersBySchool, $activeBySchool) {
            $u = collect($usersBySchool[$s->id] ?? [])->pluck('c', 'r');
            $a = collect($activeBySchool[$s->id] ?? []);
            $ar = $a->pluck('a', 'r');
            $rate = fn ($r) => ($u[$r] ?? 0) ? (int) round(($ar[$r] ?? 0) / $u[$r] * 100) : null;
            $total = (int) $u->sum();
            $active = (int) $a->sum('a');

            return [
                'id' => $s->id, 'name' => $s->name, 'city' => $s->city,
                'users' => $total, 'active' => $active, 'rate' => $total ? (int) round($active / $total * 100) : 0,
                'students' => (int) ($u[Roles::STUDENT] ?? 0), 'student_rate' => $rate(Roles::STUDENT),
                'teachers' => (int) ($u[Roles::TEACHER] ?? 0), 'teacher_rate' => $rate(Roles::TEACHER),
                'parents' => (int) ($u[Roles::PARENT] ?? 0), 'parent_rate' => $rate(Roles::PARENT),
                'hits' => (int) $a->sum('h'), 'minutes' => (int) round($a->sum('s') / 60),
            ];
        })->sortByDesc('rate')->values();

        $onlineNow = User::with('roles', 'school:id,name')->where('last_seen_at', '>=', $onlineSince)
            ->orderByDesc('last_seen_at')->limit(60)->get()->map(function ($u) use ($va, $today) {
                $v = DB::table('visits')->where('date', $today)->where('user_id', $u->id)->first(['last_route', 'device', 'first_at']);

                return [
                    'id' => $u->id, 'name' => $u->name, 'role' => VisitAnalytics::ROLE_LABELS[$u->roles->first()?->name] ?? '—',
                    'school' => $u->school?->name, 'section' => $v?->last_route ? $va->sectionLabel($v->last_route) : '—',
                    'device' => $v->device ?? null, 'since' => $v?->first_at ? \App\Support\Jalali::fa(substr($v->first_at, 11, 5)) : null,
                ];
            });

        return Inertia::render('Admin/Visits', [
            'days' => $days, 'periods' => VisitAnalytics::PERIODS,
            'kpis' => $kpis, 'roles' => $roles, 'schools' => $schools, 'onlineNow' => $onlineNow,
            'series' => $va->dailySeries($days, null, true), 'hours' => $va->hours($days),
            'sections' => $va->topSections($days), 'devices' => $va->devices($days),
            'users' => $this->users($request, $va, $days),
            'filters' => $request->only('role', 'school', 'status', 'q'),
            'schoolOptions' => School::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** فهرستِ کاربران با فیلتر — صفحه‌بندی‌شده. */
    private function users(Request $request, VisitAnalytics $va, int $days)
    {
        $q = User::with('roles', 'school:id,name');
        if ($r = $request->query('role')) $q->role($r);
        if ($s = $request->query('school')) $q->where('school_id', (int) $s);
        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($x) => $x->where('name', 'like', "%$term%")->orWhere('phone', 'like', "%$term%"));
        }
        $online = now()->subMinutes(VisitTracker::ONLINE_MINUTES);
        match ($request->query('status')) {
            'online' => $q->where('last_seen_at', '>=', $online),
            'offline' => $q->where(fn ($x) => $x->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $online)),
            'inactive' => $q->where(fn ($x) => $x->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subDays(7))),
            'never' => $q->whereNull('last_seen_at')->whereNull('last_login_at'),
            default => null,
        };
        $page = $q->orderByRaw('last_seen_at IS NULL')->orderByDesc('last_seen_at')->orderBy('name')
            ->paginate(40)->withQueryString();
        $stats = $va->userVisitStats(collect($page->items())->pluck('id'), now()->subDays($days - 1)->startOfDay());
        $page->through(function ($u) use ($stats, $days, $va) {
            $v = $stats[$u->id] ?? null;

            return [
                'id' => $u->id, 'name' => $u->name, 'phone' => $u->phone,
                'role' => VisitAnalytics::ROLE_LABELS[$u->roles->first()?->name] ?? '—', 'school' => $u->school?->name,
                'online' => $u->last_seen_at && $u->last_seen_at->gte(now()->subMinutes(VisitTracker::ONLINE_MINUTES)),
                'last_seen_label' => $va->ago($u->last_seen_at), 'logins' => (int) $u->login_count,
                'days' => (int) ($v->days ?? 0), 'hits' => (int) ($v->hits ?? 0), 'minutes' => (int) round(($v->seconds ?? 0) / 60),
                'presence' => (int) round(((int) ($v->days ?? 0)) / $days * 100),
            ];
        });

        return $page;
    }
}
