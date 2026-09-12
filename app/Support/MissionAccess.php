<?php

namespace App\Support;

use App\Models\Mission;
use App\Models\MissionCompletion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * «کدام مأموریت‌ها مالِ این دانش‌آموز است؟» — یک جا، برای همه.
 *
 * این منطق پیش از این فقط داخلِ Student\MissionController بود. حالا که
 * فیدِ اعلان‌ها و پیشخوان هم باید همان مأموریت‌ها را بشناسند، به اینجا
 * منتقل شد تا سه نسخه‌ی جداگانه از یک قاعده نداشته باشیم و با هر تغییر
 * از هم دور نیفتند.
 */
class MissionAccess
{
    /** مأموریت‌های فعالِ در دسترسِ این دانش‌آموز (کلاس + تیم). */
    public static function availableFor(User $student): Builder
    {
        $classrooms = $student->classrooms()->get(['classrooms.id', 'classrooms.teacher_id']);
        $teacherIds = $classrooms->pluck('teacher_id')->filter()->unique()->values()->all();
        $classroomIds = $classrooms->pluck('id')->all();

        return Mission::whereIn('teacher_id', $teacherIds ?: [0])
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('classroom_id')->orWhereIn('classroom_id', $classroomIds ?: [0]))
            // مأموریتِ بدونِ تیم برای همه؛ مأموریتِ تیم‌دار فقط برای همان تیم
            ->where(fn ($q) => $q->whereNull('theme_id')->orWhere('theme_id', $student->theme_id));
    }

    /** شناسه‌ی مأموریت‌هایی که امروز انجام شده‌اند. */
    public static function doneTodayIds(User $student): array
    {
        return MissionCompletion::where('student_id', $student->id)
            ->whereDate('play_date', now()->toDateString())
            ->pluck('mission_id')->all();
    }

    /**
     * مأموریت‌های امروزِ این دانش‌آموز که هنوز انجام نشده‌اند.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int,Mission>
     */
    public static function pendingToday(User $student)
    {
        $done = self::doneTodayIds($student);

        return self::availableFor($student)
            ->when($done, fn ($q) => $q->whereNotIn('id', $done))
            ->get();
    }
}
