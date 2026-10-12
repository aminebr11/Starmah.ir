<?php

namespace App\Http\Middleware;

use App\Support\AutoMigrate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** پیش از هر درخواستِ وب: مایگریشن‌های تازه یک‌بار اجرا و کشِ کهنه‌ی مسیرها پاک شود. */
class ApplyPendingMigrations
{
    public function handle(Request $request, Closure $next): Response
    {
        AutoMigrate::ensure();
        // کشِ مسیرهای قدیمی‌تر از فایل‌های تازه، مسیرهای جدید را پنهان می‌کرد
        \App\Support\StaleCaches::sweep();

        return $next($request);
    }
}
