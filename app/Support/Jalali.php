<?php

namespace App\Support;

/**
 * تبدیل و قالب‌بندی تاریخ شمسی (جلالی).
 * منطق تبدیل از نسخه‌ی قبلی سایت پورت شده است.
 */
class Jalali
{
    public static function fromGregorian(int $gy, int $gm, int $gd): array
    {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + ((int) (($gy2 + 3) / 4)) - ((int) (($gy2 + 99) / 100))
            + ((int) (($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * ((int) ($days / 12053)));
        $days %= 12053;
        $jy += 4 * ((int) ($days / 1461));
        $days %= 1461;
        if ($days > 365) {
            $jy += (int) (($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        $jm = ($days < 186) ? 1 + (int) ($days / 31) : 7 + (int) (($days - 186) / 30);
        $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));

        return [$jy, $jm, $jd];
    }

    private const MONTHS = ['', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
        'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    private const WEEKDAYS = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

    /** خروجی مثل «۱۲ آذر ۱۴۰۳» از یک Carbon/DateTime */
    public static function format($date, bool $withWeekday = false): string
    {
        if (! $date) {
            return '';
        }
        [$jy, $jm, $jd] = self::fromGregorian((int) $date->format('Y'), (int) $date->format('n'), (int) $date->format('j'));
        $out = self::fa("{$jd} " . self::MONTHS[$jm] . " {$jy}");
        if ($withWeekday) {
            // 6=شنبه در نگاشت ما؛ date('w') یکشنبه=0
            $w = ((int) $date->format('w') + 1) % 7;
            $out = self::WEEKDAYS[$w] . ' ' . $out;
        }
        return $out;
    }

    public static function fa(string $s): string
    {
        return strtr($s, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }

    public static function weekdays(): array
    {
        return self::WEEKDAYS;
    }
}
