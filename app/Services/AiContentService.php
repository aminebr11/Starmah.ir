<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

/**
 * تولید متن با هوش مصنوعی (اطلاعیه، پیام و…).
 * کلیدها از تنظیمات سراسری خوانده می‌شوند (ادمین کل وارد می‌کند)، با fallback به env.
 * پشتیبانی از Claude (Anthropic) و ChatGPT (OpenAI).
 */
class AiContentService
{
    public function provider(): string
    {
        return Setting::get('ai_provider', 'anthropic');
    }

    public function anthropicKey(): ?string
    {
        return Setting::get('anthropic_key') ?: env('ANTHROPIC_API_KEY');
    }

    public function openaiKey(): ?string
    {
        return Setting::get('openai_key') ?: env('OPENAI_API_KEY');
    }

    public function isConfigured(): bool
    {
        return (bool) ($this->provider() === 'openai' ? $this->openaiKey() : $this->anthropicKey());
    }

    /**
     * تولید متن. اگر کلید واقعی تنظیم باشد از هوش مصنوعی استفاده می‌کند؛
     * در غیر این صورت (یا در صورت خطای شبکه) یک «پیش‌نویس محلی» برمی‌گرداند تا دکمه همیشه کار کند.
     */
    public function generate(string $prompt, ?string $topic = null): string
    {
        $provider = $this->provider();
        try {
            if ($provider === 'openai' && $this->openaiKey()) {
                return $this->viaOpenAI($this->openaiKey(), $prompt);
            }
            if ($this->anthropicKey()) {
                return $this->viaClaude($this->anthropicKey(), $prompt);
            }
        } catch (\Throwable $e) {
            // در صورت خطا به پیش‌نویس محلی می‌رویم
        }
        return $this->localDraft($topic ?: $prompt);
    }

    /** پیش‌نویس محلیِ حرفه‌ای (بدون نیاز به کلید API) — چند قالبِ متفاوت. قابل ویرایش. */
    private function localDraft(string $topic): string
    {
        $topic = trim(rtrim($topic, '.۔!؟'));
        $templates = [
            "اولیای گرامی و دانش‌آموزان عزیز،\n\n"
                . "با سلام و احترام؛ به اطلاع می‌رساند که {$topic}.\n"
                . "خواهشمند است ترتیبی اتخاذ فرمایید تا از این برنامه بهره‌مند شوید. در صورت هرگونه پرسش، همکاران ما در دفتر مدرسه پاسخ‌گو هستند.\n\n"
                . "با آرزوی موفقیت،\nمدیریت مدرسه",
            "دانش‌آموزان عزیز و خانواده‌های محترم،\n\n"
                . "🔔 اطلاعیه‌ی مهم: {$topic}.\n\n"
                . "لطفاً این موضوع را در برنامه‌ی خود لحاظ کنید و در صورت نیاز به هماهنگی بیشتر، با مدرسه در تماس باشید.\n\n"
                . "با سپاس از همراهی شما،\nمدیریت مدرسه",
            "با نام خدا\n\nاولیای گرامی،\n\n"
                . "بدین‌وسیله به آگاهی می‌رساند که {$topic}. مشارکت و همراهی شما در این زمینه مایه‌ی دلگرمی ماست.\n\n"
                . "با احترام،\nمدیریت مدرسه",
        ];
        return $templates[array_rand($templates)];
    }

    private function systemPrompt(): string
    {
        return 'تو دستیار نگارش یک مدرسه هستی. یک اطلاعیه‌ی کوتاه، رسمی، صمیمی و روان به زبان فارسی بنویس. '
            . 'فقط متن اطلاعیه را برگردان، بدون عنوان جداگانه، بدون توضیح اضافه و بدون علامت نقل‌قول.';
    }

    private function viaClaude(string $key, string $prompt): string
    {
        $res = Http::withHeaders([
            'x-api-key' => $key,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(40)->post('https://api.anthropic.com/v1/messages', [
            'model' => Setting::get('anthropic_model') ?: env('ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001'),
            'max_tokens' => 700,
            'system' => $this->systemPrompt(),
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        if (! $res->successful()) {
            throw new \RuntimeException('خطا در ارتباط با Claude: ' . $res->status());
        }
        return trim((string) data_get($res->json(), 'content.0.text', ''));
    }

    private function viaOpenAI(string $key, string $prompt): string
    {
        $res = Http::withToken($key)->timeout(40)->post('https://api.openai.com/v1/chat/completions', [
            'model' => Setting::get('openai_model') ?: 'gpt-4o-mini',
            'max_tokens' => 700,
            'messages' => [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        if (! $res->successful()) {
            throw new \RuntimeException('خطا در ارتباط با ChatGPT: ' . $res->status());
        }
        return trim((string) data_get($res->json(), 'choices.0.message.content', ''));
    }
}
