<?php

namespace App\Http\Middleware;

use App\Services\WellbeingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * «🌿 سلامتِ دیجیتال»: وقتی سقفِ زمانِ دانش‌آموز پر شد، به صفحه‌ی «وقتِ استراحت» می‌رود
 * تا والدین با رمز باز کنند (یا بعد از استراحت/فردا خودش باز شود).
 */
class EnforceScreenTime
{
    /** مسیرهایی که همیشه باز می‌مانند (خروج، بخشِ والدین، مسابقه‌ی کلاسی، فایل‌ها…). */
    private const OPEN = ['time-up', 'logout', 'presence', 'notices/pulse', 'family', 'live', 'build/', 'storage/', 'up', 'sw.js', 'manifest', 'install', 'login', 'favicon'];

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = $request->user();
            $isStudent = $user && $user->isStudent();
        } catch (\Throwable) {
            $isStudent = false;
        }
        if (! $isStudent) {
            return $next($request);
        }
        $path = $request->path();
        foreach (self::OPEN as $p) {
            if ($path === rtrim($p, '/') || str_starts_with($path, str_ends_with($p, '/') ? $p : $p . '/')) {
                return $next($request);
            }
        }
        $locked = rescue(fn () => app(WellbeingService::class)->locked($user), null, false);
        if (! $locked) {
            return $next($request);
        }
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => 'وقتِ استفاده‌ی امروز تمام شده است. برای ادامه، والدین باید با رمز باز کنند.', 'locked' => true], 423);
        }

        return redirect()->route('time-up');
    }
}
