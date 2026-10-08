<?php

namespace App\Support;

use App\Models\Setting;

/**
 * پیکربندیِ مرکزیِ هوش مصنوعی — کدام سرویس فعال است، کلید، مدل و نشانی.
 *
 * همه‌ی بخش‌ها (دستیار، طراحِ سؤال، اطلاعیه‌نویس…) از همین‌جا می‌خوانند.
 * سرویس‌ها دو «خانواده» دارند: anthropic (پیامِ Claude) و openai (قالبِ
 * chat/completions که DeepSeek، Gemini، OpenRouter و سرویس‌های ایرانیِ
 * سازگار هم از آن پیروی می‌کنند). پس افزودنِ سرویسِ تازه فقط یک ردیف است.
 */
class AiConfig
{
    public const PROVIDERS = [
        'anthropic' => [
            'label' => 'Claude', 'vendor' => 'Anthropic', 'family' => 'anthropic', 'emoji' => '🟠',
            'base' => 'https://api.anthropic.com/v1', 'key' => 'anthropic_key', 'model' => 'anthropic_model',
            'default' => 'claude-haiku-4-5-20251001',
            'models' => ['claude-haiku-4-5-20251001', 'claude-sonnet-5-5', 'claude-opus-5-5'],
            'hint' => 'کیفیتِ بسیار بالا در فارسی و طراحیِ سؤال.', 'site' => 'console.anthropic.com',
        ],
        'openai' => [
            'label' => 'ChatGPT', 'vendor' => 'OpenAI', 'family' => 'openai', 'emoji' => '🟢',
            'base' => 'https://api.openai.com/v1', 'key' => 'openai_key', 'model' => 'openai_model',
            'default' => 'gpt-4o-mini',
            'models' => ['gpt-5-mini', 'gpt-5', 'gpt-5-nano', 'gpt-4.1-mini', 'gpt-4o-mini', 'gpt-4o', 'o4-mini'],
            'hint' => 'سریع و همه‌کاره.', 'site' => 'platform.openai.com',
        ],
        'deepseek' => [
            'label' => 'DeepSeek', 'vendor' => 'DeepSeek', 'family' => 'openai', 'emoji' => '🔵',
            'base' => 'https://api.deepseek.com/v1', 'key' => 'ai_key_deepseek', 'model' => 'ai_model_deepseek',
            'default' => 'deepseek-chat',
            'models' => ['deepseek-chat', 'deepseek-reasoner'],
            'hint' => 'بسیار ارزان؛ مناسبِ حجمِ زیاد.', 'site' => 'platform.deepseek.com',
        ],
        'gemini' => [
            'label' => 'Gemini', 'vendor' => 'Google', 'family' => 'openai', 'emoji' => '🔷',
            'base' => 'https://generativelanguage.googleapis.com/v1beta/openai', 'key' => 'gemini_key', 'model' => 'ai_model_gemini',
            'default' => 'gemini-2.5-flash',
            'models' => ['gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-2.0-flash'],
            'hint' => 'سهمیه‌ی رایگانِ خوب؛ همان کلیدِ تصویرسازِ Gemini.', 'site' => 'aistudio.google.com',
        ],
        'openrouter' => [
            'label' => 'OpenRouter', 'vendor' => 'OpenRouter', 'family' => 'openai', 'emoji' => '🟣',
            'base' => 'https://openrouter.ai/api/v1', 'key' => 'ai_key_openrouter', 'model' => 'ai_model_openrouter',
            'default' => 'deepseek/deepseek-chat',
            'models' => ['deepseek/deepseek-chat', 'qwen/qwen-2.5-72b-instruct', 'meta-llama/llama-3.3-70b-instruct', 'mistralai/mistral-large'],
            'hint' => 'یک کلید برای ده‌ها مدل (DeepSeek، Qwen، Llama، Mistral…).', 'site' => 'openrouter.ai',
        ],
        'custom' => [
            'label' => 'سرویسِ سازگار', 'vendor' => 'OpenAI-compatible', 'family' => 'openai', 'emoji' => '⚙️',
            'base' => null, 'key' => 'ai_key_custom', 'model' => 'ai_model_custom',
            'default' => 'gpt-4o-mini',
            'models' => [],
            'hint' => 'هر سرویسی با قالبِ OpenAI؛ مثلاً واسط‌های ایرانی (AvalAI، GapGPT و…) یا سرورِ خودتان.', 'site' => null,
        ],
    ];

    /** سرویسِ فعال (یا off). */
    public static function provider(): string
    {
        $p = (string) Setting::get('ai_provider', 'anthropic');

        return $p === 'off' || isset(self::PROVIDERS[$p]) ? $p : 'anthropic';
    }

    public static function meta(?string $p = null): ?array
    {
        return self::PROVIDERS[$p ?? self::provider()] ?? null;
    }

    /** خانواده‌ی سرویسِ فعال: anthropic | openai | off */
    public static function family(?string $p = null): string
    {
        $p ??= self::provider();

        return $p === 'off' ? 'off' : (self::PROVIDERS[$p]['family'] ?? 'anthropic');
    }

    public static function key(?string $p = null): ?string
    {
        $p ??= self::provider();
        $m = self::PROVIDERS[$p] ?? null;
        if (! $m) return null;
        $env = ['anthropic' => 'ANTHROPIC_API_KEY', 'openai' => 'OPENAI_API_KEY', 'deepseek' => 'DEEPSEEK_API_KEY', 'gemini' => 'GEMINI_API_KEY'][$p] ?? null;

        return Setting::get($m['key']) ?: ($env ? env($env) : null);
    }

    public static function model(?string $p = null): string
    {
        $p ??= self::provider();
        $m = self::PROVIDERS[$p] ?? self::PROVIDERS['anthropic'];

        return (string) (Setting::get($m['model']) ?: ($p === 'anthropic' ? env('ANTHROPIC_MODEL', $m['default']) : $m['default']));
    }

    public static function base(?string $p = null): ?string
    {
        $p ??= self::provider();
        $b = $p === 'custom' ? Setting::get('ai_base_custom') : (self::PROVIDERS[$p]['base'] ?? null);

        return $b ? rtrim($b, '/') : null;
    }

    /** نشانیِ chat/completions برای خانواده‌ی openai. */
    public static function chatUrl(?string $p = null): string
    {
        return (self::base($p) ?: self::PROVIDERS['openai']['base']) . '/chat/completions';
    }

    public static function configured(?string $p = null): bool
    {
        $p ??= self::provider();

        return $p !== 'off' && (bool) self::key($p) && ($p !== 'custom' || self::base($p));
    }

    /**
     * آماده‌کردنِ بدنه‌ی chat/completions برای سرویسِ مقصد.
     *
     * مدل‌های تازه‌ی OpenAI (خانواده‌ی GPT‑5 و سری o) پارامترِ max_tokens را
     * نمی‌پذیرند و max_completion_tokens می‌خواهند؛ دما (temperature) را هم جز
     * مقدارِ پیش‌فرض قبول نمی‌کنند. خودِ OpenAI برای همه‌ی مدل‌هایش
     * max_completion_tokens را می‌پذیرد، پس برای OpenAI همیشه همان فرستاده می‌شود.
     * سرویس‌های سازگارِ دیگر (DeepSeek و…) همان max_tokens را می‌خواهند.
     */
    public static function adaptChatBody(array $body, ?string $p = null): array
    {
        $p ??= self::provider();
        if ($p === 'openai') {
            if (isset($body['max_tokens'])) {
                // مدل‌های استدلالی بخشی از سقف را صرفِ «فکرکردن» می‌کنند؛ کمی جا اضافه می‌کنیم
                $max = (int) $body['max_tokens'];
                $body['max_completion_tokens'] = self::isReasoningModel($body['model'] ?? '') ? max($max * 4, 1500) : $max;
                unset($body['max_tokens']);
            }
            if (self::isReasoningModel($body['model'] ?? '')) {
                unset($body['temperature'], $body['top_p']);
                // بدونِ این، مدل با «تلاشِ متوسط» فکر می‌کند و طراحیِ چند سؤال از
                // سقفِ زمانِ PHP روی هاست (معمولاً ۳۰ ثانیه) بیشتر می‌شود
                $body['reasoning_effort'] ??= 'low';
            }
        }

        return $body;
    }

    /** GPT‑5، o1، o3، o4… — مدل‌هایی که دما ندارند و «توکنِ استدلال» مصرف می‌کنند. */
    public static function isReasoningModel(string $model): bool
    {
        return (bool) preg_match('/^(gpt-5|o\d)/i', trim($model));
    }

    /**
     * فرستادنِ درخواستِ chat/completions با پیکربندیِ سرویس و یک بار تلاشِ دوباره
     * اگر مدل پارامتری را نپذیرفت (max_tokens / max_completion_tokens / temperature).
     */
    public static function postChat(string $key, array $body, int $timeout = 40, ?string $p = null): \Illuminate\Http\Client\Response
    {
        $p ??= self::provider();
        $body = self::adaptChatBody($body, $p);
        $send = function ($b) use ($key, $timeout, $p) {
            $req = \Illuminate\Support\Facades\Http::withToken($key)->timeout($timeout);
            if ($p === 'openrouter') {
                $req = $req->withHeaders(['HTTP-Referer' => config('app.url'), 'X-Title' => 'Starmah']);
            }

            return $req->post(self::chatUrl($p), $b);
        };
        $res = $send($body);
        if ($res->status() === 400) {
            $err = strtolower((string) data_get($res->json(), 'error.message', $res->body()));
            $fixed = $body;
            if (str_contains($err, 'max_completion_tokens') && isset($fixed['max_tokens'])) {
                $fixed['max_completion_tokens'] = $fixed['max_tokens']; unset($fixed['max_tokens']);
            } elseif (str_contains($err, 'max_tokens') && isset($fixed['max_completion_tokens'])) {
                $fixed['max_tokens'] = $fixed['max_completion_tokens']; unset($fixed['max_completion_tokens']);
            }
            if (str_contains($err, 'temperature')) unset($fixed['temperature']);
            if (str_contains($err, 'reasoning_effort') || str_contains($err, 'reasoning effort')) unset($fixed['reasoning_effort']);
            if (str_contains($err, 'response_format') || str_contains($err, 'json_schema')) {
                $fixed['response_format'] = ['type' => 'json_object'];
            }
            if (str_contains($err, 'top_p')) unset($fixed['top_p']);
            if ($fixed !== $body) $res = $send($fixed);
        }

        return $res;
    }

    /**
     * چند ثانیه برای فراخوانیِ هوش مصنوعی وقت داریم؟ سقفِ واقعیِ PHP روی هاست
     * (max_execution_time) را در نظر می‌گیرد؛ set_time_limit روی بسیاری از هاست‌ها
     * بسته است و اگر از سقف بگذریم، PHP بی‌صدا قطع می‌شود و مرورگر پاسخی نمی‌گیرد.
     */
    public static function timeBudget(int $wanted = 100): int
    {
        @set_time_limit($wanted + 20);
        $max = (int) ini_get('max_execution_time');

        return $max > 0 ? max(10, min($wanted, $max - 6)) : $wanted;
    }

    /** آیا این نشانی متعلق به یکی از سرویس‌های متنیِ هوش مصنوعی است؟ (برای ثبتِ مصرف) */
    public static function providerForUrl(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        foreach (self::PROVIDERS as $k => $m) {
            $b = $k === 'custom' ? Setting::get('ai_base_custom') : $m['base'];
            if ($b && $host && $host === parse_url($b, PHP_URL_HOST)) return $k;
        }

        return null;
    }
}
