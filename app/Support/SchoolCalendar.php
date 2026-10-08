<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * تقویمِ مدرسه:
 * - سالِ تحصیلی از ۱ مهر تا ۳۱ شهریورِ سالِ بعد (مثلاً ۱۴۰۵-۱۴۰۶).
 * - هفته از شنبه ۰۰:۰۰ تا پایانِ جمعه (به وقتِ تهران).
 * - «هفته‌ی Nامِ سالِ تحصیلی» = هفته‌ای که ۱ مهر در آن است، هفته‌ی ۱.
 */
class SchoolCalendar
{
    /** سالِ شروعِ سالِ تحصیلیِ یک تاریخ (مهر به بعد = همان سال، وگرنه سالِ قبل). */
    public static function schoolYearOf(?Carbon $at = null): int
    {
        $at ??= now();
        [$jy, $jm] = Jalali::fromGregorian((int) $at->format('Y'), (int) $at->format('n'), (int) $at->format('j'));

        return $jm >= 7 ? $jy : $jy - 1;
    }

    /** آغازِ سالِ تحصیلی (۱ مهر، ۰۰:۰۰). */
    public static function yearStart(int $jy): Carbon
    {
        [$gy, $gm, $gd] = Jalali::toGregorian($jy, 7, 1);

        return Carbon::create($gy, $gm, $gd, 0, 0, 0, config('app.timezone'));
    }

    /** پایانِ سالِ تحصیلی (پایانِ ۳۱ شهریورِ سالِ بعد). */
    public static function yearEnd(int $jy): Carbon
    {
        return self::yearStart($jy + 1)->subSecond();
    }

    public static function yearLabel(int $jy): string
    {
        return Jalali::fa($jy . '-' . ($jy + 1));
    }

    /** شنبه‌ی آغازِ هفته‌ی یک تاریخ. */
    public static function weekStart(Carbon $at): Carbon
    {
        $d = $at->copy()->setTimezone(config('app.timezone'))->startOfDay();
        // Carbon: شنبه = 6
        $back = ($d->dayOfWeek - Carbon::SATURDAY + 7) % 7;

        return $d->subDays($back);
    }

    /** شماره‌ی هفته در سالِ تحصیلی (هفته‌ی ۱ = هفته‌ای که ۱ مهر در آن است). */
    public static function weekNumber(Carbon $at, ?int $jy = null): int
    {
        $jy ??= self::schoolYearOf($at);
        $first = self::weekStart(self::yearStart($jy));

        return intdiv((int) $first->diffInDays(self::weekStart($at), false), 7) + 1;
    }

    /** مشخصاتِ کاملِ یک هفته. */
    public static function week(Carbon $at, ?int $jy = null): array
    {
        $jy ??= self::schoolYearOf($at);
        $from = self::weekStart($at);
        $to = $from->copy()->addDays(6)->endOfDay();
        $n = self::weekNumber($at, $jy);
        [, $fm, $fd] = Jalali::fromGregorian((int) $from->format('Y'), (int) $from->format('n'), (int) $from->format('j'));
        [$ty, $tm, $td] = Jalali::fromGregorian((int) $to->format('Y'), (int) $to->format('n'), (int) $to->format('j'));
        $range = $fm === $tm
            ? "شنبه {$fd} تا جمعه {$td} " . Jalali::monthName($tm) . " {$ty}"
            : "شنبه {$fd} " . Jalali::monthName($fm) . " تا جمعه {$td} " . Jalali::monthName($tm) . " {$ty}";

        return [
            'n' => $n,
            'from' => $from,
            'to' => $to,
            'key' => $from->toDateString(),
            'title' => Jalali::fa("هفته‌ی {$n}") . ' سالِ تحصیلیِ ' . self::yearLabel($jy),
            'short' => Jalali::fa("هفته‌ی {$n}"),
            'range' => Jalali::fa($range),
            'tick' => Jalali::fa($fd . ' ' . Jalali::monthName($fm)),
        ];
    }

    /** همه‌ی هفته‌های سالِ تحصیلی تا امروز (یا پایانِ سال). */
    public static function weeksSoFar(int $jy, ?Carbon $until = null): array
    {
        $until ??= now();
        $end = min($until->timestamp, self::yearEnd($jy)->timestamp);
        $out = [];
        for ($d = self::weekStart(self::yearStart($jy)); $d->timestamp <= $end; $d = $d->copy()->addDays(7)) {
            $out[] = self::week($d, $jy);
        }

        return $out;
    }

    /** ماه‌های شمسیِ سالِ تحصیلی: [jy, jm, from, to, label]. */
    public static function months(int $jy): array
    {
        $out = [];
        foreach ([7, 8, 9, 10, 11, 12, 1, 2, 3, 4, 5, 6] as $m) {
            $y = $m >= 7 ? $jy : $jy + 1;
            [$gy, $gm, $gd] = Jalali::toGregorian($y, $m, 1);
            $from = Carbon::create($gy, $gm, $gd, 0, 0, 0, config('app.timezone'));
            [$ny, $nm] = $m === 12 ? [$y + 1, 1] : [$y, $m + 1];
            [$hy, $hm, $hd] = Jalali::toGregorian($ny, $nm, 1);
            $to = Carbon::create($hy, $hm, $hd, 0, 0, 0, config('app.timezone'))->subSecond();
            $out[] = ['key' => sprintf('%d-%02d', $y, $m), 'from' => $from, 'to' => $to, 'label' => Jalali::fa(Jalali::monthName($m) . ' ' . $y)];
        }

        return $out;
    }
}
