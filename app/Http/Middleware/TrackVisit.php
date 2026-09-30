<?php

namespace App\Http\Middleware;

use App\Support\VisitTracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** بعد از ساختنِ پاسخ، دیدنِ صفحه را ثبت می‌کند (فقط پاسخ‌های موفقِ GET). */
class TrackVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() < 400 && ! $response->isRedirection() && VisitTracker::countable($request)) {
            VisitTracker::hit($request);
        }

        return $response;
    }
}
