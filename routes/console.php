<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// بررسی روزانه‌ی تولدها (برای cron: php artisan birthdays:check)
Artisan::command('birthdays:check', function () {
    $svc = app(\App\Services\BirthdayService::class);
    \App\Models\School::query()->pluck('id')->each(fn ($id) => $svc->runForSchool($id));
    $this->info('Birthday check done.');
})->purpose('ارسال پیام تبریک تولد دانش‌آموزان');
