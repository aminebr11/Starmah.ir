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
            'models' => ['gpt-4o-mini', 'gpt-4.1-mini', 'gpt-4o', 'gpt-4.1'],
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
