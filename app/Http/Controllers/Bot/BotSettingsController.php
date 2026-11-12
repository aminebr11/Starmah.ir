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
            'defaults' => BotConfig::DEFAULT_API + ['site_url' => config('app.url')],
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
    public function adminWebhook(string $platform): RedirectResponse
    {
        abort_unless(in_array($platform, ['bale', 'telegram'], true), 404);
        $api = BotApi::for($platform);
        $me = $api->call('getMe');
        if (empty($me['ok'])) {
            return back()->with('flash', '⚠️ ' . BotLink::PLATFORMS[$platform] . ': ارتباط با بات برقرار نشد — ' . ($me['description'] ?? 'توکن یا نشانیِ API را بررسی کنید.'));
        }
        if (! empty($me['result']['username'])) {
            BotConfig::put($platform . '_username', $me['result']['username']);
        }
        $r = $api->call('setWebhook', ['url' => BotConfig::webhookUrl($platform), 'allowed_updates' => ['message', 'callback_query'], 'max_connections' => 20]);

        return back()->with('flash', ! empty($r['ok'])
            ? '✅ ' . BotLink::PLATFORMS[$platform] . ': بات «@' . ($me['result']['username'] ?? '') . '» وصل شد و نشانیِ دریافت ثبت شد.'
            : '⚠️ ' . BotLink::PLATFORMS[$platform] . ': توکن درست است ولی ثبتِ نشانی نشد — ' . ($r['description'] ?? ''));
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
