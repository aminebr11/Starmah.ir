<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * طرحِ ظاهری (پوسته‌ی رابط کاربری).
 *
 *   classic → طرحِ فعلیِ سایت (سرمه‌ای/شب)
 *   clay    → «خمیرماه»: روشن، خمیری و بچه‌پسند
 *
 * ترتیبِ تصمیم: ?ui= در نشانی ← کوکیِ sm_ui (انتخابِ خودِ کاربر) ←
 * پیش‌فرضِ سامانه (Setting ui_default). برای مهاجرتِ کامل کافی است
 * پیش‌فرض را clay کنیم؛ کاربری که «طرحِ قبلی» را انتخاب کرده همان را می‌بیند.
 */
class Ui
{
    public const SKINS = ['classic', 'clay'];

    public static function current(?Request $request = null): string
    {
        $request ??= request();
        $q = (string) $request->query('ui', '');
        if (in_array($q, self::SKINS, true)) {
            return $q;
        }
        $c = (string) $request->cookie('sm_ui', '');
        if (in_array($c, self::SKINS, true)) {
            return $c;
        }
        try {
            $d = (string) Setting::get('ui_default', 'clay');
        } catch (\Throwable $e) {
            $d = 'clay';
        }

        return in_array($d, self::SKINS, true) ? $d : 'clay';
    }
}
