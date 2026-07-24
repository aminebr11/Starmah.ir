<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\DisciplineRecord;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * فید یکپارچه‌ی اعلان‌ها — اطلاعیه/پیام + موارد انضباطی.
 * هر رویداد با «خوانده/نخوانده»؛ شمارنده کنار زنگوله و منوی اعلان‌ها.
 */
class Notifications
{
    /** آخرین زمانی که کاربر اعلان‌ها را دیده است. */
    public static function seenAt(User $user): ?Carbon
    {
        $v = Setting::get('notif_seen:'.$user->id);
        return $v ? Carbon::parse($v) : null;
    }

    /** علامت‌زدنِ «دیده‌شد» تا این لحظه (زنگوله و فید خالی می‌شود). */
    public static function markSeen(User $user): void
    {
        Setting::put('notif_seen:'.$user->id, now()->toDateTimeString());
    }

    /** فید یکپارچه (برای زنگوله و صفحه‌ی خانه). */
    public static function feed(User $user, int $limit = 12): array
    {
        $seen = self::seenAt($user);

        // اطلاعیه/پیام‌های قابل‌مشاهده
        $anns = Announcement::forUser($user)->with('sender:id,name')->latest()->limit($limit)->get();
        // خوانده‌نشده‌های شخصی (pivot)
        $unreadPersonal = DB::table('announcement_recipients')
            ->where('user_id', $user->id)->whereNull('read_at')->pluck('announcement_id')->flip();

        $items = $anns->map(function ($a) use ($seen, $unreadPersonal) {
            $personal = $a->audience === 'personal';
            $read = $personal
                ? ! $unreadPersonal->has($a->id)
                : ($seen && $a->created_at->lessThanOrEqualTo($seen));
            // اعلانِ «بازی جدید» → مستقیم به دنیای بازی‌ها
            $isGame = str_starts_with($a->title, '🎮');
            return [
                'id'    => 'a'.$a->id,
                'kind'  => $isGame ? 'game' : ($personal ? 'message' : 'announcement'),
                'icon'  => $isGame ? '🎮' : ($personal ? '✉️' : '📢'),
                'color' => $isGame ? '#e8505b' : ($personal ? '#7c5cf0' : '#3d7bf0'),
                'title' => $a->title,
                'body'  => $a->body,
                'date'  => Jalali::format($a->created_at),
                'href'  => $isGame ? '/game-world' : '/notices',
                'read'  => $read,
                'ts'    => $a->created_at->timestamp,
            ];
        });

        // موارد انضباطی (۷ روز اخیر)
        $disc = DisciplineRecord::where('student_id', $user->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->latest()->limit($limit)->get()
            ->map(function ($r) use ($seen) {
                $plus = $r->points >= 0;
                return [
                    'id'    => 'd'.$r->id,
                    'kind'  => $plus ? 'star' : 'warn',
                    'icon'  => $plus ? '🌟' : '⚠️',
                    'color' => $plus ? '#2bb673' : '#e8505b',
                    'title' => ($r->title ?? ($plus ? 'تشویق' : 'تذکر')).' ('.($plus ? '+' : '').$r->points.')',
                    'body'  => $r->note,
                    'date'  => Jalali::format($r->created_at),
                    'href'  => '/my-discipline',
                    'read'  => $seen && $r->created_at->lessThanOrEqualTo($seen),
                    'ts'    => $r->created_at->timestamp,
                ];
            });

        // پیامِ جدیدِ «بخشِ والدین» — محتوا محرمانه است؛ فقط خبرِ رسیدن نمایش داده می‌شود
        $family = collect();
        if ($user->isStudent() && \Illuminate\Support\Facades\Schema::hasTable('parent_notes')) {
            $family = \App\Models\ParentNote::where('student_id', $user->id)
                ->where('from_parent', false)->whereNull('read_at')
                ->latest()->limit(3)->get()
                ->map(fn ($n) => [
                    'id'    => 'f'.$n->id,
                    'kind'  => 'family',
                    'icon'  => '🔐',
                    'color' => '#b9831a',
                    'title' => 'پیامِ جدید برای والدین',
                    'body'  => 'به پدر و مادرت بگو واردِ «بخشِ والدین» شوند.',
                    'date'  => Jalali::format($n->created_at),
                    'href'  => '/family',
                    'read'  => false,
                    'ts'    => $n->created_at->timestamp,
                ]);
        }

        return $items->concat($disc)->concat($family)
            ->sortByDesc('ts')->take($limit)->values()
            ->map(fn ($i) => collect($i)->except('ts')->all())->all();
    }

    /** تعداد اعلان‌های خوانده‌نشده (برای شمارنده‌ی زنگوله/منو). */
    public static function unreadCount(User $user): int
    {
        return collect(self::feed($user))->where('read', false)->count();
    }
}
