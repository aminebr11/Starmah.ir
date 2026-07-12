<?php

namespace App\Http\Middleware;

use App\Services\ThemeEngine;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $theme = app(ThemeEngine::class)->for($user);

        return [
            ...parent::share($request),
            'auth' => [
                'user'  => $user,
                'roles' => $user ? $user->getRoleNames() : [],
            ],
            // تم فعال در همه‌ی صفحات در دسترس است تا فرانت ظاهر را بسازد
            'theme' => app(ThemeEngine::class)->presentation($theme),
            'flash' => ['flash' => fn () => $request->session()->get('flash')],
            // فید یکپارچه‌ی اعلان‌ها (اطلاعیه + پیام + موارد انضباطی) برای زنگوله
            'notifications' => fn () => $user ? \App\Support\Notifications::feed($user) : [],
            // شمار اعلان‌های خوانده‌نشده (برای نشان روی زنگوله و منوی اعلان‌ها)
            'unreadNotices' => fn () => $user ? \App\Support\Notifications::unreadCount($user) : 0,
        ];
    }
}
