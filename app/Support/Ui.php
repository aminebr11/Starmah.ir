<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * طرحِ ظاهری (پوسته‌ی رابط کاربری).
 *
 *   clay    → «خمیرماه»: روشن، خمیری و بچه‌پسند (پیش‌فرض)
 *   classic → طرحِ قدیمی (سرمه‌ای/شب)
 *
 * تصمیم با ادمینِ کل است، برای هر مدرسه جدا (ستونِ schools.ui):
 *   کاربرِ یک مدرسه  → طرحِ همان مدرسه
 *   ادمینِ کل         → می‌تواند برای پیش‌نمایش با ?ui= یا کلیدِ 🎨/🌙 جابه‌جا کند
 *   مهمان / بدونِ مدرسه → پیش‌فرضِ سامانه (Setting ui_default، خودش پیش‌فرض clay)
 * کاربرانِ عادی دیگر کلیدِ جابه‌جایی ندارند.
 */
class Ui
{
    public const SKINS = ['classic', 'clay'];

    public static function current(?Request $request = null): string
    {
        $request ??= request();
        $user = null;
        try {
            $user = $request->user();
        } catch (\Throwable $e) {
            // پیش از بالا آمدنِ نشست (مثلاً صفحه‌ی خطا) — مثلِ مهمان
        }

        if ($user && $user->hasRole(Roles::SUPER_ADMIN)) {
            $q = (string) $request->query('ui', '');
            if (in_array($q, self::SKINS, true)) {
                return $q;
            }
            $c = (string) $request->cookie('sm_ui', '');
            if (in_array($c, self::SKINS, true)) {
                return $c;
            }

            return self::fallback();
        }

        if ($user && $user->school_id) {
            try {
                $s = (string) ($user->school?->ui ?? '');
            } catch (\Throwable $e) {
                $s = '';
            }
            if (in_array($s, self::SKINS, true)) {
                return $s;
            }
        }

        return self::fallback();
    }

    private static function fallback(): string
    {
        try {
            $d = (string) Setting::get('ui_default', 'clay');
        } catch (\Throwable $e) {
            $d = 'clay';
        }

        return in_array($d, self::SKINS, true) ? $d : 'clay';
    }
}
