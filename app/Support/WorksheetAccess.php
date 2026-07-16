<?php

namespace App\Support;

use App\Models\User;
use App\Models\Worksheet;
use Illuminate\Database\Eloquent\Builder;

/**
 * دسترسیِ بانک کاربرگ‌ها — همان قواعدِ بانک سؤالات:
 * - معلم: کاربرگ‌های خودش + کاربرگ‌های مدرسه/سراسری.
 * - مدیر مدرسه: کلِ کاربرگ‌های مدرسه‌ی خودش.
 * - ادمین کل: همه.
 */
class WorksheetAccess
{
    public static function visibleQuery(User $user): Builder
    {
        if ($user->hasRole(Roles::SUPER_ADMIN)) {
            return Worksheet::withoutGlobalScopes();
        }
        if ($user->hasRole(Roles::SCHOOL_ADMIN)) {
            return Worksheet::withoutGlobalScopes()->where('school_id', $user->school_id);
        }

        // معلم
        $seesShared = BankAccess::schoolCanSeeShared($user->school_id);

        return Worksheet::withoutGlobalScopes()->where(function (Builder $q) use ($user, $seesShared) {
            $q->where('teacher_id', $user->id);
            $q->orWhere(function (Builder $w) use ($user) {
                $w->where('school_id', $user->school_id)->whereIn('scope', ['school', 'global']);
            });
            if ($seesShared) {
                $q->orWhere('scope', 'global');
            }
        });
    }

    public static function canEdit(User $user, Worksheet $w): bool
    {
        if ($user->hasRole(Roles::SUPER_ADMIN)) {
            return true;
        }
        if ($user->hasRole(Roles::SCHOOL_ADMIN)) {
            return $w->school_id === $user->school_id;
        }
        return $w->teacher_id === $user->id;
    }
}
