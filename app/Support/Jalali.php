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

    /** تبدیلِ شمسی به میلادی — خروجی [سال، ماه، روز]. */
    public static function toGregorian(int $jy, int $jm, int $jd): array
    {
        $jy += 1595;
        $days = -355668 + (365 * $jy) + (((int) ($jy / 33)) * 8) + ((int) ((($jy % 33) + 3) / 4)) + $jd
            + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
        $gy = 400 * ((int) ($days / 146097));
        $days %= 146097;
        if ($days > 36524) {
            $gy += 100 * ((int) (--$days / 36524));
            $days %= 36524;
            if ($days >= 365) $days++;
        }
        $gy += 4 * ((int) ($days / 1461));
        $days %= 1461;
        if ($days > 365) {
            $gy += (int) (($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        $gd = $days + 1;
        $sal = [0, 31, (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        for ($gm = 0; $gm < 13 && $gd > $sal[$gm]; $gm++) $gd -= $sal[$gm];

        return [$gy, $gm, $gd];
    }

    /** نامِ ماهِ شمسی. */
    public static function monthName(int $jm): string
    {
        return self::MONTHS[$jm] ?? '';
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

    /** اجزای تاریخ شمسی + برچسب‌های آماده برای دسته‌بندی. */
    public static function ymParts($date): array
    {
        [$jy, $jm, $jd] = self::fromGregorian((int) $date->format('Y'), (int) $date->format('n'), (int) $date->format('j'));
        $w = ((int) $date->format('w') + 1) % 7;

        return [
            'jy' => $jy, 'jm' => $jm, 'jd' => $jd,
            'year' => self::fa((string) $jy),
            'month' => self::MONTHS[$jm],
            'monthLabel' => self::fa(self::MONTHS[$jm] . ' ' . $jy),
            'day' => self::fa((string) $jd),
            'weekday' => self::WEEKDAYS[$w],
            'short' => self::fa($jd . ' ' . self::MONTHS[$jm]),
        ];
    }
}
