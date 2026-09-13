<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ارسالِ پیامک — با دو حالت:
 *
 *   • iransms  → درایورِ اختصاصیِ iransmsservice.com (نقطه‌ی bulk2).
 *                کلید در هدرِ apikey می‌رود و بدنه form-urlencoded است،
 *                پس با قالبِ عمومیِ URL قابلِ بیان نبود و درایورِ جدا خواست.
 *                یک درخواست می‌تواند چند گیرنده داشته باشد (با کاما).
 *   • custom   → همان قالبِ آزادِ قبلی برای هر سامانه‌ی دیگر
 *                (جای‌گیرها: {api_key} {sender} {to} {message}).
 *
 * اگر خاموش باشد پیام فقط در لاگ می‌نشیند تا جریانِ برنامه نشکند.
 */
class SmsService
{
    public const IRANSMS_URL = 'https://api.iransmsservice.com/v2/sms/send/bulk2';

    public function enabled(): bool
    {
        return (bool) Setting::get('sms_enabled', false)
            && Setting::get('sms_provider', 'off') !== 'off';
    }

    public function provider(): string
    {
        return (string) Setting::get('sms_provider', 'off');
    }

    /** خطِ ارسالِ پیش‌فرضِ سامانه (مدرسه می‌تواند خطِ خودش را داشته باشد). */
    public function sender(): string
    {
        return (string) Setting::get('sms_sender', '');
    }

    /**
     * ارسال به یک یا چند شماره.
     *
     * @param  string|array<int,string>  $to
     * @return array{ok:bool,message:string,ids:array<string,string>}
     */
    public function send(string|array $to, string $message, ?string $sender = null): array
    {
        $numbers = collect(is_array($to) ? $to : [$to])
            ->map(fn ($n) => $this->normalize($n))->filter()->unique()->values();

        if ($numbers->isEmpty()) {
            return ['ok' => false, 'message' => 'شماره‌ی معتبری برای ارسال نبود.', 'ids' => []];
        }

        if (! $this->enabled()) {
            Log::info('[SMS OFF] to=' . $numbers->implode(',') . " msg={$message}");

            return ['ok' => false, 'message' => 'سامانه‌ی پیامک فعال نیست (پیام فقط در لاگ ثبت شد).', 'ids' => []];
        }

        return $this->provider() === 'iransms'
            ? $this->sendIranSms($numbers->all(), $message, $sender)
            : $this->sendCustom($numbers->all(), $message, $sender);
    }

    /**
     * درایورِ iransmsservice — نقطه‌ی bulk2.
     *
     * پاسخِ موفق: {"result":"success","messageids":"123,124"} — شناسه‌ها
     * به همان ترتیبِ گیرنده‌ها برمی‌گردند، پس یک‌به‌یک نگاشت می‌شوند.
     * در خطا، messageids حاملِ کدِ خطاست.
     *
     * @param  array<int,string>  $numbers
     */
    private function sendIranSms(array $numbers, string $message, ?string $sender): array
    {
        $key = (string) Setting::get('sms_api_key', '');
        $line = $sender ?: $this->sender();

        if ($key === '' || $line === '') {
            return ['ok' => false, 'message' => 'کلیدِ API یا خطِ ارسال تنظیم نشده است.', 'ids' => []];
        }

        try {
            $res = Http::withHeaders(['apikey' => $key])
                ->asForm()->timeout(30)
                ->post(self::IRANSMS_URL, [
                    'message'  => $message,
                    'sender'   => $line,
                    'receptor' => implode(',', $numbers),
                ]);

            if (! $res->successful()) {
                return ['ok' => false, 'message' => 'خطای سامانه‌ی پیامک (HTTP ' . $res->status() . ')', 'ids' => []];
            }

            $json = $res->json();
            if (! is_array($json)) {
                return ['ok' => false, 'message' => 'پاسخِ نامعتبر از سامانه‌ی پیامک.', 'ids' => []];
            }

            $raw = (string) ($json['messageids'] ?? '');
            if (($json['result'] ?? '') !== 'success') {
                return ['ok' => false, 'message' => 'ارسال ناموفق — کدِ خطا: ' . ($raw ?: 'نامشخص'), 'ids' => []];
            }

            $ids = [];
            foreach (array_map('trim', explode(',', $raw)) as $i => $id) {
                if (isset($numbers[$i])) {
                    $ids[$numbers[$i]] = $id;
                }
            }

            return ['ok' => true, 'message' => 'پیامک ارسال شد.', 'ids' => $ids];
        } catch (\Throwable $e) {
            Log::warning('SMS (iransms) failed: ' . $e->getMessage());

            return ['ok' => false, 'message' => 'ارسالِ پیامک ناموفق بود: ' . $e->getMessage(), 'ids' => []];
        }
    }

    /**
     * قالبِ آزاد برای هر سامانه‌ی دیگر — همان رفتارِ قبلی، ولی حالا
     * چندگیرنده‌ای (هر شماره یک درخواست).
     *
     * @param  array<int,string>  $numbers
     */
    private function sendCustom(array $numbers, string $message, ?string $sender): array
    {
        $template = (string) Setting::get('sms_url_template', '');
        if ($template === '') {
            return ['ok' => false, 'message' => 'آدرسِ سامانه‌ی پیامک تنظیم نشده است.', 'ids' => []];
        }

        $method = strtoupper((string) Setting::get('sms_http_method', 'GET'));
        $bodyTpl = (string) Setting::get('sms_body_template', '');
        $key = (string) Setting::get('sms_api_key', '');
        $line = $sender ?: $this->sender();

        $ok = 0;
        $fail = null;
        foreach ($numbers as $to) {
            $repl = [
                '{api_key}' => rawurlencode($key),
                '{sender}'  => rawurlencode($line),
                '{to}'      => rawurlencode($to),
                '{message}' => rawurlencode($message),
            ];
            try {
                $url = strtr($template, $repl);
                if ($method === 'POST') {
                    $raw = ['{api_key}' => $key, '{sender}' => $line, '{to}' => $to, '{message}' => $message];
                    $payload = $bodyTpl ? json_decode(strtr($bodyTpl, $raw), true) : [];
                    $res = Http::timeout(15)->asJson()->post($url, $payload ?: []);
                } else {
                    $res = Http::timeout(15)->get($url);
                }
                $res->successful() ? $ok++ : $fail = 'HTTP ' . $res->status();
            } catch (\Throwable $e) {
                $fail = $e->getMessage();
            }
        }

        return $ok > 0
            ? ['ok' => true, 'message' => "پیامک برای {$ok} شماره ارسال شد.", 'ids' => []]
            : ['ok' => false, 'message' => 'ارسالِ پیامک ناموفق بود' . ($fail ? " ({$fail})" : ''), 'ids' => []];
    }

    /** آزمایشِ اتصال با ارسالِ یک پیامکِ نمونه. */
    public function test(string $to): array
    {
        return $this->send($to, 'ستاره ماه: پیامکِ آزمایشیِ سامانه با موفقیت ارسال شد. ✅');
    }

    /**
     * تعدادِ «قطعه»ی یک پیام — مبنای محاسبه‌ی مصرف.
     * پیامکِ فارسی (یونیکد) ۷۰ نویسه در هر قطعه است، نه ۱۶۰.
     */
    public static function segments(string $message): int
    {
        $len = mb_strlen($message);
        $ascii = preg_match('/^[\x00-\x7F]*$/', $message) === 1;
        $per = $ascii ? 160 : 70;

        return max(1, (int) ceil($len / $per));
    }

    /** ۰۹…، ۹۸۹…، +۹۸۹… همه به ۰۹… تبدیل می‌شوند. */
    public function normalize(string $phone): string
    {
        $p = preg_replace('/\D/', '', strtr($phone, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']));
        if (str_starts_with($p, '0098')) {
            $p = '0' . substr($p, 4);
        } elseif (str_starts_with($p, '98') && strlen($p) === 12) {
            $p = '0' . substr($p, 2);
        } elseif (strlen($p) === 10 && str_starts_with($p, '9')) {
            $p = '0' . $p;
        }

        return preg_match('/^09\d{9}$/', $p) === 1 ? $p : '';
    }
}
