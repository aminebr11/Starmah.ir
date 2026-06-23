<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** اگر کاربر باید رمزش را عوض کند، تا تغییر، فقط به صفحه‌ی تغییر رمز دسترسی دارد. */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && $user->must_change_password
            && ! $request->routeIs('password.force', 'password.force.update', 'logout')) {
            return redirect()->route('password.force');
        }
        return $next($request);
    }
}
