<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * درخواست‌های axios (نه Inertia) که JSON می‌خواهند، اگر جایی در مسیر «ریدایرکت»
 * شوند (ورودِ دوباره، تغییرِ اجباریِ رمز، back() پس از خطا…)، مرورگر بی‌صدا ریدایرکت را
 * دنبال می‌کرد و یک صفحه‌ی HTML می‌گرفت — طراحیِ سؤال پیامِ گنگ نشان می‌داد.
 * اینجا ریدایرکت به یک پاسخِ JSONِ روشن تبدیل می‌شود که می‌گوید به کجا فرستاده شده بود.
 */
class JsonRedirectsForAjax
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') || $request->header('X-Inertia') || ! $request->expectsJson() || ! $response->isRedirection()) {
            return $response;
        }

        $to = (string) $response->headers->get('Location');
        $path = parse_url($to, PHP_URL_PATH) ?: '/';
        $msg = match (true) {
            str_starts_with($path, '/login') => 'نشستِ شما منقضی شده یا از حساب خارج شده‌اید؛ دوباره وارد شوید و دوباره تلاش کنید.',
            str_contains($path, 'force-password') || str_contains($path, 'password') => 'پیش از ادامه باید رمزِ عبورتان را تغییر دهید (صفحه را تازه کنید).',
            str_contains($path, 'verify') => 'ابتدا باید حساب‌تان تأیید شود.',
            default => 'سرور درخواست را به نشانیِ «' . $path . '» برگرداند و انجام نشد. صفحه را تازه کنید و دوباره تلاش کنید.',
        };
        $errs = session('errors');
        $flash = match (true) {
            $errs instanceof \Illuminate\Support\ViewErrorBag, $errs instanceof \Illuminate\Support\MessageBag => $errs->first(),
            is_array($errs) => (string) collect($errs)->flatten()->first(),
            default => null,
        } ?: session('toast');

        return response()->json([
            'ok' => false, 'mode' => 'redirect', 'questions' => [],
            'redirect' => $path,
            'message' => $flash ? $msg . ' (' . $flash . ')' : $msg,
        ], 200);
    }
}
