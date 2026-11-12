<?php

namespace App\Support\Bot;

use App\Models\Setting;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Support\Str;

/**
 * تنظیماتِ باتِ معلم (ادمینِ کل) + روشن/خاموشِ هر مدرسه (مدیرِ مدرسه).
 *
 *  بله:    مستقیم از سرورِ داخلِ ایران (tapi.bale.ai).
 *  تلگرام: سرورِ داخلِ ایران به api.telegram.org دسترسی ندارد؛ «نشانیِ API» را نشانیِ یک واسطه
 *          (مثلاً Cloudflare Worker) بگذارید که درخواست‌ها را به تلگرام می‌رساند — docs/bot-relay.
 */
class BotConfig
{
    public const DEFAULT_API = ['bale' => 'https://tapi.bale.ai', 'telegram' => 'https://api.telegram.org'];

    public static function get(string $key, $default = null)
    {
        return Setting::get('bot_' . $key, $default);
    }

    public static function put(string $key, $value): void
    {
        Setting::put('bot_' . $key, (string) $value);
    }

    public static function enabled(): bool
    {
        return (bool) self::get('enabled', false);
    }

    public static function token(string $platform): string
    {
        return trim((string) self::get($platform . '_token', ''));
    }

    public static function apiBase(string $platform): string
    {
        return rtrim(trim((string) self::get($platform . '_api', '')) ?: self::DEFAULT_API[$platform], '/');
    }

    /** نامِ کاربریِ بات (برای لینکِ اتصال: ble.ir/… یا t.me/…). */
    public static function username(string $platform): string
    {
        return ltrim(trim((string) self::get($platform . '_username', '')), '@');
    }

    public static function ready(string $platform): bool
    {
        return self::enabled() && self::token($platform) !== '';
    }

    /** رازِ نشانیِ وب‌هوک (یک‌بار ساخته می‌شود). */
    public static function secret(): string
    {
        $s = (string) self::get('secret', '');
        if ($s === '') {
            $s = Str::random(32);
            self::put('secret', $s);
        }

        return $s;
    }

    /** نشانیِ دریافتِ پیام‌ها روی همین سایت (برای تلگرام اگر واسطه گذاشته‌اید، نشانیِ واسطه). */
    public static function webhookUrl(string $platform): string
    {
        $override = trim((string) self::get($platform . '_webhook', ''));
        if ($override !== '') {
            return rtrim($override, '/') . '/' . self::secret();
        }
        return self::siteUrl() . '/bot/' . $platform . '/' . self::secret();
    }

    /**
     * نشانیِ عمومیِ سایت: تنظیمِ ادمین ← نشانیِ همین درخواست ← APP_URL.
     * (APP_URL روی هاست معمولاً «http://localhost» مانده بود و پیام‌رسان نمی‌توانست به آن پیام بدهد.)
     */
    public static function siteUrl(): string
    {
        $set = trim((string) self::get('site_url', ''));
        if ($set !== '') {
            return rtrim($set, '/');
        }
        $req = app()->bound('request') && ! app()->runningInConsole() ? request()->getSchemeAndHttpHost() : '';
        if ($req !== '' && ! self::isLocal($req)) {
            return rtrim($req, '/');
        }

        return rtrim((string) config('app.url'), '/');
    }

    public static function isLocal(string $url): bool
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        return $host === '' || $host === 'localhost' || str_ends_with($host, '.test') || filter_var($host, FILTER_VALIDATE_IP) !== false;
    }

    /** آیا این کاربر (با نقش و مدرسه‌اش) اجازه‌ی استفاده از بات را دارد؟ */
    public static function allowed(User $user): bool
    {
        if (! self::enabled() || ! $user->hasAnyRole([Roles::TEACHER, Roles::SCHOOL_ADMIN, Roles::SUPER_ADMIN])) {
            return false;
        }
        if ($user->hasRole(Roles::SUPER_ADMIN)) {
            return true;   // ادمینِ کل: اعلان‌ها و گزارش‌ها
        }

        return $user->school_id ? self::schoolEnabled((int) $user->school_id) : $user->hasRole(Roles::SCHOOL_ADMIN);
    }

    public static function schoolEnabled(int $schoolId): bool
    {
        $v = Setting::get('bot:school:' . $schoolId);

        return $v === null || $v === '' ? true : (bool) (int) $v;
    }

    public static function setSchool(int $schoolId, bool $on): void
    {
        Setting::put('bot:school:' . $schoolId, $on ? '1' : '0');
    }
}
