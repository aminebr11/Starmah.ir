<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/** فراخوانیِ سادهِ متن‌به‌متن روی هر سرویسی که در AiConfig تعریف شده. */
class AiChat
{
    /**
     * @return array{ok:bool,text:string,status:int,error:?string,model:string,ms:int,in:int,out:int}
     */
    public static function send(string $system, string $user, int $maxTokens = 700, ?string $provider = null, int $timeout = 40): array
    {
        $p = $provider ?? AiConfig::provider();
        $model = AiConfig::model($p);
        $key = AiConfig::key($p);
        $t0 = microtime(true);
        $base = ['ok' => false, 'text' => '', 'status' => 0, 'error' => null, 'model' => $model, 'ms' => 0, 'in' => 0, 'out' => 0];
        if ($p === 'off') return ['error' => 'هوش مصنوعی خاموش است.'] + $base;
        if (! $key) return ['error' => 'کلیدِ این سرویس وارد نشده است.'] + $base;
        if (AiConfig::family($p) === 'openai' && ! AiConfig::base($p)) return ['error' => 'نشانیِ سرویس (Base URL) وارد نشده است.'] + $base;

        try {
            if (AiConfig::family($p) === 'anthropic') {
                $res = Http::withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01', 'content-type' => 'application/json'])
                    ->timeout($timeout)->post(AiConfig::base($p) . '/messages', [
                        'model' => $model, 'max_tokens' => $maxTokens, 'system' => $system,
                        'messages' => [['role' => 'user', 'content' => $user]],
                    ]);
                $text = collect(data_get($res->json(), 'content', []))->where('type', 'text')->pluck('text')->implode("\n");
                $in = (int) data_get($res->json(), 'usage.input_tokens', 0);
                $out = (int) data_get($res->json(), 'usage.output_tokens', 0);
            } else {
                $res = AiConfig::postChat($key, [
                    'model' => $model, 'max_tokens' => $maxTokens,
                    'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
                ], $timeout, $p);
                $text = (string) data_get($res->json(), 'choices.0.message.content', '');
                $in = (int) data_get($res->json(), 'usage.prompt_tokens', 0);
                $out = (int) data_get($res->json(), 'usage.completion_tokens', 0);
            }
            $ms = (int) round((microtime(true) - $t0) * 1000);
            if (! $res->successful()) {
                return ['status' => $res->status(), 'ms' => $ms, 'error' => self::friendly($res->status(), (string) (data_get($res->json(), 'error.message') ?: $res->body()))] + $base;
            }

            return ['ok' => true, 'text' => trim($text), 'status' => $res->status(), 'error' => null, 'model' => $model, 'ms' => $ms, 'in' => $in, 'out' => $out];
        } catch (\Throwable $e) {
            return ['ms' => (int) round((microtime(true) - $t0) * 1000), 'error' => 'به سرویس وصل نشد (شبکه یا فیلتر): ' . mb_substr($e->getMessage(), 0, 120)] + $base;
        }
    }

    public static function friendly(int $status, string $msg): string
    {
        $m = mb_substr(trim(strip_tags($msg)), 0, 160);

        return match (true) {
            $status === 401 || $status === 403 => 'کلید نامعتبر است یا دسترسی ندارد (' . $status . '). ' . $m,
            $status === 402 => 'اعتبارِ حسابِ این سرویس تمام شده است. ' . $m,
            $status === 404 => 'مدل یا نشانیِ سرویس پیدا نشد؛ نامِ مدل را بررسی کنید. ' . $m,
            $status === 429 => 'سقفِ درخواست یا سهمیه پر شده؛ کمی بعد دوباره امتحان کنید. ' . $m,
            $status >= 500 => 'سرورِ سرویسِ هوش مصنوعی خطا داد (' . $status . '). ' . $m,
            default => 'خطای ' . $status . ': ' . $m,
        };
    }
}
