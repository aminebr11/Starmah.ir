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
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\EnsurePasswordChanged::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

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
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
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
