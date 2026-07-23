<?php

namespace App\Http\Middleware;

use App\Services\ThemeEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            // عکسِ کاربر و برندِ مدرسه — در همه‌ی داشبوردها (سایدبار/تاپ‌بار) استفاده می‌شود
            'avatarUrl' => $user && $user->avatar ? Storage::url($user->avatar) : null,
            'school' => fn () => $user && $user->school ? [
                'name'     => $user->school->name,
                'logo_url' => $user->school->logo ? Storage::url($user->school->logo) : null,
            ] : null,
            // تم فعال در همه‌ی صفحات در دسترس است تا فرانت ظاهر را بسازد
            'theme' => app(ThemeEngine::class)->presentation($theme),
            'flash' => ['flash' => fn () => $request->session()->get('flash')],
            // فید یکپارچه‌ی اعلان‌ها (اطلاعیه + پیام + موارد انضباطی) برای زنگوله
            'notifications' => fn () => $user ? \App\Support\Notifications::feed($user) : [],
            // شمار اعلان‌های خوانده‌نشده (برای نشان روی زنگوله و منوی اعلان‌ها)
            'unreadNotices' => fn () => $user ? \App\Support\Notifications::unreadCount($user) : 0,
            // آزمایشگاه هوشمند آزمون — فقط برای گیتِ منو (خاموش = منو نمایش داده نمی‌شود)
            'smartLab' => fn () => $user ? \App\Support\SmartLab::enabledFor($user) : false,
        ];
    }
}
