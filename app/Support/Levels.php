<?php

namespace App\Support;

/** مقاطع تحصیلی و پایه‌های هرکدام (نظام آموزشی ایران). */
class Levels
{
    public const MAP = [
        'دبستان'      => ['اول', 'دوم', 'سوم', 'چهارم', 'پنجم', 'ششم'],
        'متوسطه اول'  => ['هفتم', 'هشتم', 'نهم'],
        'متوسطه دوم'  => ['دهم', 'یازدهم', 'دوازدهم'],
    ];

    /** فهرست مقاطع */
    public static function levels(): array
    {
        return array_keys(self::MAP);
    }

    /** پایه‌های یک مقطع */
    public static function grades(?string $level): array
    {
        return self::MAP[$level] ?? [];
    }

    /** همه‌ی پایه‌ها به‌صورت تخت */
    public static function allGrades(): array
    {
        return array_merge(...array_values(self::MAP));
    }

    public static function isValidLevel(?string $level): bool
    {
        return $level !== null && array_key_exists($level, self::MAP);
    }
}
