<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;

/**
 * «کارهایی که ماهو انجام می‌دهد» و اینکه چه کسی اجازه‌شان را می‌دهد — دو لایه:
 *   ادمینِ کل   → هر کار را برای کلِ سامانه روشن/خاموش می‌کند؛ ورودِ نمایشی، صدا و خوش‌آمدِ گفتاری.
 *   مدیرِ مدرسه → از میانِ کارهای روشن، برای معلم‌ها و دانش‌آموزانِ مدرسه‌ی خودش انتخاب می‌کند.
 * ماهو هیچ کاری را بی‌تأییدِ خودِ کاربر انجام نمی‌دهد (کارتِ «✅ انجام بده»).
 */
class MahooAbilities
{
    /** کلید → [عنوان، توضیح، نقش‌هایی که این کار برایشان معنا دارد، پیش‌فرض] */
    public const LIST = [
        'navigate'      => ['🧭 باز کردنِ صفحه‌ها', 'می‌گویی «برو به کارنامه» و ماهو همان صفحه را باز می‌کند.', ['student', 'teacher', 'school_admin', 'parent', 'super_admin'], true],
        'timer'         => ['⏱️ تایمرِ درس و استراحت', 'تایمرِ ۵ تا ۶۰ دقیقه‌ای؛ سرِ وقت با صدا خبر می‌دهد.', ['student', 'teacher'], true],
        'fun'           => ['🧩 چیستان و جمله‌ی انرژی', 'چیستان، معما و جمله‌های انگیزشیِ کوتاه برای بچه‌ها.', ['student'], true],
        'award'         => ['⭐ امتیاز به دانش‌آموز', 'معلم می‌گوید «به سارا ۵ امتیاز بده» و ماهو بعد از تأیید ثبت می‌کند (با قاعده‌های امتیازِ مدرسه).', ['teacher'], true],
        'class_notice'  => ['📣 پیام به کلاس', 'معلم متن را می‌گوید و ماهو بعد از تأیید برای همه‌ی دانش‌آموزانِ کلاس می‌فرستد.', ['teacher'], true],
        'remedial'      => ['🔁 آزمونِ جبرانی', 'از سؤال‌های دشوارِ آخرین بازی یک آزمونِ جبرانی (پیش‌نویس) می‌سازد.', ['teacher'], true],
        'school_notice' => ['📢 اطلاعیه‌ی مدرسه', 'مدیرِ مدرسه متن را می‌گوید و ماهو بعد از تأیید برای همه، معلم‌ها یا دانش‌آموزان منتشر می‌کند.', ['school_admin'], true],
    ];

    /** نقش‌هایی که مدیرِ مدرسه برایشان تصمیم می‌گیرد. */
    public const SCHOOL_ROLES = ['teacher' => 'معلم‌ها', 'student' => 'دانش‌آموزان'];

    public const LOOK = ['entrance' => true, 'sound' => true, 'voice' => true];

    public static function roleOf(?User $user): ?string
    {
        if (! $user) {
            return null;
        }
        foreach (['super_admin', 'school_admin', 'teacher', 'student', 'parent'] as $r) {
            if ($user->hasRole($r)) {
                return $r;
            }
        }

        return $user->isStudent() ? 'student' : 'parent';
    }

    /** تنظیمِ سراسری (ادمینِ کل). */
    public static function global(): array
    {
        $v = json_decode((string) Setting::get('mahoo:global', ''), true) ?: [];
        $ab = [];
        foreach (self::LIST as $k => [, , , $def]) {
            $ab[$k] = (bool) ($v['abilities'][$k] ?? $def);
        }
        $look = [];
        foreach (self::LOOK as $k => $def) {
            $look[$k] = (bool) ($v[$k] ?? $def);
        }

        return ['abilities' => $ab] + $look;
    }

    public static function saveGlobal(array $abilities, array $look): void
    {
        $out = ['abilities' => []];
        foreach (self::LIST as $k => $_) {
            $out['abilities'][$k] = (bool) ($abilities[$k] ?? false);
        }
        foreach (self::LOOK as $k => $_) {
            $out[$k] = (bool) ($look[$k] ?? false);
        }
        Setting::put('mahoo:global', json_encode($out));
    }

    /** تنظیمِ یک مدرسه (مدیرِ مدرسه): برای هر کار و هر نقش. */
    public static function school(?int $schoolId): array
    {
        $v = $schoolId ? (json_decode((string) Setting::get('mahoo:school:'.$schoolId, ''), true) ?: []) : [];
        $ab = [];
        foreach (self::LIST as $k => [, , $roles]) {
            foreach (array_keys(self::SCHOOL_ROLES) as $r) {
                if (in_array($r, $roles, true)) {
                    $ab[$k][$r] = (bool) ($v['abilities'][$k][$r] ?? true);
                }
            }
        }
        $look = [];
        foreach (self::LOOK as $k => $_) {
            $look[$k] = (bool) ($v[$k] ?? true);
        }

        return ['abilities' => $ab] + $look;
    }

    public static function saveSchool(int $schoolId, array $abilities, array $look): void
    {
        $out = ['abilities' => []];
        foreach (self::LIST as $k => [, , $roles]) {
            foreach (array_keys(self::SCHOOL_ROLES) as $r) {
                if (in_array($r, $roles, true)) {
                    $out['abilities'][$k][$r] = (bool) ($abilities[$k][$r] ?? false);
                }
            }
        }
        foreach (self::LOOK as $k => $_) {
            $out[$k] = (bool) ($look[$k] ?? false);
        }
        Setting::put('mahoo:school:'.$schoolId, json_encode($out));
    }

    /** آیا ماهو این کار را برای این کاربر انجام می‌دهد؟ */
    public static function allowed(?User $user, string $ability): bool
    {
        $role = self::roleOf($user);
        $def = self::LIST[$ability] ?? null;
        if (! $role || ! $def || ! in_array($role, $def[2], true) || ! AssistantAccess::visible($user)) {
            return false;
        }
        if (! self::global()['abilities'][$ability]) {
            return false;
        }
        if (isset(self::SCHOOL_ROLES[$role]) && $user->school_id) {
            return (bool) (self::school((int) $user->school_id)['abilities'][$ability][$role] ?? true);
        }

        return true;
    }

    /** کارهای مجازِ این کاربر (برای دستیار و صفحه‌ی خوش‌آمد). */
    public static function forUser(?User $user): array
    {
        $out = [];
        foreach (self::LIST as $k => [$title, $desc]) {
            if (self::allowed($user, $k)) {
                $out[] = ['key' => $k, 'title' => $title, 'desc' => $desc];
            }
        }

        return $out;
    }

    /** ظاهر: ورودِ نمایشی، صدا و خوش‌آمدِ گفتاری — اگر هم ادمینِ کل و هم مدرسه روشن گذاشته باشند. */
    public static function look(?User $user): array
    {
        $g = self::global();
        $s = $user?->school_id ? self::school((int) $user->school_id) : null;
        $out = [];
        foreach (self::LOOK as $k => $_) {
            $out[$k] = $g[$k] && ($s[$k] ?? true);
        }

        return $out;
    }
}
