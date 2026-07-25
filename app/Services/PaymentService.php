<?php

namespace App\Services;

use App\Models\PaymentTransaction;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * درگاهِ پرداخت — سازگار با چند سامانه (زرین‌پال، آی‌دی‌پی و مشابه) با یک انتزاعِ ساده.
 * ادمین در «تنظیمات» درگاه و کلید را وارد می‌کند. اگر خاموش/دستی باشد، تراکنش به‌صورتِ
 * «در انتظارِ تأییدِ دستیِ ادمین» می‌ماند و جریانِ ثبت‌نام نمی‌شکند.
 */
class PaymentService
{
    public function enabled(): bool
    {
        return (bool) Setting::get('pay_enabled', false)
            && Setting::get('pay_provider', 'off') !== 'off';
    }

    public function provider(): string
    {
        return (string) Setting::get('pay_provider', 'off');
    }

    /**
     * آغازِ پرداخت. خروجی: [ok, redirect(url|null), message].
     * در حالتِ خاموش، تراکنش pending می‌ماند و ادمین دستی تأیید می‌کند.
     */
    public function start(PaymentTransaction $tx, string $callbackUrl): array
    {
        if (! $this->enabled()) {
            return ['ok' => false, 'redirect' => null, 'message' => 'درگاهِ پرداخت آنلاین فعال نیست؛ درخواست برای بررسیِ دستیِ مدیرِ سامانه ثبت شد.'];
        }

        $provider = $this->provider();
        $key = (string) Setting::get('pay_merchant_id', '');
        $sandbox = (bool) Setting::get('pay_sandbox', false);

        try {
            if ($provider === 'zarinpal') {
                $base = $sandbox ? 'https://sandbox.zarinpal.com' : 'https://payment.zarinpal.com';
                $res = Http::timeout(20)->asJson()->post("$base/pg/v4/payment/request.json", [
                    'merchant_id' => $key,
                    'amount'      => $tx->amount * 10, // زرین‌پال ریال می‌گیرد
                    'callback_url'=> $callbackUrl,
                    'description' => 'اشتراکِ سامانه‌ی ستاره ماه',
                    'metadata'    => ['mobile' => $tx->payer_phone],
                ]);
                $auth = data_get($res->json(), 'data.authority');
                if ($auth) {
                    $tx->update(['authority' => $auth]);
                    return ['ok' => true, 'redirect' => "$base/pg/StartPay/$auth", 'message' => null];
                }
                return ['ok' => false, 'redirect' => null, 'message' => 'خطا در ایجادِ تراکنشِ زرین‌پال.'];
            }

            if ($provider === 'idpay') {
                $res = Http::timeout(20)->withHeaders(['X-API-KEY' => $key, 'X-SANDBOX' => $sandbox ? '1' : '0'])
                    ->asJson()->post('https://api.idpay.ir/v1.1/payment', [
                        'order_id' => $tx->id, 'amount' => $tx->amount * 10,
                        'phone' => $tx->payer_phone, 'callback' => $callbackUrl,
                    ]);
                $link = data_get($res->json(), 'link');
                if ($link) {
                    $tx->update(['authority' => data_get($res->json(), 'id')]);
                    return ['ok' => true, 'redirect' => $link, 'message' => null];
                }
                return ['ok' => false, 'redirect' => null, 'message' => 'خطا در ایجادِ تراکنشِ آی‌دی‌پی.'];
            }

            return ['ok' => false, 'redirect' => null, 'message' => 'درگاهِ ناشناخته.'];
        } catch (\Throwable $e) {
            Log::warning('Payment start failed: '.$e->getMessage());

            return ['ok' => false, 'redirect' => null, 'message' => 'اتصال به درگاهِ پرداخت ناموفق بود.'];
        }
    }

    /** تأییدِ بازگشت از درگاه. خروجی: [ok, ref_id, message]. */
    public function verify(PaymentTransaction $tx, array $params): array
    {
        $provider = $this->provider();
        $key = (string) Setting::get('pay_merchant_id', '');
        $sandbox = (bool) Setting::get('pay_sandbox', false);

        try {
            if ($provider === 'zarinpal') {
                if (($params['Status'] ?? '') !== 'OK') {
                    return ['ok' => false, 'ref_id' => null, 'message' => 'پرداخت لغو شد.'];
                }
                $base = $sandbox ? 'https://sandbox.zarinpal.com' : 'https://payment.zarinpal.com';
                $res = Http::timeout(20)->asJson()->post("$base/pg/v4/payment/verify.json", [
                    'merchant_id' => $key, 'amount' => $tx->amount * 10, 'authority' => $tx->authority,
                ]);
                $code = data_get($res->json(), 'data.code');
                if (in_array($code, [100, 101], true)) {
                    return ['ok' => true, 'ref_id' => (string) data_get($res->json(), 'data.ref_id'), 'message' => null];
                }
                return ['ok' => false, 'ref_id' => null, 'message' => 'تأییدِ پرداخت ناموفق بود.'];
            }

            if ($provider === 'idpay') {
                $res = Http::timeout(20)->withHeaders(['X-API-KEY' => $key, 'X-SANDBOX' => $sandbox ? '1' : '0'])
                    ->asJson()->post('https://api.idpay.ir/v1.1/payment/verify', [
                        'id' => $tx->authority, 'order_id' => $tx->id,
                    ]);
                if ((int) data_get($res->json(), 'status') === 100) {
                    return ['ok' => true, 'ref_id' => (string) data_get($res->json(), 'track_id'), 'message' => null];
                }
                return ['ok' => false, 'ref_id' => null, 'message' => 'تأییدِ پرداخت ناموفق بود.'];
            }

            return ['ok' => false, 'ref_id' => null, 'message' => 'درگاهِ ناشناخته.'];
        } catch (\Throwable $e) {
            Log::warning('Payment verify failed: '.$e->getMessage());

            return ['ok' => false, 'ref_id' => null, 'message' => 'خطا در تأییدِ پرداخت.'];
        }
    }
}
