<?php

namespace App\Services\Bot;

use App\Models\BotLink;
use App\Models\User;
use App\Support\Bot\BotApi;
use App\Support\Bot\BotConfig;
use App\Support\Notifications;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * پیام‌های خودکارِ بات (میزبان cron ندارد): هر ۹۰ ثانیه حداکثر یک بار، پس از پاسخِ یک درخواستِ
 * معمولیِ سایت (کاربر منتظر نمی‌ماند):
 *   🌅 گزارشِ صبحگاهی — روزهای کاری، ساعتِ ۷ تا ۱۰، روزی یک بار برای هر گفتگو
 *   🔔 اعلان‌های تازه‌ی سایت — حداکثر ۳ تا در یک پیام
 */
class BotPush
{
    public static function due(): void
    {
        if (! BotConfig::enabled() || ! BotLink::ready()) {
            return;
        }
        if (! Cache::add('bot-push-lock', 1, 45)) {
            return;
        }
        app()->terminating(fn () => rescue(fn () => self::run(), null, true));
    }

    public static function run(int $limit = 15): void
    {
        $prev = Auth::user();
        try {
            $now = now();
            // جمعه تعطیل است؛ ساعتِ ۷ تا ۱۰ صبح
            if ($now->dayOfWeek !== 5 && $now->hour >= 7 && $now->hour < 10) {
                BotLink::where('brief', true)->where(fn ($q) => $q->whereNull('brief_on')->orWhereDate('brief_on', '<', $now->toDateString()))
                    ->limit($limit)->get()->each(function (BotLink $l) use ($now) {
                        $l->forceFill(['brief_on' => $now->toDateString()])->save();
                        if (! ($u = self::user($l))) {
                            return;
                        }
                        $bot = new TeacherBot($l->platform);
                        BotApi::for($l->platform)->send($l->chat_id, (new BotReports($u, $bot->classes($u)))->today()
                            . "\n\n(گزارشِ صبحگاهی — از ⚙️ تنظیمات خاموش می‌شود)", [[['text' => '📊 گزارش‌ها', 'data' => 'm:rep'], ['text' => '🏠 منو', 'data' => 'm:main']]]);
                    });
            }

            self::notifyLinks(BotLink::where('notify', true)->orderBy('notified_at')->limit($limit)->get());
        } finally {
            $prev ? Auth::setUser($prev) : Auth::forgetUser();
        }
    }

    /**
     * ⚡ پس از هر کارِ ثبت‌شده در سایت (ارسالِ تکلیف، پیام، اطلاعیه، درخواستِ مدرسه…) — بلافاصله بعد از
     * پاسخ، اعلان‌های تازه‌ی کاربرانِ همان مدرسه (و ادمینِ کل) به بات فرستاده می‌شود. میزبان cron ندارد،
     * پس همین «پس از هر کار» جای زمان‌بندی را می‌گیرد. هم‌زمان فقط یک بررسی برای هر مدرسه اجرا می‌شود؛
     * اگر وسطِ آن کارِ تازه‌ای ثبت شد، یک دورِ دیگر هم اجرا می‌شود تا چیزی جا نماند.
     */
    public static function afterWrite(?int $schoolId): void
    {
        if (! BotConfig::enabled() || ! BotLink::ready()) {
            return;
        }
        $k = 'bot-push-now:' . ($schoolId ?: 0);
        Cache::put($k . ':dirty', microtime(true), 120);
        if (! Cache::add($k . ':lock', 1, 30)) {
            return;   // یک بررسی در جریان است؛ «dirty» باعث می‌شود دوباره اجرا شود
        }
        // وقتی ده‌ها دانش‌آموز هم‌زمان کار می‌کنند: حداکثر هر ۳ ثانیه یک بررسی برای هر مدرسه (بارِ هاست)
        $wait = 3 - (microtime(true) - (float) Cache::get($k . ':last', 0));
        if ($wait > 0 && ! app()->runningUnitTests()) {
            usleep((int) ($wait * 1_000_000));
        }
        $prev = Auth::user();
        try {
            for ($round = 0; $round < 3; $round++) {
                $start = microtime(true);
                $links = BotLink::where('notify', true)
                    ->where(fn ($q) => $q->whereNull('school_id')->when($schoolId, fn ($w) => $w->orWhere('school_id', $schoolId)))
                    ->limit(60)->get();
                self::notifyLinks($links);
                if ((float) Cache::get($k . ':dirty', 0) <= $start) {
                    break;
                }
            }
        } finally {
            Cache::put($k . ':last', microtime(true), 60);
            Cache::forget($k . ':lock');
            $prev ? Auth::setUser($prev) : Auth::forgetUser();
        }
    }

    /** اعلان‌های خوانده‌نشده‌ی تازه (که قبلاً فرستاده نشده‌اند) برای هر گفتگو. */
    private static function notifyLinks($links): void
    {
        foreach ($links as $l) {
            rescue(function () use ($l) {
                $since = ($l->notified_at?->timestamp ?? 0) - 5;   // چند ثانیه هم‌پوشانی؛ تکراری‌ها با «فرستاده‌شده‌ها» حذف می‌شوند
                $l->forceFill(['notified_at' => now()])->save();
                if (! ($u = self::user($l))) {
                    return;
                }
                Notifications::forget($u);
                $sentKey = 'bot-sent:' . $l->id;
                $sent = Cache::get($sentKey, []);
                $fresh = collect(Notifications::unread($u, 15))
                    ->filter(fn ($n) => ($n['at'] ?? 0) >= $since && ! in_array((string) ($n['id'] ?? ''), $sent, true))
                    ->take(5);
                if ($fresh->isEmpty()) {
                    return;
                }
                Cache::put($sentKey, array_slice(array_merge($sent, $fresh->pluck('id')->map(fn ($x) => (string) $x)->all()), -300), now()->addDays(7));
                $site = BotConfig::siteUrl();
                $text = "🔔 اعلانِ تازه\n\n" . $fresh->map(fn ($n) => ($n['icon'] ?? '•') . ' ' . $n['title']
                    . (! empty($n['body']) ? "\n" . Str::limit(strip_tags((string) $n['body']), 160) : '')
                    . (! empty($n['href']) ? "\n" . (str_starts_with($n['href'], 'http') ? $n['href'] : $site . $n['href']) : ''))->implode("\n\n");
                BotApi::for($l->platform)->send($l->chat_id, $text);
            }, null, true);
        }
    }

    private static function user(BotLink $l): ?User
    {
        $u = User::find($l->user_id);
        if (! $u || ! BotConfig::allowed($u)) {
            return null;
        }
        Auth::setUser($u);

        return $u;
    }
}
