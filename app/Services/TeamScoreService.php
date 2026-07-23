<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\TeamPoint;
use App\Models\XpEntry;
use App\Support\Jalali;
use Illuminate\Support\Collection;

/**
 * منبعِ یگانه‌ی امتیازِ تیمی (گروهِ تم‌دار).
 * مجموعِ تیم = جمعِ XP اعضا + جمعِ امتیازهای دستیِ گروهی (team_points).
 * همچنین «دفترِ ریزِ گروه»: از کجا امتیاز آمده (فعالیتِ اعضا + امتیازِ دستی).
 */
class TeamScoreService
{
    /** خلاصه‌ی همه‌ی تیم‌های یک کلاس. */
    public function teams(Classroom $classroom, ?int $meId = null): array
    {
        $students = $classroom->students()->with('theme')->get();
        $manual = TeamPoint::where('classroom_id', $classroom->id)
            ->selectRaw('theme_id, SUM(amount) as s')->groupBy('theme_id')->pluck('s', 'theme_id');

        return $students->whereNotNull('theme_id')->groupBy('theme_id')->map(function ($members, $themeId) use ($manual, $meId) {
            $theme = $members->first()->theme;
            $memberXp = $members->sum(fn ($s) => $s->totalXp());
            $bonus = (int) ($manual[$themeId] ?? 0);
            return [
                'theme_id' => (int) $themeId,
                'name' => $theme?->name ?? 'تیم',
                'emoji' => $theme?->emoji ?? '👥',
                'color' => data_get($theme?->skin, 'p1', '#888'),
                'members_xp' => (int) $memberXp,
                'bonus' => $bonus,
                'total' => (int) $memberXp + $bonus,
                'count' => $members->count(),
                'mine' => $meId !== null && $members->contains(fn ($s) => $s->id === $meId),
                'members' => $members->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'xp' => $s->totalXp(), 'me' => $s->id === $meId])
                    ->sortByDesc('xp')->values()->all(),
            ];
        })->sortByDesc('total')->values()->all();
    }

    /** دفترِ ریزِ یک تیم در یک کلاس: هر ردیفِ امتیازِ اعضا + امتیازهای دستیِ گروهی. */
    public function ledger(int $themeId, Classroom $classroom, int $limit = 80): array
    {
        $memberIds = $classroom->students()->where('theme_id', $themeId)->pluck('users.id');

        $entries = XpEntry::whereIn('student_id', $memberIds)->with('student:id,name')
            ->latest('id')->limit($limit)->get()
            ->map(fn ($e) => [
                'kind' => 'member',
                'who' => $e->student?->name,
                'amount' => (int) $e->amount,
                'reason' => $e->reason ?: $this->sourceLabel($e->source_type),
                'date' => Jalali::format($e->created_at, true),
                'ts' => $e->created_at?->timestamp ?? 0,
            ]);

        $manual = TeamPoint::where('classroom_id', $classroom->id)->where('theme_id', $themeId)
            ->with('awardedBy:id,name')->latest('id')->limit($limit)->get()
            ->map(fn ($t) => [
                'kind' => 'team',
                'who' => optional($t->awardedBy)->name ?? 'معلم',
                'amount' => (int) $t->amount,
                'reason' => '🏆 امتیازِ گروهی' . ($t->reason ? ' — ' . $t->reason : ''),
                'id' => $t->id,
                'date' => Jalali::format($t->created_at, true),
                'ts' => $t->created_at?->timestamp ?? 0,
            ]);

        return $entries->concat($manual)->sortByDesc('ts')->take($limit)->values()->all();
    }

    private function sourceLabel(?string $type): string
    {
        return match (true) {
            str_contains((string) $type, 'Mission') => '🎯 مأموریت',
            str_contains((string) $type, 'EduGame') => '🎮 بازی',
            str_contains((string) $type, 'SmartExam') => '🧠 آزمون هوشمند',
            str_contains((string) $type, 'Worksheet') => '🎨 کاربرگ',
            str_contains((string) $type, 'ActivityAward') => '🏅 فعالیتِ کلاسی',
            str_contains((string) $type, 'Grade') => '📔 نمره',
            default => '⚡ امتیاز',
        };
    }
}
