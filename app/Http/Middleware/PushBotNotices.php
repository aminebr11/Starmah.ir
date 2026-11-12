<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 🔔 اعلانِ فوری در بات: پس از هر کارِ ثبت‌شده در سایت (POST/PUT/…) — بعد از فرستادنِ پاسخ،
 * پس کاربر منتظر نمی‌ماند — اعلان‌های تازه‌ی همان مدرسه به «بله»/«تلگرام» فرستاده می‌شود.
 * درخواست‌های پرتکرار و بی‌اعلان (ذخیره‌ی خودکار، ضربانِ کلاسِ آنلاین، پیش‌نمایش…) نادیده گرفته می‌شوند.
 */
class PushBotNotices
{
    private const SKIP = '/(save|autosave|heartbeat|ping|presence|progress|track|visit|beacon|preview|\.ai$|^bot\.|^notifications\.|login|logout|password|^figures)/i';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($request->isMethodSafe() || $response->getStatusCode() >= 400) {
            return $response;
        }
        $name = (string) ($request->route()?->getName() ?? '');
        if ($name !== '' && preg_match(self::SKIP, $name)) {
            return $response;
        }
        $school = $request->user()?->school_id;
        \Illuminate\Support\defer(fn () => rescue(fn () => \App\Services\Bot\BotPush::afterWrite($school ? (int) $school : null), null, true));

        return $response;
    }
}
