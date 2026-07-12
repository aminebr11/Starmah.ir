<?php

namespace App\Http\Middleware;

use App\Support\SmartLab;
use Closure;
use Illuminate\Http\Request;

/** دروازه‌ی دسترسی به «آزمایشگاه هوشمند آزمون» — وقتی خاموش است مسیرها ۴۰۴ می‌شوند. */
class EnsureSmartLab
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(SmartLab::enabledFor($request->user()), 404);
        return $next($request);
    }
}
