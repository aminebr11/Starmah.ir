<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * کشِ مسیرها/تنظیماتی که از فایل‌های تازه قدیمی‌تر است پاک می‌شود.
 *
 * به‌روزرسانی‌ها با ریختنِ فایل روی هاست نصب می‌شوند. اگر روی هاست یک‌بار
 * «route:cache» یا «config:cache» اجرا شده باشد، مسیرهای تازه (مثلاً
 * «assistant.hello») شناخته نمی‌شوند و صفحه‌هایی که به آن‌ها نیاز دارند
 * می‌شکنند. اینجا اگر فایلِ کش از routes/ یا config/ قدیمی‌تر باشد پاک می‌شود
 * تا از درخواستِ بعدی فایل‌های تازه خوانده شوند. هزینه: چند filemtime، فقط
 * وقتی کش وجود دارد. هرگز خطا پرتاب نمی‌کند.
 */
class StaleCaches
{
    private static bool $checked = false;

    public static function sweep(): void
    {
        if (self::$checked) {
            return;
        }
        self::$checked = true;

        try {
            $app = app();
            if ($app->routesAreCached()) {
                self::dropIfOlder($app->getCachedRoutesPath(), base_path('routes'));
            }
            if ($app->configurationIsCached()) {
                self::dropIfOlder($app->getCachedConfigPath(), config_path());
            }
        } catch (\Throwable $e) {
            Log::warning('[stale-caches] ' . $e->getMessage());
        }
    }

    private static function dropIfOlder(string $cache, string $dir): void
    {
        if (! is_file($cache)) {
            return;
        }
        $newest = 0;
        foreach (glob($dir . '/*.php') ?: [] as $f) {
            $newest = max($newest, (int) @filemtime($f));
        }
        if ($newest > (int) @filemtime($cache)) {
            @unlink($cache);
            Log::info('[stale-caches] removed outdated cache ' . basename($cache));
        }
    }
}
