<?php

namespace App\Support;

use App\Models\User;

/**
 * چه کسی دستیار را می‌بیند و آیا اجازه‌ی استفاده از هوش مصنوعی دارد.
 *
 * سه لایه، مثلِ پیامک:
 *   ادمینِ کل   → کلیدِ هوش مصنوعی را می‌گذارد (بدونِ کلید، حالتِ آماده)
 *   مدیرِ مدرسه → دستیار را برای معلم‌ها و دانش‌آموزان باز/بسته می‌کند،
 *                 و می‌تواند هوش مصنوعی را خاموش کند تا فقط پاسخ‌های
 *                 آماده‌ی سامانه (بی‌هزینه) داده شود
 *   کاربر       → همیشه پاسخِ سامانه‌ای دارد، حتی وقتی AI در دسترس نیست
 */
class AssistantAccess
{
    /** آیا حبابِ دستیار برای این کاربر نمایش داده شود؟ */
    public static function visible(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->hasRole(Roles::SUPER_ADMIN) || $user->hasRole(Roles::SCHOOL_ADMIN)) {
            return true;
        }

        $school = $user->school;
        if (! $school) {
            return true;   // کاربرِ بی‌مدرسه (والد) — دستیارِ راهنما باز باشد
        }
        if ($user->hasRole(Roles::TEACHER)) {
            return $school->assistant_teachers !== false;
        }
        if ($user->isStudent()) {
            return $school->assistant_students !== false;
        }

        return true;
    }

    /** آیا این کاربر مجاز است پاسخ از هوش مصنوعی بگیرد؟ */
    public static function aiAllowed(?User $user): bool
    {
        if (! $user || ! self::visible($user)) {
            return false;
        }
        // ادمینِ کل همیشه؛ بقیه به تصمیمِ مدرسه
        if ($user->hasRole(Roles::SUPER_ADMIN)) {
            return true;
        }

        return $user->school?->assistant_ai !== false;
    }
}
