<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\AiConfig;
use App\Support\AiUsage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * «بخوان برایم» با صدای فارسی، وقتی دستگاه خودش صدای فارسی ندارد (بیشترِ گوشی‌ها، به‌ویژه آیفون).
 *
 * متن یک‌بار با سرویسِ تبدیلِ متن به گفتار (OpenAI یا Gemini — هر کدام کلید داشت) خوانده و
 * به‌صورتِ فایلِ صوتی در storage ذخیره می‌شود؛ دفعه‌های بعد همان فایل پخش می‌شود (بی‌هزینه).
 */
class SpeechService
{
    public const MAX = 700;

    /** آیا سرویسِ صدا در دسترس است؟ (ادمین می‌تواند با tts_enabled=0 خاموشش کند) */
    public static function available(): bool
    {
        return (bool) rescue(fn () => Setting::get('tts_enabled', '1') !== '0' && self::engine() !== null, false, false);
    }

    /** موتورِ صدا: OpenAI (کیفیتِ بهتر برای فارسی) و بعد Gemini. */
    public static function engine(): ?string
    {
        if (AiConfig::key('openai')) {
            return 'openai';
        }
        if (AiConfig::key('gemini')) {
            return 'gemini';
        }

        return null;
    }

    public static function clean(string $text): string
    {
        $t = preg_replace('/[\p{Extended_Pictographic}\x{FE0F}\x{200D}]/u', '', $text) ?? $text;
        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;

        return mb_substr(trim($t), 0, self::MAX);
    }

    /** نشانیِ فایلِ صوتیِ متن (اگر نبود ساخته می‌شود)؛ null یعنی صدا در دسترس نیست. */
    public function urlFor(string $text): ?string
    {
        $text = self::clean($text);
        $engine = self::engine();
        if ($text === '' || ! $engine || ! self::available()) {
            return null;
        }
        $ext = $engine === 'gemini' ? 'wav' : 'mp3';
        $path = 'tts/' . substr(sha1($engine . '|' . $text), 0, 2) . '/' . sha1($engine . '|' . $text) . '.' . $ext;
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            $prev = AiUsage::$feature;
            AiUsage::$feature = 'tts';
            try {
                $audio = $engine === 'gemini' ? $this->gemini($text) : $this->openai($text);
            } finally {
                AiUsage::$feature = $prev;
            }
            if (! $audio) {
                return null;
            }
            $disk->put($path, $audio);
        }

        return $disk->url($path);
    }

    private function openai(string $text): ?string
    {
        $key = AiConfig::key('openai');
        $body = ['model' => 'gpt-4o-mini-tts', 'voice' => 'nova', 'input' => $text, 'response_format' => 'mp3',
            'instructions' => 'به زبانِ فارسیِ معیار، آرام، شمرده و مهربان برای یک کودکِ دبستانی بخوان.'];
        $res = Http::withToken($key)->timeout(30)->post('https://api.openai.com/v1/audio/speech', $body);
        if ($res->status() === 400 || $res->status() === 404) {
            // حساب‌هایی که به مدلِ تازه دسترسی ندارند
            unset($body['instructions']);
            $res = Http::withToken($key)->timeout(30)->post('https://api.openai.com/v1/audio/speech', ['model' => 'tts-1'] + $body);
        }

        return $res->successful() && strlen($res->body()) > 200 ? $res->body() : null;
    }

    private function gemini(string $text): ?string
    {
        $key = AiConfig::key('gemini');
        $res = Http::timeout(30)->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-tts:generateContent?key=' . urlencode((string) $key), [
            'contents' => [['parts' => [['text' => $text]]]],
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => ['voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => 'Kore']]],
            ],
        ]);
        $b64 = $res->successful() ? data_get($res->json(), 'candidates.0.content.parts.0.inlineData.data') : null;
        $pcm = $b64 ? base64_decode($b64, true) : null;

        return $pcm ? self::wav($pcm, 24000) : null;
    }

    /** PCMِ ۱۶ بیتیِ تک‌کاناله → WAV. */
    public static function wav(string $pcm, int $rate): string
    {
        $len = strlen($pcm);

        return 'RIFF' . pack('V', 36 + $len) . 'WAVE' . 'fmt ' . pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16)
            . 'data' . pack('V', $len) . $pcm;
    }
}
