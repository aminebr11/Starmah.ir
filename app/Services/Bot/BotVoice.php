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
    /**
     * @param  string  $hint  واژه‌هایی که احتمالاً در پیام می‌آیند (نامِ بچه‌های کلاس، درس‌ها) — دقتِ نام‌ها را خیلی بالا می‌برد
     */
    public static function fromMessage(string $platform, array $voice, string $hint = ''): array
    {
        // تلگرام مدت را به ثانیه می‌دهد و «بله» به میلی‌ثانیه (پیامِ ۵ ثانیه‌ای = 5000)
        $secs = (float) ($voice['duration'] ?? 0);
        if ($platform === 'bale' || $secs > 3600) {
            $secs /= 1000;
        }
        // سقفِ سرویس‌های «صدا به متن» حدودِ ۲۵ مگابایت است
        if ($secs > 20 * 60 || ($voice['file_size'] ?? 0) > 24 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'پیامِ صوتی خیلی طولانی است؛ حداکثر ۲۰ دقیقه.'];
        }
        if (self::$fake !== null) {
            return ['ok' => true, 'text' => self::$fake];
        }
        $d = \App\Support\Bot\BotFile::download($platform, (string) ($voice['file_id'] ?? ''), 24 * 1024 * 1024);
        if (! $d['ok']) {
            return ['ok' => false, 'error' => 'فایلِ صوتی گرفته نشد: ' . $d['error']];
        }

        return self::transcribe($d['bytes'], self::sniff($d['bytes'], (string) ($voice['mime_type'] ?? 'audio/ogg')), $hint);
    }

    /** قالبِ واقعیِ صدا از روی بایت‌های اول (نامِ فایل و mime پیام‌رسان‌ها همیشه درست نیست). */
    public static function sniff(string $b, string $fallback = 'audio/ogg'): string
    {
        return match (true) {
            str_starts_with($b, 'OggS') => 'audio/ogg',
            str_starts_with($b, 'ID3') || (strlen($b) > 1 && ord($b[0]) === 0xFF && (ord($b[1]) & 0xE0) === 0xE0) => 'audio/mpeg',
            substr($b, 4, 4) === 'ftyp' => 'audio/mp4',
            str_starts_with($b, 'RIFF') && substr($b, 8, 4) === 'WAVE' => 'audio/wav',
            str_starts_with($b, '#!AMR') => 'audio/amr',
            str_starts_with($b, "\x1A\x45\xDF\xA3") => 'audio/webm',
            str_starts_with($b, 'fLaC') => 'audio/flac',
            default => $fallback ?: 'audio/ogg',
        };
    }

    /** واژه‌های رایجِ کارِ معلم (برای راهنماییِ مدلِ صدا به متن). */
    public const VOCAB = 'حضور و غیاب، غایب، حاضر، تأخیر، موجه، نمره، ارزشیابی، خیلی خوب، خوب، قابل قبول، نیاز به تلاش، تکلیف، روخوانی، املا، کاربرگ، امتیاز، تذکر، تشویق، اطلاعیه، آزمون، بازی، مسابقه‌ی زنده، کلاس، ریاضی، علوم، فارسی، نگارش، مطالعات اجتماعی، هدیه‌های آسمانی، قرآن';

    public static function transcribe(string $bytes, string $mime = 'audio/ogg', string $hint = ''): array
    {
        // راهنمای مدل: «گفتگوی معلم» + نامِ بچه‌ها و درس‌ها (مدل‌های صدا به متن از این متن برای املای نام‌ها کمک می‌گیرند)
        $prompt = mb_substr(trim('پیامِ صوتیِ یک معلمِ دبستان به دستیارش، به فارسی. ' . ($hint !== '' ? 'نام‌ها: ' . $hint . '. ' : '') . 'واژه‌ها: ' . self::VOCAB . '.'), 0, 900);
        $ext = match (true) {
            str_contains($mime, 'mpeg') || str_contains($mime, 'mp3') => 'mp3',
            str_contains($mime, 'mp4') || str_contains($mime, 'm4a') || str_contains($mime, 'aac') => 'm4a',
            str_contains($mime, 'wav') => 'wav',
            str_contains($mime, 'webm') => 'webm',
            str_contains($mime, 'flac') => 'flac',
            str_contains($mime, 'amr') => 'amr',
            default => 'ogg',
        };
        $tried = [];
        $silent = false;

        // ۱) و ۲) و ۳): سرویس‌های سازگار با OpenAI
        foreach (self::openaiTargets() as [$base, $key, $models]) {
            foreach ($models as $model) {
                $tried[] = $model;
                try {
                    $res = Http::timeout(60)->withToken($key)->attach('file', $bytes, 'voice.' . $ext)
                        ->post(rtrim($base, '/') . '/audio/transcriptions', ['model' => $model, 'language' => 'fa', 'response_format' => 'json',
                            'temperature' => '0', 'prompt' => $prompt]);
                    $text = self::clean((string) ($res->json('text') ?? ''), $prompt);
                    if ($res->ok() && $text !== '') {
                        return ['ok' => true, 'text' => $text, 'model' => $model];
                    }
                    if ($res->ok()) {
                        $silent = true;   // مدل جواب داد ولی متنی نبود؛ مدلِ بعدی را هم امتحان کن
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
                    ['text' => "این پیامِ صوتیِ فارسیِ یک معلمِ دبستان را کلمه‌به‌کلمه و دقیق به متنِ فارسی بنویس. چیزی اضافه یا کم نکن و ترجمه نکن. فقط خودِ متن را بنویس.\n"
                        . ($hint !== '' ? 'اگر نامی شنیدی، با املای همین فهرست بنویس: ' . $hint . "\n" : '') . 'اگر صدایی نبود، فقط بنویس: [خالی]'],
                ]]], 'generationConfig' => ['temperature' => 0]]);
                $text = self::clean((string) ($res->json('candidates.0.content.parts.0.text') ?? ''), $prompt);
                if ($res->ok() && $text !== '' && ! str_contains($text, '[خالی]')) {
                    return ['ok' => true, 'text' => $text, 'model' => 'gemini'];
                }
            } catch (\Throwable $e) {
                // ادامه
            }
        }

        return ['ok' => false, 'error' => $silent
            ? 'صدایی شنیده نشد؛ نزدیک‌تر به گوشی و واضح‌تر بگو (یا بنویس).'
            : ($tried ? 'تبدیلِ صدا به متن انجام نشد؛ دوباره و واضح‌تر بفرست یا بنویس.' : null)
            ?? 'برای پیامِ صوتی، ادمین باید یک سرویسِ «صدا به متن» (یا کلیدِ OpenAI/Gemini) را در صفحه‌ی بات تنظیم کند.'];
    }

    /**
     * پاک‌سازیِ خروجی: حروفِ عربی، و «توهم‌»های شناخته‌شده‌ی مدل‌ها روی صدای خالی
     * («زیرنویس توسط…»، تکرارِ همان متنِ راهنما).
     */
    public static function clean(string $text, string $prompt = ''): string
    {
        $t = trim(strtr($text, ['ي' => 'ی', 'ك' => 'ک', 'ى' => 'ی']));
        $t = trim(preg_replace('/\s+/u', ' ', $t) ?? $t);
        if ($t === '' || preg_match('/(زیرنویس|ترجمه و زیرنویس|amara\.org|subtitles? by|thanks for watching|ممنون که تماشا|سابتایتل)/iu', $t)) {
            return '';
        }
        // مدل وقتی صدا را نمی‌فهمد گاهی همان متنِ راهنما را برمی‌گرداند
        if ($prompt !== '' && (str_contains($prompt, $t) || str_contains($t, 'پیامِ صوتیِ یک معلمِ دبستان') || str_starts_with($t, 'واژه‌ها:') || str_starts_with($t, 'نام‌ها:'))) {
            return '';
        }

        return $t;
    }

    /** @return array<int,array{0:string,1:string,2:array<int,string>}> */
    private static function openaiTargets(): array
    {
        $out = [];
        $base = trim((string) BotConfig::get('stt_base', ''));
        $key = trim((string) BotConfig::get('stt_key', ''));
        if ($base !== '' && $key !== '') {
            $m = trim((string) BotConfig::get('stt_model', ''));
            $out[] = [$base, $key, $m !== '' ? [$m] : ['gpt-4o-transcribe', 'whisper-1']];
        }
        $p = AiConfig::provider();
        if ($p !== 'off' && AiConfig::family($p) === 'openai' && in_array($p, ['openai', 'custom'], true) && ($k = AiConfig::key($p)) && ($b = AiConfig::base($p))) {
            $out[] = [$b, $k, ['gpt-4o-transcribe', 'gpt-4o-mini-transcribe', 'whisper-1']];
        }
        if ($p !== 'openai' && ($k = AiConfig::key('openai'))) {
            $out[] = [AiConfig::base('openai') ?: 'https://api.openai.com/v1', $k, ['gpt-4o-transcribe', 'gpt-4o-mini-transcribe', 'whisper-1']];
        }

        return $out;
    }

    /** کدام سرویس صدا را به متن می‌کند (برای صفحه‌ی ادمین). */
    public static function describe(): ?string
    {
        $t = self::openaiTargets();
        if ($t) {
            $host = parse_url($t[0][0], PHP_URL_HOST) ?: $t[0][0];

            return $t[0][2][0] . ' — ' . $host . (count($t) > 1 || AiConfig::key('gemini') ? ' (و جایگزین)' : '');
        }

        return AiConfig::key('gemini') ? 'Gemini 2.5 Flash' : null;
    }

    public static function available(): bool
    {
        return self::$fake !== null || self::openaiTargets() !== [] || (bool) AiConfig::key('gemini');
    }
}
