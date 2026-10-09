<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // پیش از همه (بیرونی‌ترین لایه): ریدایرکتِ درخواست‌های JSON را به پاسخِ JSON تبدیل کن
        $middleware->web(prepend: [
            \App\Http\Middleware\JsonRedirectsForAjax::class,
            // به‌روزرسانیِ تازه با مایگریشنِ اجرانشده → یک‌بار خودکار اجرا شود (جلوی ۵۰۰ را می‌گیرد)
            \App\Http\Middleware\ApplyPendingMigrations::class,
        ]);
        // میان‌افزارهای سراسری فقط اگر فایلشان روی سرور باشد ثبت می‌شوند؛ جاماندنِ یک فایل
        // در به‌روزرسانی نباید کلِ سایت را با «خطای ۵۰۰» از کار بیندازد.
        $middleware->web(append: array_values(array_filter([
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\EnsurePasswordChanged::class,
            \App\Http\Middleware\EnforceScreenTime::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\TrackVisit::class,
        ], fn ($class) => class_exists($class))));

        // انتخابِ طرحِ ظاهری را خودِ مرورگر هم می‌نویسد، پس رمزنگاری نمی‌شود
        $middleware->encryptCookies(except: ['sm_ui']);

        // نقش‌ها (Spatie Permission)
        $middleware->alias([
            'role'       => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'smartlab'   => \App\Http\Middleware\EnsureSmartLab::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // «جدول/ستون وجود ندارد» → دفعه‌ی بعد مایگریشن‌ها دوباره بررسی شوند
        $exceptions->report(function (\Illuminate\Database\QueryException $e) {
            if (preg_match('/1146|42S02|1054|42S22|no such table|no such column/i', $e->getMessage())) {
                \App\Support\AutoMigrate::forget();
            }
            // چیزی برنمی‌گردانیم تا گزارشِ عادیِ خطا هم انجام شود
        });

        // خطاها برای درخواست‌هایی که JSON می‌خواهند (axios، نه Inertia) هم JSON باشد.
        // قبلاً فقط api/* بود؛ پس خطای اعتبارسنجی یا نشستِ منقضی در /teacher/... به
        // «ریدایرکت» تبدیل می‌شد، مرورگر صفحه‌ی HTML می‌گرفت و طراحیِ سؤال می‌شکست.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || ($request->expectsJson() && ! $request->header('X-Inertia')),
        );

        // همه‌ی خطاها به فارسی — هم صفحه‌ی خطا، هم پاسخِ JSON.
        // پیش از این کاربر «404 NOT FOUND» یا «CSRF token mismatch.» می‌دید.
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, Request $request) {
            $status = $response->getStatusCode();
            $fa = \App\Support\ErrorMessages::for($status);
            if ($fa === null || $e instanceof \Illuminate\Validation\ValidationException) {
                return $response;
            }

            // در حالتِ توسعه، صفحه‌ی خطای کاملِ لاراول (برای رفعِ اشکال) بماند
            if ($status >= 500 && config('app.debug')) {
                return $response;
            }

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['message' => $fa['text']], $status);
            }

            // نشستِ منقضی: کاربر را به همان صفحه برگردان با پیامِ روشن
            if ($status === 419) {
                return back()->with('toast', $fa['text']);
            }

            return \Inertia\Inertia::render('Error', ['status' => $status] + $fa)
                ->toResponse($request)->setStatusCode($status);
        });
    })->create();
