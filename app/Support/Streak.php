<?php

namespace App\Support;

use App\Models\MissionCompletion;
use App\Models\ReviewCompletion;
use App\Models\User;

/** روزهای پیاپیِ تمرین (مأموریت یا مرور). اگر امروز هنوز تمرین نشده، رشته از دیروز زنده است. */
class Streak
{
    public static function days(User $user): int
    {
        $dates = MissionCompletion::where('student_id', $user->id)->pluck('play_date')
            ->merge(\App\Services\LearningService::ready() ? ReviewCompletion::where('student_id', $user->id)->pluck('play_date') : collect())
            ->map(fn ($d) => $d->toDateString())->unique()->sortDesc()->values();

        $streak = 0;
        $cursor = now()->startOfDay();
        foreach ($dates as $d) {
            if ($d === $cursor->toDateString()) {
                $streak++;
                $cursor->subDay();
            } elseif ($streak === 0 && $d === $cursor->copy()->subDay()->toDateString()) {
                $streak++;
                $cursor->subDays(2);
            } elseif ($d < $cursor->toDateString()) {
                break;
            }
        }

        return $streak;
    }
}
