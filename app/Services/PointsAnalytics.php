<?php

namespace App\Services;

use App\Models\User;
use App\Support\Jalali;
use App\Support\Roles;
use App\Support\SchoolCalendar;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * تحلیلِ امتیازها بر پایه‌ی تراکنش‌های دفترکلِ XP (xp_ledger).
 *
 * همه‌جا «هفته» یعنی شنبه تا جمعه و «سالِ تحصیلی» یعنی ۱ مهر تا ۳۱ شهریور
 * (SchoolCalendar). امتیازِ یک بازه = جمعِ تراکنش‌های همان بازه، نه مجموعِ کل.
 */
class PointsAnalytics
{
    /** زیرپرسشِ شناسه‌ی دانش‌آموزان (نقشِ student). */
    private function studentIds()
    {
        return DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', Roles::STUDENT)->where('model_has_roles.model_type', User::class)
            ->select('model_has_roles.model_id');
    }

    /**
     * برترین‌های یک بازه. positiveOnly: فقط تراکنش‌های مثبت (برای برترین‌های هفته)،
     * وگرنه جمعِ خالص (مثبت منهای منفی) که معیارِ منصفانه‌ترِ «امتیازِ دوره» است.
     *
     * @param  array<int>|null  $ids  محدودکردن به چند دانش‌آموز (کلاس/مدرسه)
     */
    public function top(?Carbon $from, ?Carbon $to, int $limit = 5, bool $positiveOnly = true, ?array $ids = null): array
    {
        $rows = DB::table('xp_ledger')
            ->when($positiveOnly, fn ($q) => $q->where('amount', '>', 0))
            ->when($ids !== null, fn ($q) => $q->whereIn('student_id', $ids), fn ($q) => $q->whereIn('student_id', $this->studentIds()))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->groupBy('student_id')
            ->select('student_id', DB::raw('SUM(amount) as xp'), DB::raw('COUNT(*) as n'))
            ->orderByDesc('xp')->orderBy('student_id')->limit($limit)->get()
            ->filter(fn ($r) => $r->xp > 0)->values();

        if ($rows->isEmpty()) {
            return [];
        }

        $users = User::with(['school:id,name', 'theme:id,name,emoji', 'classrooms:id,name,grade'])
            ->whereIn('id', $rows->pluck('student_id'))->get()->keyBy('id');

        $out = [];
        foreach ($rows as $i => $r) {
            $u = $users[$r->student_id] ?? null;
            if (! $u) continue;
            $out[] = [
                'rank' => count($out) + 1,
                'id' => $u->id,
                'name' => $u->name,
                'avatar' => $u->avatar_url,
                'school' => $u->school?->name,
                'class' => $u->classrooms->first()?->name,
                'team' => $u->theme ? trim(($u->theme->emoji ?? '') . ' ' . $u->theme->name) : null,
                'xp' => (int) $r->xp,
                'entries' => (int) $r->n,
            ];
        }

        return $out;
    }

    /** برترین‌های هفته‌ی جاری + مشخصاتِ هفته. */
    public function weekTop(int $limit = 5): array
    {
        $w = SchoolCalendar::week(now());

        return ['week' => \Illuminate\Support\Arr::except($w, ['from', 'to']), 'rows' => $this->top($w['from'], $w['to'], $limit)];
    }

    /** برترین‌های کلِ سالِ تحصیلی. */
    public function yearTop(?int $jy = null, int $limit = 5): array
    {
        $jy ??= SchoolCalendar::schoolYearOf();

        return [
            'year' => $jy,
            'label' => SchoolCalendar::yearLabel($jy),
            'rows' => $this->top(SchoolCalendar::yearStart($jy), SchoolCalendar::yearEnd($jy), $limit),
        ];
    }

    /**
     * روندِ هفته‌به‌هفته‌ی یک دانش‌آموز در سالِ تحصیلی:
     * امتیازِ هر هفته، جمعِ تجمعی، رتبه در کلاس و رتبه در مدرسه.
     *
     * رتبه بر پایه‌ی جمعِ تجمعیِ امتیاز از ابتدای سال است (نه فقط همان هفته)،
     * چون سؤال «رتبه در کلاس از ابتدای سال» همین را می‌خواهد.
     */
    public function studentTrend(User $student, ?int $jy = null): array
    {
        $jy ??= SchoolCalendar::schoolYearOf();
        $weeks = SchoolCalendar::weeksSoFar($jy);
        if (! $weeks) return ['year' => $jy, 'label' => SchoolCalendar::yearLabel($jy), 'points' => [], 'summary' => []];

        $classroom = $student->classrooms()->first();
        $classIds = $classroom ? $classroom->students()->pluck('users.id')->all() : [$student->id];
        $schoolIds = $student->school_id
            ? User::role(Roles::STUDENT)->where('school_id', $student->school_id)->pluck('id')->all()
            : $classIds;
        $pool = array_values(array_unique(array_merge($classIds, $schoolIds, [$student->id])));

        $start = SchoolCalendar::yearStart($jy);
        $rows = DB::table('xp_ledger')->whereIn('student_id', $pool)
            ->where('created_at', '>=', $start)->where('created_at', '<=', SchoolCalendar::yearEnd($jy))
            ->select('student_id', 'amount', 'created_at')->get();

        // جمعِ هر دانش‌آموز در هر هفته
        $byWeek = [];
        foreach ($rows as $r) {
            $k = SchoolCalendar::weekStart(Carbon::parse($r->created_at))->toDateString();
            $byWeek[$k][$r->student_id] = ($byWeek[$k][$r->student_id] ?? 0) + (int) $r->amount;
        }

        $cum = [];     // جمعِ تجمعیِ هر دانش‌آموز
        $points = [];
        $rankOf = function (array $cum, array $ids, int $me): array {
            $vals = [];
            foreach ($ids as $id) $vals[$id] = $cum[$id] ?? 0;
            arsort($vals);
            $rank = 1; $i = 0; $prev = null;
            foreach ($vals as $id => $v) {
                $i++;
                if ($prev === null || $v < $prev) { $rank = $i; $prev = $v; }
                if ($id === $me) return [$rank, count($vals)];
            }

            return [count($vals), count($vals)];
        };

        foreach ($weeks as $w) {
            $week = $byWeek[$w['key']] ?? [];
            foreach ($week as $sid => $amt) $cum[$sid] = ($cum[$sid] ?? 0) + $amt;
            [$cRank, $cTotal] = $rankOf($cum, $classIds, $student->id);
            [$sRank, $sTotal] = $rankOf($cum, $schoolIds, $student->id);
            $points[] = [
                'n' => $w['n'],
                'label' => $w['short'],
                'range' => $w['range'],
                'tick' => $w['tick'],
                'xp' => (int) ($week[$student->id] ?? 0),
                'total' => (int) ($cum[$student->id] ?? 0),
                'class_rank' => $cRank, 'class_size' => $cTotal,
                'school_rank' => $sRank, 'school_size' => $sTotal,
                'class_avg' => count($classIds) ? (int) round(array_sum(array_map(fn ($id) => $cum[$id] ?? 0, $classIds)) / count($classIds)) : 0,
            ];
        }

        $last = end($points) ?: null;
        $best = collect($points)->sortByDesc('xp')->first();
        $bestRank = collect($points)->min('class_rank');

        return [
            'year' => $jy,
            'label' => SchoolCalendar::yearLabel($jy),
            'points' => $points,
            'summary' => [
                'total' => $last['total'] ?? 0,
                'week_xp' => $last['xp'] ?? 0,
                'class_rank' => $last['class_rank'] ?? null,
                'class_size' => $last['class_size'] ?? 0,
                'school_rank' => $last['school_rank'] ?? null,
                'school_size' => $last['school_size'] ?? 0,
                'best_week' => $best && $best['xp'] > 0 ? ['label' => $best['label'], 'range' => $best['range'], 'xp' => $best['xp']] : null,
                'best_rank' => $bestRank,
                'classroom' => $classroom?->name,
                // تغییرِ رتبه نسبت به هفته‌ی قبل (مثبت = بهتر شده)
                'rank_delta' => count($points) > 1 ? ($points[count($points) - 2]['class_rank'] - $last['class_rank']) : 0,
            ],
        ];
    }

    /**
     * جدولِ رتبه‌بندیِ دانش‌آموزان در یک بازه‌ی دلخواه (برای معلم).
     *
     * @param  array<int>  $ids
     */
    public function rankTable(array $ids, ?Carbon $from, ?Carbon $to): array
    {
        if (! $ids) return [];
        $rows = DB::table('xp_ledger')->whereIn('student_id', $ids)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->groupBy('student_id')
            ->select('student_id',
                DB::raw('SUM(amount) as xp'),
                DB::raw('SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as plus'),
                DB::raw('SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END) as minus'),
                DB::raw('COUNT(*) as n'),
                DB::raw('MAX(created_at) as last_at'))
            ->get()->keyBy('student_id');

        $users = User::with('theme:id,name,emoji')->whereIn('id', $ids)->get(['id', 'name', 'school_id']);
        $out = $users->map(function ($u) use ($rows) {
            $r = $rows->get($u->id);

            return [
                'id' => $u->id, 'name' => $u->name,
                'team' => $u->theme ? trim(($u->theme->emoji ?? '') . ' ' . $u->theme->name) : null,
                'xp' => (int) ($r->xp ?? 0),
                'plus' => (int) ($r->plus ?? 0),
                'minus' => (int) abs($r->minus ?? 0),
                'entries' => (int) ($r->n ?? 0),
                'last' => isset($r->last_at) ? Jalali::format(Carbon::parse($r->last_at)) : null,
                'last_raw' => isset($r->last_at) ? Carbon::parse($r->last_at)->timestamp : null,
            ];
        })->sortByDesc('xp')->values()->all();

        // رتبه با در نظر گرفتنِ تساوی
        $rank = 0; $i = 0; $prev = null;
        foreach ($out as $k => $row) {
            $i++;
            if ($prev === null || $row['xp'] < $prev) { $rank = $i; $prev = $row['xp']; }
            $out[$k]['rank'] = $rank;
        }

        return $out;
    }

    /**
     * بازه‌ی انتخابیِ معلم را به [from, to, label] تبدیل می‌کند.
     * preset: week | month | year | day | custom
     */
    public function resolveRange(?string $preset, ?string $from, ?string $to): array
    {
        $jy = SchoolCalendar::schoolYearOf();
        $parse = fn ($d) => $d ? Carbon::parse($d, config('app.timezone')) : null;

        return match ($preset) {
            'day' => (function () use ($parse, $from) {
                $d = ($parse($from) ?: now())->startOfDay();

                return ['from' => $d, 'to' => $d->copy()->endOfDay(), 'label' => Jalali::format($d, true)];
            })(),
            'month' => (function () use ($parse, $from, $jy) {
                $d = $parse($from) ?: now();
                $m = collect(SchoolCalendar::months($jy))->first(fn ($x) => $d->between($x['from'], $x['to']))
                    ?? collect(SchoolCalendar::months(SchoolCalendar::schoolYearOf($d)))->first(fn ($x) => $d->between($x['from'], $x['to']));

                return ['from' => $m['from'], 'to' => $m['to'], 'label' => 'ماهِ ' . $m['label']];
            })(),
            'year' => ['from' => SchoolCalendar::yearStart($jy), 'to' => SchoolCalendar::yearEnd($jy), 'label' => 'سالِ تحصیلیِ ' . SchoolCalendar::yearLabel($jy)],
            'all' => ['from' => null, 'to' => null, 'label' => 'کلِ دوره'],
            'custom' => (function () use ($parse, $from, $to) {
                $f = $parse($from)?->startOfDay();
                $t = $parse($to)?->endOfDay() ?: now();

                return ['from' => $f, 'to' => $t, 'label' => 'از ' . ($f ? Jalali::format($f) : 'ابتدا') . ' تا ' . Jalali::format($t)];
            })(),
            default => (function () use ($parse, $from) {
                $w = SchoolCalendar::week($parse($from) ?: now());

                return ['from' => $w['from'], 'to' => $w['to'], 'label' => $w['title'] . ' — ' . $w['range']];
            })(),
        };
    }
}
