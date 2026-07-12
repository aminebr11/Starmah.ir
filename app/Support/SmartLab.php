<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;

/**
 * «آزمایشگاه هوشمند آزمون» — پرچم‌های ویژگی (Feature Flags).
 * ماژول آزمایشی و کاملاً قابل خاموش‌کردن؛ خاموش‌بودن آن هیچ اثری روی آزمون‌های قدیمی ندارد.
 */
class SmartLab
{
    /** کلیدهای پرچم و مقدار پیش‌فرض (همه پیش‌فرض خاموش — ماژول آزمایشی). */
    public const FLAGS = [
        'smart_lab_enabled'      => false, // کلید اصلی ماژول
        'smart_ai_enabled'       => false, // تولید سؤال با AI
        'smart_adaptive_enabled' => false, // آزمون تطبیقی
        'smart_analysis_enabled' => false, // تحلیل هوشمند نتیجه
        'smart_games_enabled'    => false, // اتصال به بازی‌های جبرانی
    ];

    public static function flag(string $key): bool
    {
        return (bool) Setting::get($key, self::FLAGS[$key] ?? false);
    }

    /** دامنه: all | pilot | off — چه معلمانی به ماژول دسترسی دارند. */
    public static function scope(): string
    {
        return (string) Setting::get('smart_scope', 'off');
    }

    /** لیست شناسه‌ی معلمان آزمایشی (وقتی scope=pilot). */
    public static function pilotTeacherIds(): array
    {
        $v = Setting::get('smart_pilot_teachers', '[]');
        $d = is_array($v) ? $v : json_decode((string) $v, true);
        return is_array($d) ? array_map('intval', $d) : [];
    }

    /** آیا ماژول برای این کاربر فعال است؟ (ادمین کل همیشه؛ معلم/دانش‌آموز طبق دامنه) */
    public static function enabledFor(?User $user): bool
    {
        if (! $user || ! self::flag('smart_lab_enabled')) {
            return false;
        }
        if ($user->hasRole(Roles::SUPER_ADMIN)) {
            return true;
        }
        $scope = self::scope();
        if ($scope === 'off') {
            return false;
        }
        if ($scope === 'all') {
            return true;
        }
        // pilot: معلمِ آزمایشی، یا دانش‌آموزی که معلمِ کلاسش آزمایشی است
        $pilot = self::pilotTeacherIds();
        if ($user->hasRole(Roles::TEACHER)) {
            return in_array($user->id, $pilot, true);
        }
        if ($user->hasRole(Roles::STUDENT)) {
            $teacherIds = $user->classrooms()->with('teacher')->get()->pluck('teacher_id')->all();
            return (bool) array_intersect($teacherIds, $pilot);
        }
        return false;
    }

    /** پیکربندی کامل برای اشتراک‌گذاری با فرانت. */
    public static function config(): array
    {
        return [
            'lab'      => self::flag('smart_lab_enabled'),
            'ai'       => self::flag('smart_ai_enabled'),
            'adaptive' => self::flag('smart_adaptive_enabled'),
            'analysis' => self::flag('smart_analysis_enabled'),
            'games'    => self::flag('smart_games_enabled'),
            'scope'    => self::scope(),
        ];
    }
}
