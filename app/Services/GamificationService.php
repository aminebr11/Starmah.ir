<?php

namespace App\Services;

use App\Models\ActivityResult;
use App\Models\Badge;
use App\Models\Season;
use App\Models\SkillMastery;
use App\Models\User;
use App\Models\XpEntry;
use Illuminate\Support\Facades\DB;

/**
 * منطق گیمیفیکیشن: ثبت نتیجه‌ی فعالیت، اعطای XP در دفترکل،
 * به‌روزرسانی تسلط بر مهارت، و اعطای نشان.
 */
class GamificationService
{
    /**
     * نتیجه‌ی یک جلسه‌ی تمرین را ثبت و پاداش می‌دهد.
     *
     * @param  array  $perSkill  [skill_id => ['correct'=>int,'total'=>int]]
     * @return array{xp:int, badges:array}
     */
    public function recordPractice(User $student, int $score, int $max, int $xp, ?int $skillId, array $perSkill = [], ?int $themeId = null): array
    {
        return DB::transaction(function () use ($student, $score, $max, $xp, $skillId, $perSkill, $themeId) {
            $accuracy = $max > 0 ? round($score / $max * 100, 2) : 0;

            $result = ActivityResult::create([
                'student_id'   => $student->id,
                'skill_id'     => $skillId,
                'classroom_id' => $student->classrooms()->value('classrooms.id'),
                'theme_id'     => $themeId ?? $student->theme_id,
                'score'        => $score,
                'max_score'    => $max,
                'accuracy'     => $accuracy,
            ]);

            if ($xp > 0) {
                XpEntry::create([
                    'student_id'  => $student->id,
                    'season_id'   => $this->activeSeasonId($student),
                    'amount'      => $xp,
                    'reason'      => 'تمرین',
                    'source_type' => ActivityResult::class,
                    'source_id'   => $result->id,
                ]);
            }

            foreach ($perSkill as $sid => $stat) {
                $this->updateMastery($student, (int) $sid, $stat['correct'] ?? 0, $stat['total'] ?? 0);
            }

            $badges = $this->checkBadges($student);

            return ['xp' => $xp, 'badges' => $badges];
        });
    }

    /** اعطای XP دستی (توسط معلم/ادمین) */
    public function awardXp(User $student, int $amount, string $reason, ?User $by = null): XpEntry
    {
        return XpEntry::create([
            'student_id' => $student->id,
            'season_id'  => $this->activeSeasonId($student),
            'amount'     => $amount,
            'reason'     => $reason,
            'awarded_by' => $by?->id,
        ]);
    }

    /** میانگین متحرک ساده برای تسلط بر مهارت (۰..۱۰۰) */
    public function updateMastery(User $student, int $skillId, int $correct, int $total): void
    {
        if ($total <= 0) {
            return;
        }
        $sessionPct = (int) round($correct / $total * 100);

        $m = SkillMastery::firstOrNew([
            'student_id' => $student->id,
            'skill_id'   => $skillId,
        ]);

        // میانگین وزنی: ۷۰٪ تاریخچه + ۳۰٪ جلسه‌ی جدید
        $m->mastery = $m->exists ? (int) round($m->mastery * 0.7 + $sessionPct * 0.3) : $sessionPct;
        $m->attempts = ($m->attempts ?? 0) + 1;
        $m->last_practiced_at = now();
        $m->save();
    }

    /** اعطای نشان‌های ساده بر اساس آستانه‌ها (قابل توسعه) */
    public function checkBadges(User $student): array
    {
        $awarded = [];

        $totalXp = $student->totalXp();
        $rules = [
            'streak-7' => fn () => ActivityResult::where('student_id', $student->id)->count() >= 7,
            'king-mul' => fn () => SkillMastery::where('student_id', $student->id)->where('mastery', '>=', 90)->exists(),
        ];

        foreach ($rules as $key => $passes) {
            $badge = Badge::where('key', $key)->first();
            if (! $badge) {
                continue;
            }
            $has = $student->badges()->where('badges.id', $badge->id)->exists();
            if (! $has && $passes()) {
                $student->badges()->attach($badge->id, ['awarded_at' => now()]);
                $awarded[] = ['name' => $badge->name, 'emoji' => $badge->emoji];
            }
        }

        return $awarded;
    }

    public function activeSeasonId(User $student): ?int
    {
        if (! $student->school_id) {
            return null;
        }
        return Season::where('school_id', $student->school_id)
            ->where('is_active', true)
            ->orderByDesc('starts_at')
            ->value('id');
    }
}
