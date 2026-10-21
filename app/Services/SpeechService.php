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

    /** مکث (ثانیه) بعد از هر نقطه، بر حسبِ سرعتِ انتخابیِ معلم. */
    public const PAUSES = [
        'dictation' => ['short' => [2.0, 0.45], 'normal' => [3.0, 0.7], 'long' => [4.0, 1.0]],   // پایه + هر کلمه
        'reading' => ['short' => [0.7, 0], 'normal' => [1.2, 0], 'long' => [2.0, 0]],
    ];

    /**
     * «یک صدای پیوسته» از کلِ متن: متن تا آخر خوانده می‌شود و سرِ هر نقطه (. ! ؟) چند ثانیه مکث می‌شود
     * (املا: به اندازه‌ی طولِ جمله، تا بچه‌ها بنویسند). خروجی یک فایلِ WAV روی دیسکِ public است.
     * بدونِ ffmpeg: صدای هر جمله به‌صورتِ PCM گرفته و با سکوت پشتِ هم چسبانده می‌شود.
     *
     * @param  string[]  $sentences
     */
    public function continuousPath(array $sentences, string $style = 'reading', string $pace = 'normal'): ?string
    {
        $engine = self::engine();
        $sentences = array_values(array_filter(array_map(fn ($x) => self::clean((string) $x), $sentences)));
        if (! $sentences || ! $engine || ! self::available()) {
            return null;
        }
        $style = isset(self::PAUSES[$style]) ? $style : 'reading';
        [$base, $perWord] = self::PAUSES[$style][$pace] ?? self::PAUSES[$style]['normal'];
        $hash = sha1('cont|' . $engine . '|' . $style . '|' . $pace . '|' . implode("\n", $sentences));
        $path = 'tts/' . substr($hash, 0, 2) . '/' . $hash . '.wav';
        $disk = Storage::disk('public');
        if ($disk->exists($path)) {
            return $path;
        }
        $rate = 24000;
        $pcm = str_repeat("\0", (int) ($rate * 0.4) * 2); // کمی سکوت در آغاز
        foreach ($sentences as $i => $sentence) {
            $part = $this->pcmFor($sentence, $style);
            if ($part === null) {
                return null;
            }
            $pcm .= $part;
            if ($i < count($sentences) - 1) {
                $words = count(preg_split('/\s+/u', $sentence) ?: []);
                $gap = min(12, $base + $perWord * $words);
                $pcm .= str_repeat("\0", (int) ($rate * $gap) * 2);
            }
        }
        $pcm .= str_repeat("\0", (int) ($rate * 0.6) * 2);
        $disk->put($path, self::wav($pcm, $rate));

        return $path;
    }

    /** آخرین دلیلِ شکست (برای پیامِ روشن به معلم). */
    public static ?string $lastError = null;

    private function pcmPath(string $text, string $style): string
    {
        $hash = sha1('pcm|' . self::engine() . '|' . $style . '|' . $text);

        return 'tts/pcm/' . substr($hash, 0, 2) . '/' . $hash . '.pcm';
    }

    /** صدای این جمله از قبل ساخته شده است؟ (بی‌هزینه) */
    public function hasPcm(string $text, string $style): bool
    {
        $text = self::clean($text);

        return $text !== '' && self::engine() !== null && Storage::disk('public')->exists($this->pcmPath($text, $style));
    }

    /** پاک‌کردنِ صدای ساخته‌شده‌ی یک جمله (برای «ساختِ دوباره»). */
    public function forgetPcm(string $text, string $style): void
    {
        $text = self::clean($text);
        if ($text !== '' && self::engine()) {
            Storage::disk('public')->delete($this->pcmPath($text, $style));
        }
    }

    /**
     * ساختِ صدای یک جمله با تلاشِ دوباره — برای ساختِ قدم‌به‌قدمِ املا (هر درخواست چند جمله).
     * null یعنی نشد؛ دلیلش در SpeechService::$lastError است.
     */
    public function sentencePcm(string $text, string $style, int $tries = 3): ?string
    {
        $text = self::clean($text);
        if ($text === '' || ! self::available()) {
            self::$lastError = 'سرویسِ صدا (کلیدِ OpenAI یا Gemini) تنظیم نشده یا خاموش است.';

            return null;
        }
        for ($k = 1; $k <= $tries; $k++) {
            $pcm = $this->pcmFor($text, $style);
            if ($pcm !== null) {
                return $pcm;
            }
            // کلیدِ نادرست یا تمام‌شدنِ اعتبار با تلاشِ دوباره درست نمی‌شود
            if (self::$lastError && preg_match('/(کلید|اعتبار|دسترسی)/u', self::$lastError)) {
                break;
            }
            if ($k < $tries) {
                usleep(900000 * $k);
            }
        }

        return null;
    }

    /** PCMِ خامِ ۲۴ کیلوهرتزِ یک جمله (با کش؛ ساختِ دوباره‌ی یک متن هزینه ندارد). */
    private function pcmFor(string $text, string $style): ?string
    {
        $engine = self::engine();
        $path = $this->pcmPath($text, $style);
        $disk = Storage::disk('public');
        if ($disk->exists($path)) {
            return $disk->get($path);
        }
        $prev = AiUsage::$feature;
        AiUsage::$feature = 'tts';
        try {
            $pcm = $engine === 'gemini' ? $this->geminiPcm($text, $style) : $this->openai($text, $style, 'pcm');
        } finally {
            AiUsage::$feature = $prev;
        }
        if (! $pcm) {
            return null;
        }
        if (strlen($pcm) % 2) {
            $pcm .= "\0";
        }
        $disk->put($path, $pcm);

        return $pcm;
    }

    /** درخواستِ HTTP بی‌استثنا: قطعیِ اتصال یا مهلت → null و دلیلِ روشن. */
    private function send(callable $fn): ?\Illuminate\Http\Client\Response
    {
        try {
            $res = $fn();
        } catch (\Throwable $e) {
            self::$lastError = 'ارتباط با سرویسِ هوش مصنوعی برقرار نشد یا خیلی طول کشید (' . class_basename($e) . ').';
            \Illuminate\Support\Facades\Log::info('tts connection failed: ' . $e->getMessage());

            return null;
        }
        if (! $res->successful()) {
            self::$lastError = match (true) {
                in_array($res->status(), [401, 403], true) => 'کلیدِ هوش مصنوعی نامعتبر است یا دسترسیِ صدا ندارد (' . $res->status() . ').',
                $res->status() === 429 => 'اعتبار یا سهمیه‌ی سرویسِ هوش مصنوعی تمام شده یا درخواست‌ها زیاد است (429).',
                $res->status() >= 500 => 'سرویسِ هوش مصنوعی موقتاً پاسخ نداد (' . $res->status() . ').',
                default => 'سرویسِ هوش مصنوعی خطا داد (' . $res->status() . ').',
            };
            \Illuminate\Support\Facades\Log::info('tts http ' . $res->status() . ': ' . mb_substr((string) $res->body(), 0, 300));
        }

        return $res;
    }

    private function openai(string $text, string $style = 'kid', string $format = 'mp3'): ?string
    {
        $key = AiConfig::key('openai');
        $body = ['model' => 'gpt-4o-mini-tts', 'voice' => 'nova', 'input' => $text, 'response_format' => $format,
            'instructions' => self::STYLES[$style] ?? self::STYLES['kid']];
        $http = fn () => Http::withToken($key)->connectTimeout(10)->timeout(40);
        $res = $this->send(fn () => $http()->post('https://api.openai.com/v1/audio/speech', $body));
        if ($res && ($res->status() === 400 || $res->status() === 404)) {
            // حساب‌هایی که به مدلِ تازه دسترسی ندارند
            unset($body['instructions']);
            // مدلِ قدیمی دستورِ لحن نمی‌فهمد؛ برای املا آهسته‌تر پخش شود
            $res = $this->send(fn () => $http()->post('https://api.openai.com/v1/audio/speech',
                ['model' => 'tts-1'] + $body + ($style === 'dictation' ? ['speed' => 0.8] : [])));
        }
        if ($res && $res->successful() && strlen($res->body()) <= 200) {
            self::$lastError = 'سرویسِ هوش مصنوعی صدای خالی برگرداند.';
        }

        return $res && $res->successful() && strlen($res->body()) > 200 ? $res->body() : null;
    }

    private function gemini(string $text, string $style = 'kid'): ?string
    {
        $pcm = $this->geminiPcm($text, $style);

        return $pcm ? self::wav($pcm, 24000) : null;
    }

    private function geminiPcm(string $text, string $style = 'kid'): ?string
    {
        $key = AiConfig::key('gemini');
        // Gemini دستورِ لحن را از خودِ متن می‌گیرد؛ برای «kid» فقط متن (مثلِ قبل)
        $prompt = $style === 'kid' ? $text : (self::STYLES[$style] . "\n\n" . $text);
        $payload = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => ['voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => 'Kore']]],
            ],
        ];
        // اگر یک مدلِ صدا در دسترس نبود (نامِ تازه/قدیمی)، مدلِ دیگر امتحان شود
        foreach (['gemini-2.5-flash-preview-tts', 'gemini-2.5-pro-preview-tts'] as $model) {
            $res = $this->send(fn () => Http::connectTimeout(10)->timeout(45)
                ->post('https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . urlencode((string) $key), $payload));
            if (! $res || ! in_array($res->status(), [400, 404], true)) {
                break;
            }
        }
        $b64 = $res && $res->successful() ? data_get($res->json(), 'candidates.0.content.parts.0.inlineData.data') : null;
        $pcm = $b64 ? base64_decode($b64, true) : null;
        if ($res && $res->successful() && ! $pcm) {
            self::$lastError = 'سرویسِ هوش مصنوعی صدایی برنگرداند (شاید متن خیلی کوتاه یا نامفهوم است).';
        }

        return $pcm ?: null;
    }

    /** PCMِ ۱۶ بیتیِ تک‌کاناله → WAV. */
    public static function wav(string $pcm, int $rate): string
    {
        $len = strlen($pcm);

        return 'RIFF' . pack('V', 36 + $len) . 'WAVE' . 'fmt ' . pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16)
            . 'data' . pack('V', $len) . $pcm;
    }
}
