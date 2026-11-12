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
        if (! Cache::add('bot-push-lock', 1, 90)) {
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

            BotLink::where('notify', true)->orderBy('notified_at')->limit($limit)->get()->each(function (BotLink $l) {
                $since = $l->notified_at?->timestamp ?? 0;
                $l->forceFill(['notified_at' => now()])->save();
                if (! ($u = self::user($l))) {
                    return;
                }
                $fresh = collect(Notifications::unread($u, 12))->filter(fn ($n) => ($n['at'] ?? 0) > $since)->take(3);
                Notifications::forget($u);
                if ($fresh->isEmpty()) {
                    return;
                }
                $site = rtrim((string) (BotConfig::get('site_url', '') ?: config('app.url')), '/');
                $text = "🔔 اعلانِ تازه\n\n" . $fresh->map(fn ($n) => ($n['icon'] ?? '•') . ' ' . $n['title']
                    . (! empty($n['body']) ? "\n" . Str::limit(strip_tags((string) $n['body']), 140) : '')
                    . (! empty($n['href']) ? "\n" . (str_starts_with($n['href'], 'http') ? $n['href'] : $site . $n['href']) : ''))->implode("\n\n");
                BotApi::for($l->platform)->send($l->chat_id, $text);
            });
        } finally {
            $prev ? Auth::setUser($prev) : Auth::forgetUser();
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
