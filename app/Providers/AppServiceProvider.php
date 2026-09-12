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
