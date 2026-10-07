<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * به‌جای صفحه‌ی سفیدِ «خطای ۵۰۰»، علتِ شکستِ ذخیره را به زبانِ ساده به معلم نشان
 * بده و جزئیاتِ کامل را با یک «کدِ پیگیری» در لاگ ثبت کن. همان کد در صفحه‌ی
 * «سلامتِ سیستم»ِ ادمینِ کل هم دیده می‌شود.
 */
trait FriendlySaveErrors
{
    protected function saveFailed(\Throwable $e, string $what): RedirectResponse
    {
        $ref = strtoupper(Str::random(6));
        Log::error("[save-failed {$ref}] {$what}: " . $e->getMessage(), [
            'exception' => get_class($e),
            'file' => $e->getFile() . ':' . $e->getLine(),
            'user' => auth()->id(),
        ]);

        return back()->withErrors([
            '_server' => "{$what} ذخیره نشد — " . self::explainError($e) . " (کدِ پیگیری: {$ref})",
        ]);
    }

    /** توضیحِ فارسیِ رایج‌ترین خطاهای پایگاه‌داده روی هاست. */
    public static function explainError(\Throwable $e): string
    {
        $m = $e->getMessage();
        if ($e instanceof QueryException || str_contains($m, 'SQLSTATE')) {
            if (preg_match("/Unknown column '([^']+)'/i", $m, $x) || preg_match('/no such column: (\S+)/i', $m, $x)) {
                return "ستونِ «{$x[1]}» در پایگاه‌داده‌ی هاست وجود ندارد؛ یکی از به‌روزرسانی‌ها مایگریشنش اجرا نشده است. ادمینِ کل ← «🩺 سلامتِ سیستم» ← «اجرای مایگریشن‌ها»";
            }
            if (preg_match("/Table '([^']+)' doesn't exist/i", $m, $x) || preg_match('/no such table: (\S+)/i', $m, $x)) {
                return "جدولِ «{$x[1]}» در پایگاه‌داده‌ی هاست ساخته نشده است. ادمینِ کل ← «🩺 سلامتِ سیستم» ← «اجرای مایگریشن‌ها»";
            }
            if (preg_match("/Data too long for column '([^']+)'/i", $m, $x)) {
                return "متنِ واردشده برای «{$x[1]}» از ظرفیتِ پایگاه‌داده بلندتر است؛ کوتاه‌ترش کنید";
            }
            if (str_contains($m, 'Incorrect string value')) {
                return 'نویسه‌ای (مثلاً ایموجی) در متن هست که پایگاه‌داده‌ی هاست نمی‌پذیرد؛ charsetِ جدول‌ها باید utf8mb4 باشد (سلامتِ سیستم)';
            }
            if (str_contains($m, 'foreign key constraint') || str_contains($m, 'Integrity constraint')) {
                return 'یکی از انتخاب‌ها (کلاس، فصل یا گروه) در پایگاه‌داده پیدا نشد؛ صفحه را تازه کنید و دوباره انتخاب کنید';
            }
            if (str_contains($m, 'Lock wait timeout') || str_contains($m, 'Deadlock')) {
                return 'پایگاه‌داده لحظه‌ای مشغول بود؛ چند ثانیه بعد دوباره تلاش کنید';
            }

            return 'خطای پایگاه‌داده: ' . Str::limit(preg_replace('/\(Connection:.*$/s', '', $m), 160);
        }
        if ($e instanceof \Error && str_contains($m, 'not found')) {
            return 'یکی از فایل‌های برنامه روی هاست قدیمی یا ناقص است (' . Str::limit($m, 120) . '). همه‌ی فایل‌های آخرین به‌روزرسانی را دوباره بارگذاری و «php artisan optimize:clear» را اجرا کنید';
        }

        return 'خطای سرور: ' . Str::limit($m, 160);
    }
}
