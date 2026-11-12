<?php

namespace App\Support\Bot;

use Illuminate\Support\Facades\Http;

/**
 * ارسال به بات — یک رابط برای «بله» و «تلگرام» (APIِ هر دو هم‌شکل است: /bot{token}/{method}).
 * خطا هرگز پرتاب نمی‌شود؛ نتیجه {ok, result|description} است تا کارِ اصلی (ثبتِ حضور، نمره…) نخوابد.
 */
class BotApi
{
    /** برای آزمایش: هر درخواست این‌جا ثبت می‌شود به‌جای ارسال. */
    public static ?array $fake = null;

    public function __construct(public string $platform)
    {
    }

    public static function for(string $platform): self
    {
        return new self($platform);
    }

    public function call(string $method, array $params = [], int $timeout = 12): array
    {
        if (self::$fake !== null) {
            self::$fake[] = ['platform' => $this->platform, 'method' => $method, 'params' => $params];

            return ['ok' => true, 'result' => ['message_id' => count(self::$fake)]];
        }
        $token = BotConfig::token($this->platform);
        if ($token === '') {
            return ['ok' => false, 'description' => 'توکنِ بات تنظیم نشده است.'];
        }
        try {
            $req = Http::timeout($timeout)->connectTimeout(6)->acceptJson();
            if ($this->platform === 'telegram' && ($k = (string) BotConfig::get('telegram_relay_key', '')) !== '') {
                $req = $req->withHeaders(['X-Relay-Key' => $k]);
            }
            $res = $req->post(BotConfig::apiBase($this->platform) . '/bot' . $token . '/' . $method, $params);
            $json = $res->json();

            return is_array($json) ? $json : ['ok' => false, 'description' => 'پاسخِ نامعتبر (' . $res->status() . ')'];
        } catch (\Throwable $e) {
            // توکن هرگز در پیامِ خطا نمایش داده نشود
            return ['ok' => false, 'description' => 'اتصال برقرار نشد: ' . mb_substr(str_replace($token, '***', $e->getMessage()), 0, 160)];
        }
    }

    /** دکمه‌ها: [[['text'=>..,'data'=>..] | ['text'=>..,'url'=>..], ...], ...] */
    public static function keyboard(array $rows): array
    {
        return ['inline_keyboard' => array_values(array_map(fn ($row) => array_values(array_map(
            fn ($b) => isset($b['url']) ? ['text' => $b['text'], 'url' => $b['url']] : ['text' => $b['text'], 'callback_data' => mb_substr((string) $b['data'], 0, 60)],
            $row
        )), array_filter($rows)))];
    }

    public function send(string|int $chat, string $text, array $rows = []): array
    {
        $p = ['chat_id' => $chat, 'text' => mb_substr($text, 0, 4000)];
        if ($rows) {
            $p['reply_markup'] = self::keyboard($rows);
        }

        return $this->call('sendMessage', $p);
    }

    public function edit(string|int $chat, int $messageId, string $text, array $rows = []): array
    {
        $p = ['chat_id' => $chat, 'message_id' => $messageId, 'text' => mb_substr($text, 0, 4000)];
        if ($rows) {
            $p['reply_markup'] = self::keyboard($rows);
        }

        return $this->call('editMessageText', $p);
    }

    public function answer(string $callbackId, string $text = ''): void
    {
        $this->call('answerCallbackQuery', array_filter(['callback_query_id' => $callbackId, 'text' => $text]), 6);
    }
}
