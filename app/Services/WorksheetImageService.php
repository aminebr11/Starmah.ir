<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * تولیدِ تصویرِ کاربرگ با هوش مصنوعی (اختیاری). تنظیمات توسط ادمین کل:
 *  - ws_image_provider: off | openai
 *  - openai_image_key (یا همان openai_key)
 *  - ws_image_model: gpt-image-1 | dall-e-3
 * اگر تنظیم نباشد یا خطا رخ دهد، null برمی‌گرداند تا کاربرگِ HTML به‌جای آن استفاده شود.
 */
class WorksheetImageService
{
    public function enabled(): bool
    {
        return Setting::get('ws_image_provider', 'off') === 'openai'
            && (bool) (Setting::get('openai_image_key') ?: Setting::get('openai_key') ?: env('OPENAI_API_KEY'));
    }

    /**
     * تولیدِ یک تصویرِ تزئینیِ کاربرگ بر اساس درخواستِ معلم (درس + تم + توضیح).
     * @return string|null مسیرِ ذخیره‌شده روی دیسکِ public، یا null در صورت عدمِ امکان
     */
    public function generate(string $title, string $subject, string $theme, string $spec = ''): ?string
    {
        if (! $this->enabled()) {
            return null;
        }
        $key = Setting::get('openai_image_key') ?: Setting::get('openai_key') ?: env('OPENAI_API_KEY');
        $model = Setting::get('ws_image_model', 'gpt-image-1');

        $prompt = trim("یک تصویرِ زمینه‌ی آموزشیِ زیبا و کودکانه برای «کاربرگ» درسِ {$subject} با موضوعِ «{$title}» و حال‌وهوای «{$theme}». "
            . ($spec ? "جزئیات: {$spec}. " : '')
            . 'بدون متن و بدون حروف، فقط تصویرسازیِ تزئینیِ حاشیه‌ای و شاد و مناسب چاپ روی برگه‌ی A4، رنگ‌های روشن.');

        try {
            $res = Http::withToken($key)->timeout(90)->post('https://api.openai.com/v1/images/generations', [
                'model'  => $model,
                'prompt' => $prompt,
                'size'   => '1024x1024',
                'n'      => 1,
            ]);
            if ($res->failed()) {
                return null;
            }
            $b64 = data_get($res->json(), 'data.0.b64_json');
            $url = data_get($res->json(), 'data.0.url');
            $bytes = null;
            if ($b64) {
                $bytes = base64_decode($b64);
            } elseif ($url) {
                $img = Http::timeout(60)->get($url);
                $bytes = $img->successful() ? $img->body() : null;
            }
            if (! $bytes) {
                return null;
            }
            $path = 'worksheet-images/' . uniqid('ws_', true) . '.png';
            Storage::disk('public')->put($path, $bytes);

            return $path;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
