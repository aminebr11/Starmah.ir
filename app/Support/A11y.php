<?php

namespace App\Support;

use App\Models\User;

/**
 * تنظیماتِ دسترس‌پذیریِ دانش‌آموز: «بخوان برایم» و «متنِ درشت».
 * متنِ درشت برای اول تا سوم به‌طورِ پیش‌فرض روشن است (هنوز روان نمی‌خوانند).
 */
class A11y
{
    public const EARLY_GRADES = ['اول', 'دوم', 'سوم'];

    public static function for(?User $user): ?array
    {
        if (! $user) {
            return null;
        }
        $saved = (array) (($user->settings ?? [])['a11y'] ?? []);

        return [
            'readAloud' => (bool) ($saved['readAloud'] ?? true),
            'largeText' => (bool) ($saved['largeText'] ?? in_array(self::grade($user), self::EARLY_GRADES, true)),
        ];
    }

    /** پایه‌ی دانش‌آموز: از پروفایل، وگرنه از کلاسش. */
    private static function grade(User $user): ?string
    {
        return $user->grade ?: $user->classrooms()->value('classrooms.grade');
    }
}
