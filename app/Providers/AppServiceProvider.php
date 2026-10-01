<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // مصرفِ توکنِ هوش مصنوعی از پاسخِ همه‌ی سرویس‌ها
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Http\Client\Events\ResponseReceived::class, [\App\Support\AiUsage::class, 'handle']);

        // آخرین ورود و شمارِ ورودها — برای گزارشِ بازدید
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, function ($e) {
            try {
                \Illuminate\Support\Facades\DB::table('users')->where('id', $e->user->getAuthIdentifier())->update([
                    'last_login_at' => now(), 'last_seen_at' => now(), 'login_count' => \Illuminate\Support\Facades\DB::raw('login_count + 1'),
                ]);
            } catch (\Throwable $x) {
                report($x); // ورود هرگز نباید به‌خاطرِ آمار شکست بخورد
            }
        });

        // Vite::prefetch عمداً خاموش است.
        //
        // به‌صورتِ پیش‌فرض برای **همه‌ی** فایل‌های build (اینجا ۱۱۶ تکه،
        // نزدیکِ ۱.۷ مگابایت، شاملِ three.js و صفحه‌های معلم و ادمین) تگِ
        // prefetch می‌گذاشت — حتی برای مهمانی که فقط صفحه‌ی اول را باز کرده.
        // روی موبایل و اینترنتِ همراه این یعنی مصرفِ بی‌هوده‌ی حجم و اشغالِ
        // پهنای باند درست همان لحظه‌ای که کاربر می‌خواهد روی دکمه بزند.
        // هر صفحه همچنان تکه‌ی خودش را هنگامِ نیاز می‌گیرد (۱۵ تا ۳۰ کیلوبایت).
    }
}
