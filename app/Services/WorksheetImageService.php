<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * تصویرسازِ کاربرگ.
 *
 * ── مشکلی که حل می‌کند ────────────────────────────────────────────────
 * نسخه‌ی پیشین فقط OpenAI را می‌شناخت و پیش‌فرضش «خاموش» بود. بنابراین:
 *   • مدرسه‌ای که کلیدِ Claude داشت (پیش‌فرضِ پلتفرم) هیچ‌وقت تصویر نمی‌گرفت،
 *     چون Anthropic اصلاً API تصویرسازی ندارد؛
 *   • اگر ادمین گزینه را روشن نمی‌کرد، تیکِ «تولید تصویر» حتی نمایش داده
 *     نمی‌شد و معلم فکر می‌کرد کاربرگ فقط «تمِ آفلاین» دارد؛
 *   • هر خطا بی‌صدا به null تبدیل می‌شد و معلم هیچ‌وقت نمی‌فهمید چرا.
 *
 * ── حالا ──────────────────────────────────────────────────────────────
 *   • سه موتور: OpenAI Images، Google Gemini (تصویرساز)، و موتورِ محلیِ
 *     برداری (WorksheetArtService) که همیشه و بدونِ کلید کار می‌کند؛
 *   • حالتِ پیش‌فرض «auto» است: هر کلیدی که هست استفاده می‌شود، و اگر
 *     هیچ کلیدی نبود به موتورِ محلی برمی‌گردد — پس کاربرگ همیشه تصویر دارد؛
 *   • هر نتیجه با «چرا» برمی‌گردد تا در صفحه به معلم گفته شود.
 *
 * تنظیماتِ ادمین کل:
 *   ws_image_provider : auto | openai | gemini | local | off     (پیش‌فرض auto)
 *   ws_image_model    : مدلِ OpenAI (gpt-image-1 | dall-e-3)
 *   gemini_image_model: مدلِ Gemini (پیش‌فرض gemini-2.5-flash-image)
 *   openai_image_key  : کلیدِ اختصاصیِ تصویر (نبود → openai_key)
 *   gemini_key        : کلیدِ Google AI Studio
 */
class WorksheetImageService
{
    public const PROVIDERS = ['auto', 'openai', 'gemini', 'local', 'off'];

    private const LABELS = [
        'openai' => 'OpenAI (DALL·E / GPT Image)',
        'gemini' => 'Google Gemini',
        'local'  => 'تصویرسازِ محلیِ ستاره ماه',
        'off'    => 'خاموش',
    ];

    public function __construct(private WorksheetArtService $art)
    {
    }

    /* ═══════════════════════ کلیدها و موتورِ مؤثر ═══════════════════════ */

    private function openAiKey(): ?string
    {
        return Setting::get('openai_image_key') ?: Setting::get('openai_key') ?: (env('OPENAI_API_KEY') ?: null);
    }

    private function geminiKey(): ?string
    {
        return Setting::get('gemini_key') ?: (env('GEMINI_API_KEY') ?: null);
    }

    /** تنظیمِ خامِ ادمین. */
    public function configured(): string
    {
        $v = (string) Setting::get('ws_image_provider', 'auto');

        return in_array($v, self::PROVIDERS, true) ? $v : 'auto';
    }

    /**
     * موتوری که واقعاً اجرا خواهد شد.
     * auto → اولین کلیدِ موجود؛ اگر کلیدی نبود، موتورِ محلی.
     */
    public function resolved(): string
    {
        $c = $this->configured();
        if ($c === 'off') {
            return 'off';
        }
        if ($c === 'openai') {
            return $this->openAiKey() ? 'openai' : 'local';
        }
        if ($c === 'gemini') {
            return $this->geminiKey() ? 'gemini' : 'local';
        }
        if ($c === 'local') {
            return 'local';
        }

        // auto
        return $this->openAiKey() ? 'openai' : ($this->geminiKey() ? 'gemini' : 'local');
    }

    /** آیا اصلاً می‌توان تصویری ساخت؟ (موتورِ محلی هم «بله» است) */
    public function enabled(): bool
    {
        return $this->resolved() !== 'off';
    }

    /** آیا موتورِ فعال واقعاً هوشِ مصنوعی است (نه محلی)؟ */
    public function isAi(): bool
    {
        return in_array($this->resolved(), ['openai', 'gemini'], true);
    }

    /**
     * وضعیت برای نمایش در صفحه‌ی معلم و ادمین.
     *
     * @return array{enabled:bool, ai:bool, provider:string, label:string, note:string}
     */
    public function status(): array
    {
        $r = $this->resolved();
        $note = match ($r) {
            'openai' => 'تصویر با هوش مصنوعیِ OpenAI ساخته می‌شود.',
            'gemini' => 'تصویر با هوش مصنوعیِ Google Gemini ساخته می‌شود.',
            'local'  => $this->configured() === 'local'
                ? 'ادمین موتورِ محلی را انتخاب کرده — تصویرِ برداریِ اختصاصی بدونِ نیاز به اینترنت ساخته می‌شود.'
                : 'کلیدِ تصویرسازِ هوش مصنوعی ثبت نشده، پس تصویرِ برداریِ اختصاصی با موتورِ محلی ساخته می‌شود. برای تصویرِ AI، ادمین کل باید کلیدِ OpenAI یا Gemini را در «تنظیمات پلتفرم» وارد کند.',
            default  => 'ادمین کل تصویرسازِ کاربرگ را خاموش کرده است.',
        };

        return [
            'enabled'  => $r !== 'off',
            'ai'       => in_array($r, ['openai', 'gemini'], true),
            'provider' => $r,
            'label'    => self::LABELS[$r] ?? $r,
            'note'     => $note,
        ];
    }

    /* ═══════════════════════════ تولید ═══════════════════════════ */

    /**
     * ساختِ تصویرِ کاربرگ.
     *
     * @return array{path:?string, provider:string, ai:bool, error:?string}
     */
    public function make(string $title, string $subject, string $theme, string $spec = '', string $themeKey = 'classic'): array
    {
        $engine = $this->resolved();
        if ($engine === 'off') {
            return $this->fail('off', 'تصویرسازِ کاربرگ توسط ادمین خاموش است.');
        }

        $prompt = $this->prompt($title, $subject, $theme, $spec);
        $error = null;

        if ($engine === 'openai') {
            [$path, $error] = $this->viaOpenAi($prompt);
            if ($path) {
                return ['path' => $path, 'provider' => 'openai', 'ai' => true, 'error' => null];
            }
        } elseif ($engine === 'gemini') {
            [$path, $error] = $this->viaGemini($prompt);
            if ($path) {
                return ['path' => $path, 'provider' => 'gemini', 'ai' => true, 'error' => null];
            }
        }

        // موتورِ محلی: هم انتخابِ مستقیم، هم تورِ ایمنیِ خطای AI
        $path = $this->art->generate($title, $subject, $themeKey, $spec);
        if (! $path) {
            return $this->fail('local', 'ساختِ تصویر ناموفق بود.' . ($error ? ' ' . $error : ''));
        }

        return [
            'path' => $path, 'provider' => 'local', 'ai' => false,
            'error' => $error ? 'هوش مصنوعی پاسخ نداد (' . $error . ') — تصویرِ محلی جایگزین شد.' : null,
        ];
    }

    /**
     * سازگاری با کدِ قدیمی: فقط مسیر را برمی‌گرداند.
     *
     * @deprecated از make() استفاده کنید تا دلیلِ خطا هم در دست باشد.
     */
    public function generate(string $title, string $subject, string $theme, string $spec = ''): ?string
    {
        return $this->make($title, $subject, $theme, $spec)['path'];
    }

    /** آزمایشِ سریعِ اتصال برای ادمین. */
    public function test(): array
    {
        $r = $this->make('آزمایشِ تصویرساز', 'ریاضی', 'ستاره ماه 🌙', 'یک ستاره‌ی ساده', 'stars');
        if ($r['path']) {
            Storage::disk('public')->delete($r['path']);
        }

        return [
            'ok' => (bool) $r['path'],
            'provider' => $r['provider'],
            'ai' => $r['ai'],
            'error' => $r['error'],
            'label' => self::LABELS[$r['provider']] ?? $r['provider'],
        ];
    }

    /* ═══════════════════════════ موتورها ═══════════════════════════ */

    private function prompt(string $title, string $subject, string $theme, string $spec): string
    {
        return trim(
            "A cheerful, child-friendly educational illustration to decorate a printable A4 worksheet. "
            . "Subject: {$subject}. Worksheet topic: {$title}. Visual theme: {$theme}. "
            . ($spec ? "Extra details: {$spec}. " : '')
            . 'Flat vector style, bright friendly colours, generous white space, no text, no letters, no numbers, '
            . 'no human faces, suitable for printing on white paper.'
        );
    }

    /** @return array{0:?string,1:?string} [path, error] */
    private function viaOpenAi(string $prompt): array
    {
        $key = $this->openAiKey();
        if (! $key) {
            return [null, 'کلیدِ OpenAI ثبت نشده.'];
        }
        $model = Setting::get('ws_image_model', 'gpt-image-1') ?: 'gpt-image-1';

        try {
            $res = Http::withToken($key)->timeout(90)->post('https://api.openai.com/v1/images/generations', [
                'model'  => $model,
                'prompt' => $prompt,
                'size'   => '1024x1024',
                'n'      => 1,
            ]);
            if ($res->failed()) {
                return [null, $this->httpError($res->status(), (string) data_get($res->json(), 'error.message', ''))];
            }
            $bytes = null;
            if ($b64 = data_get($res->json(), 'data.0.b64_json')) {
                $bytes = base64_decode($b64);
            } elseif ($url = data_get($res->json(), 'data.0.url')) {
                $img = Http::timeout(60)->get($url);
                $bytes = $img->successful() ? $img->body() : null;
            }

            return $bytes ? [$this->store($bytes, 'png'), null] : [null, 'پاسخِ OpenAI تصویری نداشت.'];
        } catch (\Throwable $e) {
            Log::warning('worksheet image (openai): ' . $e->getMessage());

            return [null, 'خطای شبکه در ارتباط با OpenAI.'];
        }
    }

    /** @return array{0:?string,1:?string} [path, error] */
    private function viaGemini(string $prompt): array
    {
        $key = $this->geminiKey();
        if (! $key) {
            return [null, 'کلیدِ Gemini ثبت نشده.'];
        }
        $model = Setting::get('gemini_image_model', 'gemini-2.5-flash-image') ?: 'gemini-2.5-flash-image';

        try {
            $res = Http::timeout(90)
                ->withHeaders(['x-goog-api-key' => $key])
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['responseModalities' => ['IMAGE']],
                ]);
            if ($res->failed()) {
                return [null, $this->httpError($res->status(), (string) data_get($res->json(), 'error.message', ''))];
            }

            foreach ((array) data_get($res->json(), 'candidates.0.content.parts', []) as $part) {
                $b64 = data_get($part, 'inlineData.data') ?? data_get($part, 'inline_data.data');
                if ($b64) {
                    $mime = (string) (data_get($part, 'inlineData.mimeType') ?? data_get($part, 'inline_data.mime_type') ?? 'image/png');
                    $ext = str_contains($mime, 'jpeg') ? 'jpg' : (str_contains($mime, 'webp') ? 'webp' : 'png');

                    return [$this->store(base64_decode($b64), $ext), null];
                }
            }

            return [null, 'پاسخِ Gemini تصویری نداشت.'];
        } catch (\Throwable $e) {
            Log::warning('worksheet image (gemini): ' . $e->getMessage());

            return [null, 'خطای شبکه در ارتباط با Gemini.'];
        }
    }

    private function httpError(int $status, string $msg): string
    {
        $head = match (true) {
            $status === 401 || $status === 403 => 'کلیدِ API پذیرفته نشد',
            $status === 429 => 'سقفِ درخواستِ سرویس پر شده',
            $status >= 500 => 'سرویسِ تصویرساز در دسترس نیست',
            default => 'خطای ' . $status,
        };

        return $head . ($msg ? ' — ' . mb_substr($msg, 0, 140) : '');
    }

    private function store(string $bytes, string $ext): ?string
    {
        if ($bytes === '') {
            return null;
        }
        $path = 'worksheet-images/ws_' . substr(sha1($bytes . microtime(true)), 0, 16) . '.' . $ext;
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    private function fail(string $provider, string $error): array
    {
        return ['path' => null, 'provider' => $provider, 'ai' => false, 'error' => $error];
    }
}
