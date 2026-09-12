<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\DisciplineRecord;
use App\Models\Message;
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

        // محتوای زمان‌دارِ سررسیده را همین‌جا منتشر و اعلان می‌کنیم؛
        // میزبانِ اشتراکی cron ندارد، پس نخستین بازدیدِ هر کاربر این کار را می‌کند.
        ContentRelease::releaseDue();

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
            // اگر اطلاعیه مقصدِ خودش را دارد (کاربرگ، تکلیف، کارنامه…) همان
            // اولویت دارد؛ پیش از این نادیده گرفته می‌شد و همه به /notices می‌رفتند.
            $link = trim((string) ($a->link ?? ''));
            return [
                'id'    => 'a'.$a->id,
                'kind'  => $isGame ? 'game' : ($personal ? 'message' : 'announcement'),
                'icon'  => $isGame ? '🎮' : ($personal ? '✉️' : '📢'),
                'color' => $isGame ? '#e8505b' : ($personal ? '#7c5cf0' : '#3d7bf0'),
                'title' => $a->title,
                'body'  => $a->body,
                'date'  => Jalali::format($a->created_at),
                'href'  => $link !== '' ? $link : ($isGame ? '/game-world' : '/notices'),
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

        // پیام‌های «ارتباط با معلم / والدین / مدیر».
        //
        // این پیام‌ها تا امروز هیچ‌جا در زنگوله دیده نمی‌شدند و کاربر تنها
        // وقتی می‌فهمید پیام دارد که خودش صندوقِ پیام را باز می‌کرد. حالا
        // هر پیامِ دریافتیِ دو هفته‌ی اخیر در فید می‌آید و خوانده‌نشده‌ها
        // روی زنگوله شمرده می‌شوند. کلیک → همان گفت‌وگو.
        $msgs = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('messages')) {
            $msgs = Message::with('sender:id,name')
                ->where('recipient_id', $user->id)
                ->where('created_at', '>=', now()->subDays(14))
                ->latest()->limit($limit)->get()
                ->map(fn ($m) => [
                    'id'    => 'msg'.$m->id,
                    'kind'  => 'message',
                    'icon'  => '💬',
                    'color' => '#7c5cf0',
                    'title' => 'پیامِ جدید از ' . ($m->sender?->name ?: 'کاربر'),
                    'body'  => \Illuminate\Support\Str::limit((string) $m->body, 90),
                    'date'  => Jalali::format($m->created_at, true),
                    'href'  => '/messages?with=' . $m->sender_id,
                    'read'  => $m->read_at !== null,
                    'ts'    => $m->created_at->timestamp,
                ]);
        }

        // پاسخِ والدین به معلم/مدیر — «ارتباط با والدین».
        // این هم مثلِ پیام‌ها فقط داخلِ خودِ صفحه دیده می‌شد.
        $fromParents = collect();
        if (($user->hasRole(Roles::TEACHER) || $user->hasRole(Roles::SCHOOL_ADMIN))
            && \Illuminate\Support\Facades\Schema::hasTable('parent_notes')) {
            $studentIds = $user->hasRole(Roles::TEACHER)
                ? User::whereHas('classrooms', fn ($q) => $q->where('teacher_id', $user->id))->pluck('id')
                : User::role(Roles::STUDENT)->where('school_id', $user->school_id)->pluck('id');

            $fromParents = \App\Models\ParentNote::with('student:id,name')
                ->whereIn('student_id', $studentIds)->where('from_parent', true)
                ->whereNull('read_at')->latest()->limit($limit)->get()
                ->map(fn ($n) => [
                    'id'    => 'pn'.$n->id,
                    'kind'  => 'family',
                    'icon'  => '👪',
                    'color' => '#b9831a',
                    'title' => 'پیامِ والدِ ' . ($n->student?->name ?: 'دانش‌آموز'),
                    'body'  => \Illuminate\Support\Str::limit((string) ($n->title ?: $n->body), 90),
                    'date'  => Jalali::format($n->created_at, true),
                    'href'  => '/family-notes?student=' . $n->student_id,
                    'read'  => false,
                    'ts'    => $n->created_at->timestamp,
                ]);
        }

        // یادآورِ مأموریت‌های انجام‌نشده‌ی امروز.
        // این یکی «رویدادِ ذخیره‌شده» نیست، وضعیتِ همین لحظه است: تا وقتی
        // مأموریتی مانده باشد در زنگوله دیده می‌شود و به‌محضِ تمام‌شدنِ
        // همه‌شان خودش می‌رود.
        $missions = collect();
        if ($user->isStudent()) {
            $pending = MissionAccess::pendingToday($user);
            if ($pending->isNotEmpty()) {
                $xp = (int) $pending->sum('xp_reward');
                $n = $pending->count();
                $missions->push([
                    'id'    => 'm-today',
                    'kind'  => 'mission',
                    'icon'  => '🎯',
                    'color' => '#e8862e',
                    'title' => 'مأموریتِ امروزت مانده — ' . Jalali::fa((string) $n) . ' مورد',
                    'body'  => $n === 1
                        ? '«' . $pending->first()->title . '» را انجام بده و ' . Jalali::fa((string) $xp) . ' امتیاز بگیر.'
                        : 'با انجامِ همه‌شان ' . Jalali::fa((string) $xp) . ' امتیاز و جعبه‌ی گنجِ روزانه را می‌گیری.',
                    'date'  => Jalali::format(now()),
                    'href'  => '/missions',
                    'read'  => false,
                    // همیشه بالای فید بماند تا گم نشود
                    'ts'    => now()->timestamp + 1,
                ]);
            }
        }

        return $items->concat($disc)->concat($family)->concat($msgs)->concat($fromParents)->concat($missions)
            ->sortByDesc('ts')->take($limit)->values()
            ->map(fn ($i) => collect($i)->except('ts')->all())->all();
    }

    /** تعداد اعلان‌های خوانده‌نشده (برای شمارنده‌ی زنگوله/منو). */
    public static function unreadCount(User $user): int
    {
        return collect(self::feed($user))->where('read', false)->count();
    }
}
