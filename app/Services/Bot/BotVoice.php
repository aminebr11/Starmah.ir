<?php

namespace App\Services\Bot;

use App\Models\Setting;
use App\Support\AiConfig;
use App\Support\Bot\BotApi;
use App\Support\Bot\BotConfig;
use Illuminate\Support\Facades\Http;

/**
 * 🎙️ پیامِ صوتیِ معلم → متن.
 *
 * ترتیبِ تلاش (اولین مورد که کلید/نشانی دارد):
 *   ۱) سرویسِ «صدا به متن»ی که ادمین در صفحه‌ی بات گذاشته (هر سرویسِ سازگار با OpenAI)
 *   ۲) هوش مصنوعیِ فعالِ سایت اگر از خانواده‌ی OpenAI است (مثلاً واسطه‌های ایرانیِ سازگار) → /audio/transcriptions
 *   ۳) کلیدِ OpenAI ← whisper / gpt-4o-mini-transcribe
 *   ۴) کلیدِ Gemini ← فهمِ صدا با مدلِ Gemini
 */
class BotVoice
{
    /** برای آزمایش: متنِ ثابت به‌جای تبدیلِ واقعی. */
    public static ?string $fake = null;

    /** @return array{ok:bool,text?:string,error?:string} */
    public static function fromMessage(string $platform, array $voice): array
    {
        if (($voice['duration'] ?? 0) > 180) {
            return ['ok' => false, 'error' => 'پیامِ صوتی حداکثر ۳ دقیقه باشد.'];
        }
        if (self::$fake !== null) {
            return ['ok' => true, 'text' => self::$fake];
        }
        $api = BotApi::for($platform);
        $f = $api->call('getFile', ['file_id' => $voice['file_id'] ?? '']);
        $path = $f['result']['file_path'] ?? null;
        if (! $path) {
            return ['ok' => false, 'error' => 'فایلِ صوتی از پیام‌رسان گرفته نشد.'];
        }
        try {
            $req = Http::timeout(40);
            if ($platform === 'telegram' && ($k = (string) BotConfig::get('telegram_relay_key', '')) !== '') {
                $req = $req->withHeaders(['X-Relay-Key' => $k]);
            }
            $res = $req->get(BotConfig::apiBase($platform) . '/file/bot' . BotConfig::token($platform) . '/' . ltrim($path, '/'));
            if (! $res->ok() || strlen($res->body()) < 100) {
                return ['ok' => false, 'error' => 'دانلودِ صدا ناموفق بود (' . $res->status() . ').'];
            }
            $bytes = $res->body();
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'دانلودِ صدا ناموفق بود.'];
        }

        return self::transcribe($bytes, $voice['mime_type'] ?? 'audio/ogg');
    }

    public static function transcribe(string $bytes, string $mime = 'audio/ogg'): array
    {
        $ext = str_contains($mime, 'mpeg') ? 'mp3' : (str_contains($mime, 'mp4') || str_contains($mime, 'm4a') ? 'm4a' : (str_contains($mime, 'wav') ? 'wav' : 'ogg'));
        $tried = [];

        // ۱) و ۲) و ۳): سرویس‌های سازگار با OpenAI
        foreach (self::openaiTargets() as [$base, $key, $models]) {
            foreach ($models as $model) {
                $tried[] = $model;
                try {
                    $res = Http::timeout(60)->withToken($key)->attach('file', $bytes, 'voice.' . $ext)
                        ->post(rtrim($base, '/') . '/audio/transcriptions', ['model' => $model, 'language' => 'fa', 'response_format' => 'json',
                            'prompt' => 'گفتگوی معلمِ دبستان دربارهٔ حضور و غیاب، نمره، امتیاز و اطلاعیه‌ی کلاس.']);
                    $text = trim((string) ($res->json('text') ?? ''));
                    if ($res->ok() && $text !== '') {
                        return ['ok' => true, 'text' => $text];
                    }
                } catch (\Throwable $e) {
                    // مدلِ بعدی
                }
            }
        }

        // ۴) Gemini (فهمِ صدا)
        if ($key = AiConfig::key('gemini')) {
            $tried[] = 'gemini';
            try {
                $base = preg_replace('#/openai/?$#', '', (string) AiConfig::base('gemini')) ?: 'https://generativelanguage.googleapis.com/v1beta';
                $res = Http::timeout(60)->post($base . '/models/gemini-2.5-flash:generateContent?key=' . $key, ['contents' => [['parts' => [
                    ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode($bytes)]],
                    ['text' => 'این پیامِ صوتیِ فارسی را دقیق و کامل به متنِ فارسی بنویس. فقط متن را بنویس، بدونِ توضیح.'],
                ]]]]);
                $text = trim((string) ($res->json('candidates.0.content.parts.0.text') ?? ''));
                if ($res->ok() && $text !== '') {
                    return ['ok' => true, 'text' => $text];
                }
            } catch (\Throwable $e) {
                // ادامه
            }
        }

        return ['ok' => false, 'error' => $tried
            ? 'تبدیلِ صدا به متن انجام نشد؛ دوباره و واضح‌تر بفرست یا بنویس.'
            : 'برای پیامِ صوتی، ادمین باید یک سرویسِ «صدا به متن» (یا کلیدِ OpenAI/Gemini) را در صفحه‌ی بات تنظیم کند.'];
    }

    /** @return array<int,array{0:string,1:string,2:array<int,string>}> */
    private static function openaiTargets(): array
    {
        $out = [];
        $base = trim((string) BotConfig::get('stt_base', ''));
        $key = trim((string) BotConfig::get('stt_key', ''));
        if ($base !== '' && $key !== '') {
            $out[] = [$base, $key, array_filter([trim((string) BotConfig::get('stt_model', '')) ?: 'whisper-1'])];
        }
        $p = AiConfig::provider();
        if ($p !== 'off' && AiConfig::family($p) === 'openai' && in_array($p, ['openai', 'custom'], true) && ($k = AiConfig::key($p)) && ($b = AiConfig::base($p))) {
            $out[] = [$b, $k, ['gpt-4o-mini-transcribe', 'whisper-1']];
        }
        if ($p !== 'openai' && ($k = AiConfig::key('openai'))) {
            $out[] = [AiConfig::base('openai') ?: 'https://api.openai.com/v1', $k, ['gpt-4o-mini-transcribe', 'whisper-1']];
        }

        return $out;
    }

    public static function available(): bool
    {
        return self::$fake !== null || self::openaiTargets() !== [] || (bool) AiConfig::key('gemini');
    }
}
