<?php

namespace App\Support;

use App\Models\User;

/**
 * پرونده‌ی کاملِ دانش‌آموز برای نمایش و ویرایش — مشترک بینِ مدیرِ مدرسه
 * و معلم، تا هر دو دقیقاً همان مشخصات را ببینند و اصلاح کنند.
 */
class StudentRecordData
{
    public static function row(User $s): array
    {
        $class = $s->classrooms()->with('teacher:id,name')->first();
        $settings = $s->settings ?? [];
        $g = $settings['guardian'] ?? [];
        $parts = preg_split('/\s+/', trim((string) $s->name), 2);

        return [
            'id' => $s->id, 'name' => $s->name,
            'first_name' => $parts[0] ?? '', 'last_name' => $parts[1] ?? '',
            'avatar' => $s->avatar_url, 'has_avatar' => (bool) $s->avatar,
            'phone' => $s->phone, 'national_id' => $s->national_id,
            'gender' => $settings['gender'] ?? null,
            'grade' => $s->grade,
            'birth_date' => $s->birth_date?->toDateString(),
            'jbirth' => $s->birth_date ? Jalali::format($s->birth_date) : null,
            'father_name' => $g['father_name'] ?? null,
            'mother_name' => $g['mother_name'] ?? null,
            'parent_relation' => $g['relation'] ?? null,
            'address' => $g['address'] ?? ($settings['address'] ?? null),
            // سازگاری با داده‌های قدیمی که guardian_name مستقیم در settings بود
            'guardian_name' => $g['father_name'] ?? $g['mother_name'] ?? ($settings['guardian_name'] ?? null),
            'guardian_phone' => $g['phone'] ?? ($settings['guardian_phone'] ?? $s->parent_phone),
            'parent_pin' => $g['pin'] ?? null,
            'theme_id' => $s->theme_id,
            'team' => $s->theme ? "{$s->theme->emoji} {$s->theme->name}" : null,
            'classroom_id' => $class?->id, 'class' => $class?->name, 'teacher' => $class?->teacher?->name,
            'joined' => Jalali::format($s->created_at),
            'xp' => $s->totalXp(),
        ];
    }
}
