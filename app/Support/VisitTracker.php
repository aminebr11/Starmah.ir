<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ثبتِ بازدیدها و وضعیتِ آنلاین.
 *
 * هر بازدیدکننده روزی یک ردیف دارد و با هر صفحه فقط شمارنده‌ها بالا می‌روند،
 * پس جدول حتی روی هاستِ اشتراکی سبک می‌ماند. زمانِ فعال از فاصله‌ی بینِ
 * دو رویدادِ پشتِ‌سرِهم (صفحه یا ضربانِ حضور) حساب می‌شود، اگر کمتر از ۵ دقیقه باشد.
 */
class VisitTracker
{
    /** کسی که در این چند دقیقه‌ی اخیر دیده شده «آنلاین» است. */
    public const ONLINE_MINUTES = 5;

    private const IDLE_GAP = 300;

    public static function hit(Request $request, bool $page = true): void
    {
        try {
            self::record($request, $page);
        } catch (\Throwable $e) {
            // آمارِ بازدید هرگز نباید صفحه را از کار بیندازد
            report($e);
        }
    }

    private static function record(Request $request, bool $page): void
    {
        $user = $request->user();
        $now = now();
        $date = $now->toDateString();
        $visitor = $user ? 'u' . $user->id : 'g' . substr(sha1($request->ip() . '|' . $request->userAgent()), 0, 24);
        $role = $user ? ($user->roles->first()?->name ?? 'user') : 'guest';
        $schoolId = $user?->school_id;
        $route = substr((string) ($request->route()?->getName() ?? 'other'), 0, 80);

        DB::table('visits')->insertOrIgnore([
            'date' => $date, 'visitor' => $visitor, 'user_id' => $user?->id, 'school_id' => $schoolId,
            'role' => $role, 'hits' => 0, 'seconds' => 0, 'device' => self::device($request),
            'first_at' => $now, 'last_at' => $now,
        ]);

        $row = DB::table('visits')->where('date', $date)->where('visitor', $visitor)->first(['id', 'last_at']);
        if (! $row) return;
        $gap = $now->getTimestamp() - strtotime($row->last_at);
        $add = ($gap > 0 && $gap <= self::IDLE_GAP) ? $gap : 0;

        $set = ['last_at' => $now, 'seconds' => DB::raw('seconds + ' . (int) $add)];
        if ($page) {
            $set['hits'] = DB::raw('hits + 1');
            $set['last_route'] = $route;
        }
        DB::table('visits')->where('id', $row->id)->update($set);

        if ($page) {
            self::bump('visit_pages', ['date' => $date, 'route' => $route, 'role' => $role, 'school_id' => (int) $schoolId]);
            self::bump('visit_hours', ['date' => $date, 'hour' => (int) $now->format('G'), 'school_id' => (int) $schoolId]);
        }

        // «آخرین حضور» روی خودِ کاربر — حداکثر دقیقه‌ای یک بار نوشته می‌شود
        if ($user && (! $user->last_seen_at || $now->diffInSeconds($user->last_seen_at, true) >= 60)) {
            DB::table('users')->where('id', $user->id)->update(['last_seen_at' => $now]);
        }
    }

    private static function bump(string $table, array $key): void
    {
        DB::table($table)->insertOrIgnore($key + ['hits' => 0]);
        DB::table($table)->where($key)->increment('hits');
    }

    public static function device(Request $request): string
    {
        $ua = (string) $request->userAgent();
        if (str_contains($ua, 'StarmahApp')) return 'app';
        if (preg_match('/Mobile|Android|iPhone|iPad/i', $ua)) return 'mobile';
        return 'desktop';
    }

    /** آیا این درخواست یک «دیدنِ صفحه» است که باید شمرده شود؟ */
    public static function countable(Request $request): bool
    {
        if (! $request->isMethod('GET')) return false;
        if ($request->header('X-Inertia-Partial-Data') || $request->header('Purpose') === 'prefetch'
            || $request->header('X-Moz') === 'prefetch' || $request->header('Sec-Purpose')) return false;
        if ($request->expectsJson() && ! $request->header('X-Inertia')) return false;
        if (preg_match('/bot|crawl|spider|slurp|facebookexternalhit|curl|wget|python-requests/i', (string) $request->userAgent())) return false;
        $path = $request->path();
        foreach (['up', 'presence', 'notices/pulse', 'build/', 'storage/', 'sw.js', 'manifest', 'api/', 'favicon'] as $p) {
            if (str_starts_with($path, $p)) return false;
        }

        return true;
    }
}
