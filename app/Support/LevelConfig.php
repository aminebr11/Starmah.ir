<?php

namespace App\Support;

use App\Models\Setting;

/** پیکربندی مرحله‌ها (سطوح) — امتیازِ هر مرحله و نامِ اختیاریِ مرحله‌ها، به تفکیک مدرسه. */
class LevelConfig
{
    public const DEFAULT_XP = 150;

    /** امتیاز لازم برای هر مرحله در این مدرسه. */
    public static function xpPerLevel(?int $schoolId): int
    {
        $v = (int) Setting::get("level_xp:$schoolId", self::DEFAULT_XP);
        return $v >= 10 ? $v : self::DEFAULT_XP;
    }

    /** نام‌های سفارشیِ مرحله‌ها (اگر معلم تعیین کرده باشد). */
    public static function names(?int $schoolId): array
    {
        $v = Setting::get("level_names:$schoolId", '[]');
        if (is_array($v)) {
            return array_values($v);
        }
        $decoded = json_decode((string) $v, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    /** پیکربندی کامل برای ارسال به فرانت. */
    public static function forSchool(?int $schoolId): array
    {
        return [
            'xp_per_level' => self::xpPerLevel($schoolId),
            'names'        => self::names($schoolId),
        ];
    }

    /** مرحله‌ی متناظر با یک امتیاز (۱-بنیان). */
    public static function levelOf(int $xp, ?int $schoolId): int
    {
        return intdiv(max(0, $xp), self::xpPerLevel($schoolId)) + 1;
    }
}
