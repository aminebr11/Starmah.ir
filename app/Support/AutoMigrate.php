<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * اجرای خودکارِ مایگریشن‌های تازه — یک‌بار پس از هر به‌روزرسانی.
 *
 * به‌روزرسانی‌ها روی هاست با ریختنِ فایل نصب می‌شوند و اگر مایگریشن اجرا نشود،
 * صفحه‌هایی که جدول/ستونِ تازه می‌خواهند «خطای ۵۰۰» می‌دهند. اینجا فهرستِ فایل‌های
 * مایگریشن اثرانگشت گرفته می‌شود؛ اگر برای این اثرانگشت قبلاً بررسی نشده باشد،
 * مایگریشن‌های اجرانشده (همه افزایشی‌اند) با قفلِ فایلی فقط یک‌بار اجرا می‌شوند.
 * هزینه‌ی درخواست‌های بعدی فقط یک file_exists است. به cache/DB وابسته نیست.
 */
class AutoMigrate
{
    private static bool $checked = false;

    public static function signature(): string
    {
        $files = glob(database_path('migrations/*.php')) ?: [];

        // پایگاه‌داده هم در اثرانگشت است: اگر .env به پایگاهِ دیگری اشاره کند، دوباره بررسی می‌شود
        $db = config('database.default') . ':' . config('database.connections.' . config('database.default') . '.database');

        return substr(md5(implode('|', array_map('basename', $files)) . '|' . $db), 0, 12);
    }

    private static function flag(string $sig): string
    {
        return storage_path("framework/migrated-{$sig}");
    }

    /** بررسیِ سریع؛ فقط اگر لازم باشد مایگریشن اجرا می‌شود. هرگز خطا پرتاب نمی‌کند. */
    public static function ensure(bool $retry = false): void
    {
        if (self::$checked && ! $retry) {
            return;
        }
        self::$checked = true;

        try {
            $sig = self::signature();
            $flag = self::flag($sig);
            if (is_file($flag) && ! $retry) {
                return;
            }
            // پس از شکست، تا ۱۰ دقیقه دوباره تلاش نکن (درخواست‌ها کند نشوند)؛ تلاشِ دوباره‌ی صفحه‌ای که جدولش نیست: ۱ دقیقه
            $fail = $flag . '.fail';
            if (is_file($fail) && time() - filemtime($fail) < ($retry ? 60 : 600)) {
                return;
            }

            $lock = @fopen($flag . '.lock', 'c');
            if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
                return; // درخواستِ دیگری در حالِ اجراست
            }
            try {
                if (self::pending() === []) {
                    @file_put_contents($flag, date('c') . " nothing pending\n");

                    return;
                }
                $res = self::run();
                if ($res['ok']) {
                    @file_put_contents($flag, date('c') . "\n" . $res['output'] . "\n");
                    @unlink($fail);
                    Log::info('[auto-migrate] pending migrations applied', ['output' => $res['output']]);
                } else {
                    @file_put_contents($fail, date('c') . "\n" . self::describe($res) . "\n");
                    Log::error('[auto-migrate] migrate did not finish', ['failed' => $res['failed'], 'output' => $res['output']]);
                }
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        } catch (\Throwable $e) {
            @file_put_contents(self::flag(self::signature()) . '.fail', date('c') . "\n" . $e->getMessage() . "\n");
            Log::error('[auto-migrate] ' . $e->getMessage());
        }
    }

    /**
     * اجرای مایگریشن‌های باقی‌مانده. اگر اجرای یک‌جا شکست بخورد، هر مایگریشن جداگانه
     * اجرا می‌شود تا یک مایگریشنِ مشکل‌دار جلوی بقیه (مثلاً جدول‌های مرور) را نگیرد.
     *
     * @return array{ok:bool,output:string,failed:array<string,string>,pending:array}
     */
    public static function run(): array
    {
        @set_time_limit(300);
        $out = [];
        try {
            Artisan::call('migrate', ['--force' => true]);
            $out[] = trim(Artisan::output());
        } catch (\Throwable $e) {
            $out[] = 'migrate: ' . mb_substr($e->getMessage(), 0, 600);
        }
        $failed = [];
        $pending = self::pending();
        if ($pending && $pending !== ['(migrations table)']) {
            $files = app('migrator')->getMigrationFiles(database_path('migrations'));
            foreach ($pending as $name) {
                if (empty($files[$name])) {
                    continue;
                }
                try {
                    Artisan::call('migrate', ['--force' => true, '--path' => $files[$name], '--realpath' => true]);
                    $o = trim(Artisan::output());
                    if (in_array($name, self::pending(), true)) {
                        $failed[$name] = mb_substr($o, 0, 600);
                    } else {
                        $out[] = $name . ' DONE';
                    }
                } catch (\Throwable $e) {
                    $failed[$name] = mb_substr($e->getMessage(), 0, 600);
                }
            }
        }
        $left = self::pending();

        return ['ok' => $left === [], 'output' => implode("\n", array_filter($out)), 'failed' => $failed, 'pending' => $left];
    }

    /** متنِ کوتاهِ نتیجه برای فایلِ شکست و «سلامتِ سیستم». */
    public static function describe(array $res): string
    {
        $lines = [];
        foreach ($res['failed'] as $name => $msg) {
            $lines[] = "✗ {$name}\n   {$msg}";
        }
        if (! $lines && $res['pending']) {
            $lines[] = 'pending: ' . implode(', ', $res['pending']) . "\n" . $res['output'];
        }

        return implode("\n", $lines);
    }

    /** آخرین شکستِ اجرای خودکار (برای «سلامتِ سیستم»)، یا null. */
    public static function lastFailure(): ?array
    {
        $files = glob(storage_path('framework/migrated-*.fail')) ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        if (! $files) {
            return null;
        }

        return ['at' => date('Y-m-d H:i', filemtime($files[0])), 'text' => mb_substr((string) @file_get_contents($files[0]), 0, 2000)];
    }

    /** نامِ مایگریشن‌های اجرانشده. */
    public static function pending(): array
    {
        $migrator = app('migrator');
        if (! $migrator->repositoryExists()) {
            return ['(migrations table)'];
        }
        $files = $migrator->getMigrationFiles(database_path('migrations'));
        $ran = $migrator->getRepository()->getRan();

        return array_values(array_diff(array_keys($files), $ran));
    }

    /** بعد از خطای «جدول/ستون نیست»، دوباره بررسی شود. */
    public static function forget(): void
    {
        @unlink(self::flag(self::signature()));
        @unlink(self::flag(self::signature()) . '.fail');
    }
}
