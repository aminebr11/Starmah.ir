<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ارسالِ پیامک — سازگار با سامانه‌های مختلفِ ایرانی از طریقِ یک الگوی HTTP قابل‌تنظیم.
 * ادمین در «تنظیمات» درگاه را انتخاب و کلیدها را وارد می‌کند. اگر خاموش باشد،
 * پیام فقط در لاگ ثبت می‌شود (حالتِ توسعه) تا جریانِ برنامه نشکند.
 *
 * قالبِ URL و بدنه از تنظیمات می‌آید و این جای‌گیرها جایگزین می‌شوند:
 *   {api_key} {sender} {to} {message}
 */
class SmsService
{
    public function enabled(): bool
    {
        return (bool) Setting::get('sms_enabled', false)
            && Setting::get('sms_provider', 'off') !== 'off';
    }

    public function provider(): string
    {
        return (string) Setting::get('sms_provider', 'off');
    }

    /** ارسالِ یک پیامک. خروجی: [ok(bool), message(string)]. */
    public function send(string $to, string $message): array
    {
        $to = $this->normalize($to);

        if (! $this->enabled()) {
            Log::info("[SMS OFF] to={$to} msg={$message}");

            return ['ok' => false, 'message' => 'سامانه‌ی پیامک فعال نیست (پیام فقط در لاگ ثبت شد).'];
        }

        $method   = strtoupper((string) Setting::get('sms_http_method', 'GET'));
        $template = (string) Setting::get('sms_url_template', '');
        $bodyTpl  = (string) Setting::get('sms_body_template', '');
        $repl = [
            '{api_key}' => rawurlencode((string) Setting::get('sms_api_key', '')),
            '{sender}'  => rawurlencode((string) Setting::get('sms_sender', '')),
            '{to}'      => rawurlencode($to),
            '{message}' => rawurlencode($message),
        ];

        if ($template === '') {
            return ['ok' => false, 'message' => 'آدرسِ سامانه‌ی پیامک تنظیم نشده است.'];
        }

        try {
            $url = strtr($template, $repl);
            if ($method === 'POST') {
                // بدنه‌ی JSON با جای‌گیرهای بدونِ url-encode
                $rawRepl = [
                    '{api_key}' => (string) Setting::get('sms_api_key', ''),
                    '{sender}'  => (string) Setting::get('sms_sender', ''),
                    '{to}'      => $to,
                    '{message}' => $message,
                ];
                $payload = $bodyTpl ? json_decode(strtr($bodyTpl, array_map('json_encode', $rawRepl) + $rawRepl), true) : [];
                $res = Http::timeout(15)->asJson()->post(strtr($template, $repl), $payload ?: []);
            } else {
                $res = Http::timeout(15)->get($url);
            }

            if ($res->successful()) {
                return ['ok' => true, 'message' => 'پیامک ارسال شد.'];
            }

            return ['ok' => false, 'message' => 'خطای سامانه‌ی پیامک: '.$res->status()];
        } catch (\Throwable $e) {
            Log::warning('SMS send failed: '.$e->getMessage());

            return ['ok' => false, 'message' => 'ارسالِ پیامک ناموفق بود.'];
        }
    }

    /** آزمایشِ اتصال با ارسالِ یک پیامکِ نمونه. */
    public function test(string $to): array
    {
        return $this->send($to, 'ستاره ماه: پیامکِ آزمایشیِ سامانه با موفقیت ارسال شد. ✅');
    }

    private function normalize(string $phone): string
    {
        $p = preg_replace('/\D/', '', $phone);
        // 0912… → می‌ماند؛ اگر لازم بود در قالبِ URL خودِ ادمین 98 را اضافه می‌کند.
        return $p;
    }
}
