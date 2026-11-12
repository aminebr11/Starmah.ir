<?php

namespace App\Http\Controllers\Bot;

use App\Http\Controllers\Controller;
use App\Models\BotLink;
use App\Models\BotLog;
use App\Support\Bot\BotApi;
use App\Support\Bot\BotConfig;
use App\Support\Jalali;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 🤖 بات — تنظیمات (ادمینِ کل) و «اتصال به بات» (معلم و مدیرِ مدرسه).
 */
class BotSettingsController extends Controller
{
    private const FIELDS = ['bale_token', 'bale_username', 'bale_api', 'telegram_token', 'telegram_username', 'telegram_api', 'telegram_webhook', 'telegram_relay_key', 'site_url'];

    /* ───────────── ادمینِ کل ───────────── */

    public function admin(): Response
    {
        $set = [];
        foreach (self::FIELDS as $f) {
            $v = (string) BotConfig::get($f, '');
            // توکن و کلید کامل نشان داده نمی‌شوند
            $set[$f] = str_ends_with($f, 'token') || $f === 'telegram_relay_key' ? ($v !== '' ? '••••' . substr($v, -4) : '') : $v;
        }

        return Inertia::render('Admin/Bot', [
            'enabled' => BotConfig::enabled(),
            'settings' => $set,
            'defaults' => BotConfig::DEFAULT_API + ['site_url' => BotConfig::siteUrl()],
            'webhooks' => ['bale' => BotConfig::webhookUrl('bale'), 'telegram' => BotConfig::webhookUrl('telegram')],
            'ready' => BotLink::ready(),
            'stats' => BotLink::ready() ? [
                'links' => BotLink::selectRaw('platform, count(*) as n')->groupBy('platform')->pluck('n', 'platform'),
                'today' => BotLog::whereDate('created_at', now()->toDateString())->count(),
            ] : null,
            'logs' => BotLink::ready() ? BotLog::with('user:id,name')->latest('id')->limit(30)->get()->map(fn ($l) => [
                'id' => $l->id, 'user' => $l->user?->name, 'platform' => BotLink::PLATFORMS[$l->platform] ?? $l->platform,
                'action' => $l->action, 'summary' => $l->summary, 'undone' => (bool) $l->undone_at,
                'at' => Jalali::format($l->created_at) . ' ' . Jalali::fa($l->created_at->format('H:i')),
            ]) : [],
        ]);
    }

    public function adminSave(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'bale_token' => ['nullable', 'string', 'max:200'], 'bale_username' => ['nullable', 'string', 'max:80'], 'bale_api' => ['nullable', 'url', 'max:200'],
            'telegram_token' => ['nullable', 'string', 'max:200'], 'telegram_username' => ['nullable', 'string', 'max:80'], 'telegram_api' => ['nullable', 'url', 'max:200'],
            'telegram_webhook' => ['nullable', 'url', 'max:200'], 'telegram_relay_key' => ['nullable', 'string', 'max:120'],
            'site_url' => ['nullable', 'url', 'max:200'],
        ]);
        BotConfig::put('enabled', $request->boolean('enabled') ? '1' : '0');
        foreach (self::FIELDS as $f) {
            if (! array_key_exists($f, $data)) {
                continue;
            }
            $v = trim((string) $data[$f]);
            // «••••1234» یعنی دست نخورده
            if ((str_ends_with($f, 'token') || $f === 'telegram_relay_key') && str_starts_with($v, '••••')) {
                continue;
            }
            BotConfig::put($f, $v);
        }

        return back()->with('flash', '🤖 تنظیماتِ بات ذخیره شد.');
    }

    /** ثبتِ نشانیِ وب‌هوک نزدِ پیام‌رسان (setWebhook) + آزمایشِ توکن (getMe). */
    /**
     * 🩺 عیب‌یابیِ قدم‌به‌قدم روی همین سرور: توکن، دسترسی به پیام‌رسان، نشانیِ دریافت، گزارشِ خودِ
     * پیام‌رسان از آخرین خطای تحویل، و ارسالِ آزمایشی به نشانیِ دریافتِ خودِ سایت.
     */
    public function adminDiagnose(string $platform): JsonResponse
    {
        abort_unless(in_array($platform, ['bale', 'telegram'], true), 404);
        $name = BotLink::PLATFORMS[$platform];
        $steps = [];
        $add = function (bool $ok, string $title, string $detail = '', string $fix = '') use (&$steps) {
            $steps[] = compact('ok', 'title', 'detail', 'fix');

            return $ok;
        };

        $add(BotConfig::enabled(), 'بات روشن است', '', 'تیکِ «بات روشن باشد» را بزنید و ذخیره کنید.');
        if (! $add(BotConfig::token($platform) !== '', 'توکن ثبت شده', '', 'توکنِ بات را از BotFather بگیرید، در کادرِ «توکن» بگذارید و ذخیره کنید.')) {
            return response()->json(['steps' => $steps]);
        }
        $api = BotApi::for($platform);
        $me = $api->call('getMe', [], 15);
        $conn = str_starts_with((string) ($me['description'] ?? ''), 'اتصال برقرار نشد');
        if (! $add(! empty($me['ok']), "سرور به {$name} وصل می‌شود و توکن درست است",
            ! empty($me['ok']) ? '@' . ($me['result']['username'] ?? '') : (string) ($me['description'] ?? ''),
            $conn ? ($platform === 'bale' ? 'سرورِ سایت به tapi.bale.ai وصل نمی‌شود؛ از پشتیبانیِ هاست بخواهید دسترسیِ خروجی به tapi.bale.ai (پورتِ ۴۴۳) را باز کنند.' : 'نشانیِ API باید نشانیِ واسطه باشد و واسطه درست کار کند.')
                : 'توکن را دوباره از BotFather کپی کنید (بدونِ فاصله) و ذخیره کنید.')) {
            return response()->json(['steps' => $steps]);
        }
        if (! empty($me['result']['username'])) {
            BotConfig::put($platform . '_username', $me['result']['username']);
        }

        $url = BotConfig::webhookUrl($platform);
        $add(str_starts_with($url, 'https://') && ! BotConfig::isLocal($url), 'نشانیِ دریافتِ پیام معتبر است', $url,
            'نشانیِ سایت باید https و دامنه‌ی واقعی باشد (نه localhost)؛ در کادرِ «نشانیِ سایت» مثلاً https://starmah.ir بنویسید و ذخیره کنید.');

        $info = $api->call('getWebhookInfo', [], 15);
        $r = $info['result'] ?? [];
        $registered = (string) ($r['url'] ?? '');
        $same = $registered !== '' && rtrim($registered, '/') === rtrim($url, '/');
        if (! $same) {
            // ثبت نشده (یا نشانیِ دیگری ثبت است) → همین‌جا ثبت می‌کنیم و پاسخِ دقیقِ پیام‌رسان را نشان می‌دهیم
            $set = $this->setHook($platform);
            $add(! empty($set['ok']), "ثبتِ نشانی نزدِ {$name}",
                ! empty($set['ok']) ? 'انجام شد ✔' : ('پاسخِ ' . $name . ': ' . ($set['description'] ?? json_encode($set, JSON_UNESCAPED_UNICODE))),
                'متنِ پاسخ را برای پشتیبانی بفرستید.');
            $info = $api->call('getWebhookInfo', [], 15);
            $r = $info['result'] ?? [];
            $registered = (string) ($r['url'] ?? '');
            $same = $registered !== '' && rtrim($registered, '/') === rtrim($url, '/');
        }
        if (empty($info['ok'])) {
            // بعضی پیام‌رسان‌ها getWebhookInfo ندارند؛ این بررسی را رد نمی‌کنیم
            $steps[] = ['ok' => true, 'title' => "{$name} گزارشِ وضعیتِ نشانی را نمی‌دهد (مهم نیست)", 'detail' => (string) ($info['description'] ?? ''), 'fix' => ''];
        } else {
            $add($same, "نشانی نزدِ {$name} ثبت شده", $registered ?: '(هیچ نشانی‌ای ثبت نشده)', '«🔗 اتصال و ثبتِ نشانی» را بزنید.');
        }
        if (! empty($r['last_error_message'])) {
            $add(false, "{$name} آخرین بار نتوانست پیام را به سایت برساند",
                $r['last_error_message'] . (! empty($r['last_error_date']) ? ' — ' . Jalali::format(\Illuminate\Support\Carbon::createFromTimestamp($r['last_error_date'])) . ' ' . date('H:i', $r['last_error_date']) : ''),
                'اگر خطای SSL/گواهی است: گواهیِ https سایت را در cPanel (AutoSSL) بررسی کنید. اگر 403/406 است: ModSecurity هاست درخواست را می‌بندد؛ از پشتیبانیِ هاست بخواهید مسیرِ /bot/ را مستثنا کنند.');
        }
        if (isset($r['pending_update_count']) && $r['pending_update_count'] > 0) {
            $add(false, 'پیام‌های تحویل‌نشده در صف', (string) $r['pending_update_count'] . ' پیام', 'معمولاً بعد از رفعِ خطای بالا خودشان می‌رسند.');
        }

        // ارسالِ آزمایشی از خودِ سرور به نشانیِ دریافت (مسیر، SSL، فایروال، ModSecurity)
        try {
            $res = \Illuminate\Support\Facades\Http::timeout(15)->withoutVerifying()->acceptJson()->post($url, ['update_id' => random_int(1, 1000000000)]);
            $add($res->ok() && ($res->json('ok') === true), 'سایت پیام‌های آزمایشی را می‌پذیرد', 'پاسخِ ' . $res->status(),
                $res->status() === 404 ? 'نسخه‌ی تازه کامل نصب نشده یا نشانیِ سایت اشتباه است.' : ($res->status() === 419 ? 'فایلِ bootstrap/app.php نسخه‌ی ۸۸ نصب نشده است.' : 'لاگِ خطای سایت (storage/logs) را بررسی کنید.'));
        } catch (\Throwable $e) {
            $add(false, 'سایت پیام‌های آزمایشی را می‌پذیرد', mb_substr($e->getMessage(), 0, 160), 'سرور نمی‌تواند به نشانیِ خودش وصل شود؛ نشانیِ سایت را بررسی کنید.');
        }
        $add(BotLink::where('platform', $platform)->exists(), 'دستِ‌کم یک معلم وصل شده', (string) BotLink::where('platform', $platform)->count() . ' اتصال',
            'معلم: منوی «🤖 باتِ بله و تلگرام» ← «اتصال به ' . $name . '» ← کدِ ۶ رقمی را در بات بفرستد.');

        return response()->json(['steps' => $steps]);
    }

    public function adminWebhook(Request $request, string $platform): RedirectResponse
    {
        abort_unless(in_array($platform, ['bale', 'telegram'], true), 404);
        // نشانیِ سایت تنظیم نشده و APP_URL محلی است → نشانیِ همین صفحه
        if (trim((string) BotConfig::get('site_url', '')) === '' && BotConfig::isLocal((string) config('app.url')) && ! BotConfig::isLocal($request->getSchemeAndHttpHost())) {
            BotConfig::put('site_url', $request->getSchemeAndHttpHost());
        }
        $api = BotApi::for($platform);
        $me = $api->call('getMe');
        if (empty($me['ok'])) {
            return back()->with('flash', '⚠️ ' . BotLink::PLATFORMS[$platform] . ': ارتباط با بات برقرار نشد — ' . ($me['description'] ?? 'توکن یا نشانیِ API را بررسی کنید.'));
        }
        if (! empty($me['result']['username'])) {
            BotConfig::put($platform . '_username', $me['result']['username']);
        }
        $r = $this->setHook($platform);

        return back()->with('flash', ! empty($r['ok'])
            ? '✅ ' . BotLink::PLATFORMS[$platform] . ': بات «@' . ($me['result']['username'] ?? '') . '» وصل شد و نشانیِ دریافت ثبت شد.'
            : '⚠️ ' . BotLink::PLATFORMS[$platform] . ': توکن درست است ولی ثبتِ نشانی نشد — ' . ($r['description'] ?? ''));
    }

    /**
     * ثبتِ نشانی نزدِ پیام‌رسان. «بله» فقط url را می‌پذیرد (پارامترهای اضافه‌ی تلگرام مثلِ
     * allowed_updates را رد می‌کند)؛ پس برای بله فقط url فرستاده می‌شود.
     */
    private function setHook(string $platform): array
    {
        $params = ['url' => BotConfig::webhookUrl($platform)];
        if ($platform === 'telegram') {
            $params += ['allowed_updates' => ['message', 'callback_query'], 'max_connections' => 20];
        }
        $r = BotApi::for($platform)->call('setWebhook', $params);
        if (empty($r['ok']) && count($params) > 1) {
            $r = BotApi::for($platform)->call('setWebhook', ['url' => $params['url']]);
        }

        return $r;
    }

    /* ───────────── معلم / مدیرِ مدرسه ───────────── */

    public function connect(Request $request): Response
    {
        $u = $request->user();
        $isAdmin = $u->hasRole(Roles::SCHOOL_ADMIN);

        return Inertia::render('Bot/Connect', [
            'allowed' => BotConfig::allowed($u),
            'globalOn' => BotConfig::enabled(),
            'schoolOn' => $u->school_id ? BotConfig::schoolEnabled((int) $u->school_id) : true,
            'isSchoolAdmin' => $isAdmin,
            'menu' => $u->hasRole(Roles::TEACHER) ? 'teacher' : 'school',
            'platforms' => collect(BotLink::PLATFORMS)->map(fn ($label, $p) => [
                'key' => $p, 'label' => $label, 'ready' => BotConfig::ready($p) && BotConfig::username($p) !== '', 'username' => BotConfig::username($p),
            ])->values(),
            'links' => BotLink::ready() ? BotLink::where('user_id', $u->id)->get()->map(fn ($l) => [
                'id' => $l->id, 'platform' => $l->platform, 'label' => BotLink::PLATFORMS[$l->platform] ?? $l->platform, 'username' => $l->username,
                'brief' => $l->brief, 'notify' => $l->notify, 'since' => Jalali::format($l->created_at),
                'seen' => $l->last_seen_at ? Jalali::format($l->last_seen_at) : null,
            ]) : [],
            'schoolLinks' => $isAdmin && $u->school_id && BotLink::ready()
                ? BotLink::where('school_id', $u->school_id)->with('user:id,name')->get()->map(fn ($l) => [
                    'id' => $l->id, 'user' => $l->user?->name, 'label' => BotLink::PLATFORMS[$l->platform] ?? $l->platform,
                    'seen' => $l->last_seen_at ? Jalali::format($l->last_seen_at) : null,
                ]) : [],
        ]);
    }

    /** کدِ یک‌بارمصرفِ اتصال (۱۰ دقیقه) + پیوندِ مستقیم. */
    public function code(Request $request): JsonResponse
    {
        $data = $request->validate(['platform' => ['required', 'in:bale,telegram']]);
        $u = $request->user();
        abort_unless(BotConfig::allowed($u) && BotConfig::ready($data['platform']), 403, 'بات برای شما فعال نیست.');
        do {
            $code = (string) random_int(100000, 999999);
        } while (Cache::has('botlink:' . $code));
        Cache::put('botlink:' . $code, $u->id, now()->addMinutes(10));
        $bot = BotConfig::username($data['platform']);
        $url = $bot === '' ? null : ($data['platform'] === 'bale' ? "https://ble.ir/{$bot}?start={$code}" : "https://t.me/{$bot}?start={$code}");

        return response()->json(['code' => $code, 'url' => $url, 'bot' => $bot]);
    }

    public function update(Request $request, BotLink $link): RedirectResponse
    {
        abort_unless($link->user_id === $request->user()->id, 403);
        $data = $request->validate(['brief' => ['boolean'], 'notify' => ['boolean']]);
        $link->forceFill($data)->save();

        return back()->with('flash', 'تنظیمات ذخیره شد ✅');
    }

    public function unlink(Request $request, BotLink $link): RedirectResponse
    {
        $u = $request->user();
        $own = $link->user_id === $u->id;
        $admin = $u->hasRole(Roles::SCHOOL_ADMIN) && $u->school_id && (int) $link->school_id === (int) $u->school_id;
        abort_unless($own || $admin, 403);
        rescue(fn () => BotApi::for($link->platform)->send($link->chat_id, '🔌 اتصالِ این گفتگو به حسابِ «ستاره ماه» قطع شد.'), null, false);
        $link->delete();

        return back()->with('flash', '🔌 اتصال قطع شد.');
    }

    /** مدیرِ مدرسه: روشن/خاموشِ بات برای کلِ مدرسه. */
    public function school(Request $request): RedirectResponse
    {
        $u = $request->user();
        abort_unless($u->hasRole(Roles::SCHOOL_ADMIN) && $u->school_id, 403);
        BotConfig::setSchool((int) $u->school_id, $request->boolean('on'));

        return back()->with('flash', $request->boolean('on') ? '🤖 بات برای مدرسه روشن شد.' : '🤖 بات برای مدرسه خاموش شد.');
    }
}
