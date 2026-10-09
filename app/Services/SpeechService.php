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

    /** سبکِ خواندن: «kid» برای «بخوان برایم»، «dictation» برای املا (آهسته و کلمه‌به‌کلمه)، «reading» برای روخوانیِ الگو. */
    public const STYLES = [
        'kid' => 'به زبانِ فارسیِ معیار، آرام، شمرده و مهربان برای یک کودکِ دبستانی بخوان.',
        'dictation' => 'این یک جمله‌ی املا برای دانش‌آموزِ دبستانی است. به فارسیِ معیار، خیلی آهسته، واضح و کلمه‌به‌کلمه بخوان و بینِ کلمه‌ها کمی مکث کن.',
        'reading' => 'به فارسیِ معیار، مثلِ یک معلمِ خوب، روان و با لحنِ درست و رعایتِ نشانه‌ها برای دانش‌آموزِ دبستانی بخوان.',
    ];

    /** نشانیِ فایلِ صوتیِ متن (اگر نبود ساخته می‌شود)؛ null یعنی صدا در دسترس نیست. */
    public function urlFor(string $text, string $style = 'kid'): ?string
    {
        $path = $this->pathFor($text, $style);

        return $path ? Storage::disk('public')->url($path) : null;
    }

    /** مسیرِ فایلِ صوتی روی دیسکِ public (ساخته و ذخیره می‌شود). */
    public function pathFor(string $text, string $style = 'kid'): ?string
    {
        $text = self::clean($text);
        $engine = self::engine();
        if ($text === '' || ! $engine || ! self::available()) {
            return null;
        }
        $style = isset(self::STYLES[$style]) ? $style : 'kid';
        $ext = $engine === 'gemini' ? 'wav' : 'mp3';
        $hash = sha1($engine . '|' . ($style === 'kid' ? '' : $style . '|') . $text);
        $path = 'tts/' . substr($hash, 0, 2) . '/' . $hash . '.' . $ext;
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            $prev = AiUsage::$feature;
            AiUsage::$feature = 'tts';
            try {
                $audio = $engine === 'gemini' ? $this->gemini($text, $style) : $this->openai($text, $style);
            } finally {
                AiUsage::$feature = $prev;
            }
            if (! $audio) {
                return null;
            }
            $disk->put($path, $audio);
        }

        return $path;
    }

    private function openai(string $text, string $style = 'kid'): ?string
    {
        $key = AiConfig::key('openai');
        $body = ['model' => 'gpt-4o-mini-tts', 'voice' => 'nova', 'input' => $text, 'response_format' => 'mp3',
            'instructions' => self::STYLES[$style] ?? self::STYLES['kid']];
        $res = Http::withToken($key)->timeout(30)->post('https://api.openai.com/v1/audio/speech', $body);
        if ($res->status() === 400 || $res->status() === 404) {
            // حساب‌هایی که به مدلِ تازه دسترسی ندارند
            unset($body['instructions']);
            // مدلِ قدیمی دستورِ لحن نمی‌فهمد؛ برای املا آهسته‌تر پخش شود
            $res = Http::withToken($key)->timeout(30)->post('https://api.openai.com/v1/audio/speech',
                ['model' => 'tts-1'] + $body + ($style === 'dictation' ? ['speed' => 0.8] : []));
        }

        return $res->successful() && strlen($res->body()) > 200 ? $res->body() : null;
    }

    private function gemini(string $text, string $style = 'kid'): ?string
    {
        $key = AiConfig::key('gemini');
        // Gemini دستورِ لحن را از خودِ متن می‌گیرد؛ برای «kid» فقط متن (مثلِ قبل)
        $prompt = $style === 'kid' ? $text : (self::STYLES[$style] . "\n\n" . $text);
        $res = Http::timeout(30)->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-tts:generateContent?key=' . urlencode((string) $key), [
            'contents' => [['parts' => [['text' => $prompt]]]],
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
