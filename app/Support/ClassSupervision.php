<?php

namespace App\Support;

use App\Models\Classroom;
use App\Models\OnlineRoom;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * نقشِ «ناظر» — ساده:
 *   مدیرِ مدرسه  → همه‌ی کلاس‌ها؛ معلم و ناظر می‌سازد و ناظر را به کلاس‌ها وصل می‌کند.
 *   ناظر         → یک حسابِ معلم که مدیر ناظرِ چند کلاسِ انتخابی کرده. فقط همان کلاس‌ها را
 *                   می‌بیند و همان کارهای مدیر را روی‌شان می‌کند (کلاسِ آنلاین: ورود، کنترل،
 *                   پایان، حضور و فیلم؛ حضور و غیاب؛ هشدارِ زودهنگام)، ولی معلم یا ناظر نمی‌سازد.
 */
class ClassSupervision
{
    /** @var array<int,array<int>> */
    private static array $ids = [];

    public static function ready(): bool
    {
        return DbSchema::hasTable('class_supervisors');
    }

    /** کلاس‌هایی (شناسه) که این کاربر ناظرشان است — فقط کلاس‌های مدرسه‌ی خودش. */
    public static function classroomIds(?User $user): array
    {
        if (! $user || ! self::ready() || ! $user->hasRole(Roles::TEACHER)) {
            return [];
        }

        return self::$ids[$user->id] ??= DB::table('class_supervisors')->where('user_id', $user->id)
            ->where('school_id', $user->school_id)->pluck('classroom_id')->map(fn ($v) => (int) $v)->unique()->values()->all();
    }

    public static function isSupervisor(?User $user): bool
    {
        return self::classroomIds($user) !== [];
    }

    public static function covers(?User $user, ?int $classroomId): bool
    {
        return $classroomId !== null && in_array((int) $classroomId, self::classroomIds($user), true);
    }

    /** کلاس‌هایی که این معلم یا درسشان می‌دهد یا ناظرشان است. */
    public static function teacherClassrooms(User $user): Builder
    {
        $ids = self::classroomIds($user);

        return Classroom::query()->where(fn ($q) => $q->where('teacher_id', $user->id)
            ->when($ids, fn ($w) => $w->orWhereIn('id', $ids)));
    }

    /**
     * کلاس‌های آنلاینی که ناظر می‌بیند: کلاسِ آنلاینِ یکی از کلاس‌هایش، یا کلاسِ آنلاینِ
     * «همه‌ی کلاس‌هایم» از معلمی که یکی از کلاس‌های او زیرِ نظرِ این ناظر است.
     */
    public static function onlineRooms(User $user): Builder
    {
        $ids = self::classroomIds($user) ?: [0];
        $teachers = Classroom::whereIn('id', $ids)->pluck('teacher_id')->filter()->unique()->all() ?: [0];

        return OnlineRoom::query()->where('school_id', $user->school_id)->where(fn ($q) => $q->whereIn('classroom_id', $ids)
            ->orWhere(fn ($w) => $w->whereNull('classroom_id')->whereIn('teacher_id', $teachers)));
    }

    public static function coversRoom(User $user, OnlineRoom $room): bool
    {
        if ((int) $room->school_id !== (int) $user->school_id || ! self::isSupervisor($user)) {
            return false;
        }
        if ($room->classroom_id) {
            return self::covers($user, (int) $room->classroom_id);
        }

        return Classroom::whereIn('id', self::classroomIds($user))->where('teacher_id', $room->teacher_id)->exists();
    }

    /** مدیرِ مدرسه: کلاس‌های تحتِ نظرِ یک ناظر را یک‌جا تعیین می‌کند (خالی = دیگر ناظر نیست). */
    public static function assign(User $admin, User $supervisor, array $classroomIds): int
    {
        $valid = Classroom::where('school_id', $admin->school_id)->whereIn('id', array_map('intval', $classroomIds))
            ->pluck('id')->map(fn ($v) => (int) $v)->all();
        DB::transaction(function () use ($admin, $supervisor, $valid) {
            DB::table('class_supervisors')->where('user_id', $supervisor->id)->delete();
            $now = now();
            DB::table('class_supervisors')->insert(array_map(fn ($cid) => [
                'school_id' => $admin->school_id, 'user_id' => $supervisor->id, 'classroom_id' => $cid,
                'granted_by' => $admin->id, 'created_at' => $now, 'updated_at' => $now,
            ], $valid));
        });
        self::forget();

        return count($valid);
    }

    /** نقشه‌ی «ناظر → کلاس‌ها» برای همه‌ی معلم‌های یک مدرسه. */
    public static function mapForSchool(int $schoolId): array
    {
        if (! self::ready()) {
            return [];
        }

        return DB::table('class_supervisors')->where('school_id', $schoolId)->get(['user_id', 'classroom_id'])
            ->groupBy('user_id')->map(fn ($g) => $g->pluck('classroom_id')->map(fn ($v) => (int) $v)->values()->all())->all();
    }

    public static function forget(): void
    {
        self::$ids = [];
    }
}
