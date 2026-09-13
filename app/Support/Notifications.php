<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\ClassContent;
use App\Models\DisciplineRecord;
use App\Models\Message;
use App\Models\ParentNote;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * فیدِ یکپارچه‌ی اعلان‌ها — یک منبعِ واحد برای زنگوله و صفحه‌ی «اعلان‌ها».
 *
 * چرا یک‌جا: پیش از این زنگوله فیدِ یکپارچه را نشان می‌داد ولی صفحه‌ی
 * اعلان‌ها فقط جدولِ announcements را می‌خواند. نتیجه این بود که پیامِ
 * والدین، موردِ انضباطی، پیامِ صندوق و یادآورِ مأموریت در صفحه‌ی اعلان‌ها
 * اصلاً دیده نمی‌شدند — و چون لِی‌اوتِ معلم/مدیر اصلاً زنگوله‌ی بازشو
 * نداشت، برای آن‌ها هیچ‌جا دیده نمی‌شدند.
 *
 * هر ردیف یک «کلید» یکتا دارد (a12، d3، msg7، pn4، ws9، m-1405-06-22) که
 * با جدولِ notification_reads علامتِ «مطالعه شد» می‌گیرد. پس هر ردیف —
 * از هر منبعی — مستقل خوانده می‌شود.
 */
class Notifications
{
    /** آخرین زمانی که کاربر اعلان‌ها را «دیده» است (برای ردیف‌های عمومی). */
    public static function seenAt(User $user): ?Carbon
    {
        $v = Setting::get('notif_seen:'.$user->id);
        return $v ? Carbon::parse($v) : null;
    }

    /** علامتِ کلیِ «همه را دیدم» (سازگاریِ عقب‌رو با نسخه‌های پیشین). */
    public static function markSeen(User $user): void
    {
        Setting::put('notif_seen:'.$user->id, now()->toDateTimeString());
        self::forget($user);
    }

    /** «مطالعه شد» برای یک ردیفِ مشخص. */
    public static function markRead(User $user, string $key): void
    {
        $key = Str::limit(trim($key), 60, '');
        if ($key === '') {
            return;
        }
        DB::table('notification_reads')->updateOrInsert(
            ['user_id' => $user->id, 'key' => $key],
            ['read_at' => now(), 'updated_at' => now(), 'created_at' => now()],
        );
        self::forget($user);

        // منابعی که ستونِ read_at خودشان را دارند، همان‌جا هم بسته می‌شوند
        // تا شمارنده‌های دیگرِ سایت (نشانِ منوی والدین، صندوقِ پیام) هم بخوابند.
        if (str_starts_with($key, 'a')) {
            DB::table('announcement_recipients')->where('user_id', $user->id)
                ->where('announcement_id', (int) substr($key, 1))->whereNull('read_at')
                ->update(['read_at' => now()]);
        } elseif (str_starts_with($key, 'msg')) {
            Message::where('id', (int) substr($key, 3))->where('recipient_id', $user->id)
                ->whereNull('read_at')->update(['read_at' => now()]);
        } elseif (str_starts_with($key, 'pn') || str_starts_with($key, 'f')) {
            $id = (int) preg_replace('/\D/', '', $key);
            ParentNote::where('id', $id)->whereNull('read_at')->update(['read_at' => now()]);
        }
    }

    /** «همه را خواندم» — تک‌تکِ ردیف‌های فعلیِ فید. */
    public static function markAllRead(User $user): void
    {
        foreach (self::all($user, 200) as $item) {
            if (! $item['read']) {
                self::markRead($user, $item['id']);
            }
        }
        self::markSeen($user);
    }

    /** کلیدهایی که این کاربر خوانده است. */
    private static function readKeys(User $user): \Illuminate\Support\Collection
    {
        return DB::table('notification_reads')->where('user_id', $user->id)
            ->pluck('read_at', 'key');
    }

    /** فیدِ کوتاه برای زنگوله. */
    public static function feed(User $user, int $limit = 12): array
    {
        return array_slice(self::all($user), 0, $limit);
    }

    /** تعدادِ خوانده‌نشده‌ها (نشانِ روی زنگوله و منو). */
    public static function unreadCount(User $user): int
    {
        return collect(self::all($user))->where('read', false)->count();
    }

    /**
     * حافظه‌ی موقتِ هر درخواست.
     *
     * در هر بارگذاری دستِ‌کم دو بار به فید نیاز است (فهرستِ زنگوله و
     * شمارنده‌اش) و ساختنِ فید ده‌ها کوئری دارد. یک‌بار می‌سازیم و همان را
     * می‌دهیم؛ markRead این حافظه را باطل می‌کند.
     *
     * @var array<int,array<int,array<string,mixed>>>
     */
    private static array $cache = [];

    /** پس از تغییرِ وضعیتِ خوانده، فیدِ حافظه‌شده دیگر معتبر نیست. */
    public static function forget(?User $user = null): void
    {
        if ($user) {
            unset(self::$cache[$user->id]);
        } else {
            self::$cache = [];
        }
    }

    /**
     * فیدِ کاملِ کاربر — همان چیزی که هم زنگوله و هم صفحه‌ی اعلان‌ها
     * نشان می‌دهند. مرتب بر اساسِ زمان، تازه‌ترین اول.
     */
    public static function all(User $user, int $limit = 60): array
    {
        if (isset(self::$cache[$user->id])) {
            return array_slice(self::$cache[$user->id], 0, $limit);
        }
        $rows = self::build($user);
        self::$cache[$user->id] = $rows;

        return array_slice($rows, 0, $limit);
    }

    /** @return array<int,array<string,mixed>> */
    private static function build(User $user): array
    {
        $limit = 200;
        // محتوای زمان‌دارِ سررسیده را همین‌جا منتشر می‌کنیم (میزبان cron ندارد)
        ContentRelease::releaseDue();

        $seen = self::seenAt($user);
        $read = self::readKeys($user);
        $isRead = fn (string $key, bool $fallback = false) => $read->has($key) || $fallback;

        $items = collect();

        // ── ۱) اطلاعیه‌ها و پیام‌های شخصی ─────────────────────────────
        $unreadPersonal = DB::table('announcement_recipients')
            ->where('user_id', $user->id)->whereNull('read_at')->pluck('announcement_id')->flip();

        foreach (Announcement::forUser($user)->with('sender:id,name')->latest()->limit($limit)->get() as $a) {
            $personal = $a->audience === 'personal';
            $link = trim((string) ($a->link ?? ''));
            $isGame = str_starts_with($a->title, '🎮');
            $items->push([
                'id'     => 'a'.$a->id,
                // شناسه‌ی خامِ اطلاعیه — فقط این نوع ردیف قابلِ «حذف» است
                'ann'    => $a->id,
                'kind'   => $isGame ? 'game' : ($personal ? 'message' : 'announcement'),
                'group'  => $personal ? 'personal' : 'public',
                'icon'   => $isGame ? '🎮' : ($personal ? '✉️' : '📢'),
                'color'  => $isGame ? '#e8505b' : ($personal ? '#7c5cf0' : '#3d7bf0'),
                'title'  => $a->title,
                'body'   => $a->body,
                'sender' => $a->sender?->name,
                'date'   => Jalali::format($a->created_at, true),
                'href'   => $link !== '' ? $link : ($isGame ? '/game-world' : '/notices'),
                'read'   => $isRead('a'.$a->id, $personal
                    ? ! $unreadPersonal->has($a->id)
                    : (bool) ($seen && $a->created_at->lessThanOrEqualTo($seen))),
                'ts'     => $a->created_at->timestamp,
            ]);
        }

        // ── ۲) موارد انضباطی (دانش‌آموز) ──────────────────────────────
        foreach (DisciplineRecord::where('student_id', $user->id)
            ->where('created_at', '>=', now()->subDays(30))->latest()->limit($limit)->get() as $r) {
            $plus = $r->points >= 0;
            $items->push([
                'id'    => 'd'.$r->id,
                'kind'  => $plus ? 'star' : 'warn',
                'group' => 'personal',
                'icon'  => $plus ? '🌟' : '⚠️',
                'color' => $plus ? '#2bb673' : '#e8505b',
                'title' => ($r->title ?: ($plus ? 'تشویق' : 'تذکر')).' ('.($plus ? '+' : '').$r->points.')',
                'body'  => $r->note,
                'date'  => Jalali::format($r->created_at, true),
                'href'  => '/my-discipline',
                'read'  => $isRead('d'.$r->id, (bool) ($seen && $r->created_at->lessThanOrEqualTo($seen))),
                'ts'    => $r->created_at->timestamp,
            ]);
        }

        // ── ۳) پیامِ «بخشِ والدین» برای دانش‌آموز ─────────────────────
        if ($user->isStudent() && Schema::hasTable('parent_notes')) {
            foreach (ParentNote::where('student_id', $user->id)->where('from_parent', false)
                ->whereNull('read_at')->latest()->limit(5)->get() as $n) {
                $items->push([
                    'id'    => 'f'.$n->id,
                    'kind'  => 'family',
                    'group' => 'personal',
                    'icon'  => '🔐',
                    'color' => '#b9831a',
                    'title' => 'پیامِ جدید برای والدین',
                    'body'  => 'به پدر و مادرت بگو واردِ «بخشِ والدین» شوند.',
                    'date'  => Jalali::format($n->created_at, true),
                    'href'  => '/family',
                    'read'  => $isRead('f'.$n->id),
                    'ts'    => $n->created_at->timestamp,
                ]);
            }
        }

        // ── ۴) صندوقِ پیام (همه‌ی نقش‌ها) ─────────────────────────────
        if (Schema::hasTable('messages')) {
            foreach (Message::with('sender:id,name')->where('recipient_id', $user->id)
                ->where('created_at', '>=', now()->subDays(30))->latest()->limit($limit)->get() as $m) {
                $items->push([
                    'id'     => 'msg'.$m->id,
                    'kind'   => 'message',
                    'group'  => 'personal',
                    'icon'   => '💬',
                    'color'  => '#7c5cf0',
                    'title'  => 'پیامِ جدید از '.($m->sender?->name ?: 'کاربر'),
                    'body'   => Str::limit((string) $m->body, 140),
                    'sender' => $m->sender?->name,
                    'date'   => Jalali::format($m->created_at, true),
                    'href'   => '/messages?with='.$m->sender_id,
                    'read'   => $isRead('msg'.$m->id, $m->read_at !== null),
                    'ts'     => $m->created_at->timestamp,
                ]);
            }
        }

        // ── ۵) پاسخِ والدین برای معلم و مدیرِ مدرسه ───────────────────
        if (($user->hasRole(Roles::TEACHER) || $user->hasRole(Roles::SCHOOL_ADMIN))
            && Schema::hasTable('parent_notes')) {
            $studentIds = $user->hasRole(Roles::TEACHER)
                ? User::whereHas('classrooms', fn ($q) => $q->where('teacher_id', $user->id))->pluck('id')
                : User::role(Roles::STUDENT)->where('school_id', $user->school_id)->pluck('id');

            foreach (ParentNote::with('student:id,name')->whereIn('student_id', $studentIds)
                ->where('from_parent', true)->where('created_at', '>=', now()->subDays(30))
                ->latest()->limit($limit)->get() as $n) {
                $items->push([
                    'id'    => 'pn'.$n->id,
                    'kind'  => 'family',
                    'group' => 'personal',
                    'icon'  => '👪',
                    'color' => '#b9831a',
                    'title' => 'پیامِ والدِ '.($n->student?->name ?: 'دانش‌آموز'),
                    'body'  => Str::limit((string) ($n->title ? $n->title.' — '.$n->body : $n->body), 140),
                    'date'  => Jalali::format($n->created_at, true),
                    'href'  => '/family-notes?student='.$n->student_id,
                    'read'  => $isRead('pn'.$n->id, $n->read_at !== null),
                    'ts'    => $n->created_at->timestamp,
                ]);
            }
        }

        // ── ۶) کاربرگِ پرشده‌ای که دانش‌آموز فرستاده (معلم) ───────────
        if ($user->hasRole(Roles::TEACHER) && Schema::hasTable('worksheet_submissions')) {
            $subs = \App\Models\WorksheetSubmission::with(['student:id,name', 'worksheet:id,title,teacher_id'])
                ->whereHas('worksheet', fn ($q) => $q->where('teacher_id', $user->id))
                ->whereNotNull('file_path')
                ->where('updated_at', '>=', now()->subDays(14))
                ->latest('updated_at')->limit($limit)->get();
            foreach ($subs as $s) {
                $items->push([
                    'id'    => 'ws'.$s->id,
                    'kind'  => 'worksheet',
                    'group' => 'personal',
                    'icon'  => '🎨',
                    'color' => '#2bb673',
                    'title' => ($s->student?->name ?: 'دانش‌آموز').' کاربرگ فرستاد',
                    'body'  => '«'.($s->worksheet?->title ?: 'کاربرگ').'» — برای دیدنِ فایل کلیک کن.',
                    'date'  => Jalali::format($s->submitted_at ?? $s->updated_at, true),
                    'href'  => '/teacher/worksheets/'.$s->worksheet_id,
                    'read'  => $isRead('ws'.$s->id),
                    'ts'    => ($s->submitted_at ?? $s->updated_at)->timestamp,
                ]);
            }
        }

        // ── ۷) یادآورِ مأموریتِ امروزِ دانش‌آموز ───────────────────────
        // رویدادِ ذخیره‌شده نیست، وضعیتِ همین لحظه است: کلیدش تاریخ‌دار
        // است تا «خواندم»ِ امروز، یادآورِ فردا را خاموش نکند.
        if ($user->isStudent()) {
            $pending = MissionAccess::pendingToday($user);
            if ($pending->isNotEmpty()) {
                $key = 'm-'.now()->toDateString();
                $xp = (int) $pending->sum('xp_reward');
                $n = $pending->count();
                $items->push([
                    'id'    => $key,
                    'kind'  => 'mission',
                    'group' => 'personal',
                    'icon'  => '🎯',
                    'color' => '#e8862e',
                    'title' => 'مأموریتِ امروزت مانده — '.Jalali::fa((string) $n).' مورد',
                    'body'  => $n === 1
                        ? '«'.$pending->first()->title.'» را انجام بده و '.Jalali::fa((string) $xp).' امتیاز بگیر.'
                        : 'با انجامِ همه‌شان '.Jalali::fa((string) $xp).' امتیاز و جعبه‌ی گنجِ روزانه را می‌گیری.',
                    'date'  => Jalali::format(now(), true),
                    'href'  => '/missions',
                    'read'  => $isRead($key),
                    // همیشه بالای فید بماند تا گم نشود
                    'ts'    => now()->timestamp + 1,
                ]);
            }
        }

        return $items->sortByDesc('ts')->take($limit)->values()
            ->map(fn ($i) => collect($i)->except('ts')->all())->all();
    }
}
