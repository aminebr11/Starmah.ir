<?php

namespace App\Http\Controllers\Online;

use App\Http\Controllers\Controller;
use App\Services\OnlineClassService;
use App\Support\OnlineAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «🔍 آزمایشِ اتصالِ کلاس» — روی هر دستگاهی (گوشیِ بچه یا رایانه‌ی معلم) قدم‌به‌قدم
 * نشان می‌دهد صدا و تصویر کجا گیر می‌کند: تنظیمِ سایت، سرورِ رسانه، کلیدها، شبکه‌ی همین دستگاه.
 */
class CheckController extends Controller
{
    public function __construct(private OnlineClassService $svc) {}

    public function show(Request $request): Response
    {
        $lk = OnlineAccess::livekit();

        return Inertia::render('Online/Check', [
            'mode' => OnlineAccess::media(),
            'wanted' => (string) \App\Models\Setting::get('online_media', 'builtin'),
            'url' => $lk['url'] ?? null,
            'ice' => OnlineAccess::iceServers(),
            'isAdmin' => $request->user()->hasRole('super_admin'),
            'back' => url()->previous() !== url()->current() ? url()->previous() : route('dashboard'),
        ]);
    }

    /** سمتِ سرورِ سایت: آیا به LiveKit می‌رسد و کلید/رمز درست است؟ + توکنِ اتاقِ آزمایش. */
    public function server(Request $request): JsonResponse
    {
        $lk = OnlineAccess::livekit();
        if (! $lk) {
            return response()->json(['ok' => false, 'step' => 'config', 'message' => 'سرورِ رسانه (LiveKit) در تنظیماتِ ادمینِ کل کامل وارد نشده است.']);
        }
        $http = preg_replace('#^ws(s?)://#i', 'http$1://', rtrim($lk['url'], '/'));
        $tok = $this->svc->livekitTokenFor('starmah-check-' . $request->user()->id, $request->user(), 'student', 600);
        $out = ['url' => $lk['url'], 'http' => $http, 'token' => $tok['token']];

        // دامنه‌ی سرورِ رسانه باید فقط به یک IP (خودِ VPS) اشاره کند
        $host = (string) parse_url($http, PHP_URL_HOST);
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (@gethostbynamel($host) ?: []);
        $out['ips'] = $ips;
        if (count($ips) > 1) {
            return response()->json($out + ['ok' => false, 'step' => 'dns',
                'message' => 'دامنه‌ی ' . $host . ' به چند IP اشاره می‌کند (' . implode('، ', $ips) . '). فقط IPِ سرورِ رسانه باید بماند؛ بقیه را در cPanel ← Zone Editor پاک کنید (و اگر در cPanel ← Domains زیردامنه‌ی هم‌نام ساخته شده، آن را حذف کنید).']);
        }
        try {
            $ping = Http::timeout(10)->get($http . '/');
        } catch (\Throwable $e) {
            return response()->json($out + ['ok' => false, 'step' => 'reach',
                'message' => 'سرورِ سایت به ' . $http . ' وصل نشد: ' . mb_substr($e->getMessage(), 0, 160)]);
        }
        if ($ping->successful() && trim($ping->body()) !== 'OK') {
            return response()->json($out + ['ok' => false, 'step' => 'reach',
                'message' => 'نشانیِ ' . $http . ' جواب داد ولی جواب از LiveKit نیست (احتمالاً دامنه به هاستِ سایت اشاره می‌کند' . ($ips ? '؛ IP: ' . implode('، ', $ips) : '') . '). رکوردِ A را در cPanel ← Zone Editor فقط روی IPِ سرورِ رسانه بگذارید.']);
        }
        if (! $ping->successful()) {
            return response()->json($out + ['ok' => false, 'step' => 'reach',
                'message' => 'نشانیِ ' . $http . ' جواب داد ولی LiveKit نبود (کد ' . $ping->status() . '). nginx یا سرویسِ livekit را بررسی کنید.']);
        }
        try {
            $v = Http::timeout(10)->get($http . '/rtc/validate', ['access_token' => $tok['token']]);
        } catch (\Throwable $e) {
            return response()->json($out + ['ok' => false, 'step' => 'keys', 'message' => 'بررسیِ کلیدها انجام نشد: ' . mb_substr($e->getMessage(), 0, 160)]);
        }
        if (! $v->successful()) {
            $body = trim(mb_substr(preg_replace('/eyJ[\w.-]+/', '…', $v->body()), 0, 160));
            $why = str_contains($body, 'not valid yet') || str_contains($body, 'expired')
                ? 'ساعتِ سرورِ سایت و سرورِ رسانه با هم فرق دارد (روی VPS بزنید: timedatectl set-ntp true).'
                : 'API Key یا API Secret در تنظیماتِ سایت با سرور یکی نیست. دوباره از «cat /etc/livekit/keys» کپی کنید.';

            return response()->json($out + ['ok' => false, 'step' => 'keys', 'message' => $why . ' (' . $body . ')']);
        }

        return response()->json($out + ['ok' => true, 'step' => 'done', 'message' => 'سرورِ سایت به LiveKit می‌رسد و کلید و رمز درست است.']);
    }
}
