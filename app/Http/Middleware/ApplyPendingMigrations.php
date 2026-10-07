<?php

namespace App\Http\Middleware;

use App\Support\AutoMigrate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** پیش از هر درخواستِ وب: اگر به‌روزرسانیِ تازه مایگریشنِ اجرانشده دارد، یک‌بار اجرا شود. */
class ApplyPendingMigrations
{
    public function handle(Request $request, Closure $next): Response
    {
        AutoMigrate::ensure();

        return $next($request);
    }
}
