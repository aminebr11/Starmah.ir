<?php

namespace App\Support\Bot;

use Illuminate\Support\Facades\Http;

/** دریافتِ فایلی که کاربر در «بله» یا «تلگرام» فرستاده (عکس، PDF، صدا). */
class BotFile
{
    /** برای آزمایش: file_id → محتوا */
    public static ?array $fake = null;

    /** @return array{ok:bool, bytes?:string, error?:string} */
    public static function download(string $platform, string $fileId, int $maxBytes = 15 * 1024 * 1024): array
    {
        if (self::$fake !== null) {
            return isset(self::$fake[$fileId]) ? ['ok' => true, 'bytes' => self::$fake[$fileId]] : ['ok' => false, 'error' => 'فایل پیدا نشد.'];
        }
        $f = BotApi::for($platform)->call('getFile', ['file_id' => $fileId], 20);
        $path = $f['result']['file_path'] ?? null;
        if (! $path) {
            return ['ok' => false, 'error' => 'فایل از پیام‌رسان گرفته نشد' . (! empty($f['description']) ? ' (' . mb_substr((string) $f['description'], 0, 80) . ')' : '') . '.'];
        }
        if (($f['result']['file_size'] ?? 0) > $maxBytes) {
            return ['ok' => false, 'error' => 'حجمِ فایل زیاد است.'];
        }
        try {
            $req = Http::timeout(45);
            if ($platform === 'telegram' && ($k = (string) BotConfig::get('telegram_relay_key', '')) !== '') {
                $req = $req->withHeaders(['X-Relay-Key' => $k]);
            }
            $res = $req->get(BotConfig::apiBase($platform) . '/file/bot' . BotConfig::token($platform) . '/' . ltrim($path, '/'));
            if (! $res->ok() || strlen($res->body()) < 100) {
                return ['ok' => false, 'error' => 'دانلودِ فایل ناموفق بود (' . $res->status() . ').'];
            }
            if (strlen($res->body()) > $maxBytes) {
                return ['ok' => false, 'error' => 'حجمِ فایل زیاد است.'];
            }

            return ['ok' => true, 'bytes' => $res->body()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'دانلودِ فایل ناموفق بود.'];
        }
    }

    /** نوعِ واقعیِ فایل از روی چند بایتِ اول. */
    public static function sniff(string $bytes, string $fallback = 'image/jpeg'): string
    {
        return match (true) {
            str_starts_with($bytes, '%PDF') => 'application/pdf',
            str_starts_with($bytes, "\x89PNG") => 'image/png',
            str_starts_with($bytes, "\xFF\xD8") => 'image/jpeg',
            str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP' => 'image/webp',
            str_starts_with($bytes, 'GIF8') => 'image/gif',
            default => $fallback,
        };
    }
}
