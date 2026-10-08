<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Schema::hasTable / hasColumn با حافظه.
 *
 * روی MySQLِ هاست‌های اشتراکی هر بررسی یک کوئری روی information_schema است که کند است
 * و چند بار در هر درخواست تکرار می‌شد. پاسخِ «هست» یک‌بار در فایلی کوچک ذخیره می‌شود
 * (جدول/ستون پاک نمی‌شود)؛ پاسخِ «نیست» ذخیره نمی‌شود تا بعد از مایگریشن فوراً دیده شود.
 * بعد از هر اجرای مایگریشن فایل پاک می‌شود (AutoMigrate::run).
 */
class DbSchema
{
    private static ?array $known = null;

    private static function file(): string
    {
        return storage_path('framework/cache/db-schema.php');
    }

    private static function load(): array
    {
        if (self::$known === null) {
            $f = self::file();
            $data = is_file($f) ? @include $f : [];
            // کلیدِ پایگاه‌داده: اگر .env به پایگاهِ دیگری اشاره کند، حافظه‌ی قبلی معتبر نیست
            self::$known = is_array($data) && ($data['_db'] ?? null) === self::db() ? $data : ['_db' => self::db()];
        }

        return self::$known;
    }

    private static function db(): string
    {
        return config('database.default') . ':' . config('database.connections.' . config('database.default') . '.database');
    }

    private static function remember(string $key): void
    {
        self::$known[$key] = true;
        $f = self::file();
        @mkdir(dirname($f), 0775, true);
        $tmp = $f . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, '<?php return ' . var_export(self::$known, true) . ';') !== false) {
            @rename($tmp, $f);
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($f, true);
            }
        }
    }

    public static function hasTable(string $table): bool
    {
        $k = 't:' . $table;
        if (isset(self::load()[$k])) {
            return true;
        }
        $ok = (bool) rescue(fn () => Schema::hasTable($table), false, false);
        if ($ok) {
            self::remember($k);
        }

        return $ok;
    }

    public static function hasColumn(string $table, string $column): bool
    {
        $k = 'c:' . $table . '.' . $column;
        if (isset(self::load()[$k])) {
            return true;
        }
        $ok = (bool) rescue(fn () => Schema::hasColumn($table, $column), false, false);
        if ($ok) {
            self::remember($k);
            self::remember('t:' . $table);
        }

        return $ok;
    }

    public static function forget(): void
    {
        self::$known = null;
        @unlink(self::file());
    }
}
