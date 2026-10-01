<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * تولید سؤال آزمون. اگر کلید ANTHROPIC_API_KEY تنظیم باشد از Claude استفاده می‌کند،
 * در غیر این صورت با یک fallback محلی (سؤال‌های پایه) کار می‌کند تا همیشه قابل استفاده باشد.
 */
class AiExamService
{
    /**
     * @return array<int, array{prompt:string, choices:array<int,array{value:string,correct:bool}>}>
     */
    public function generate(string $topic, int $count, string $grade = 'چهارم'): array
    {
        $count = max(1, min(20, $count));
        // سرویسِ فعالِ هوش مصنوعی (هر کدام که ادمین انتخاب کرده)
        if (\App\Support\AiConfig::configured()) {
            try {
                $out = $this->viaActive($topic, $count, $grade);
                if ($out) return $out;
            } catch (\Throwable $e) {
                // در صورت خطا به fallback می‌رویم
            }
        }

        return $this->fallback($topic, $count);
    }

    private function viaActive(string $topic, int $count, string $grade): array
    {
        $prompt = "تو یک معلم ابتدایی هستی. {$count} سؤال چهارگزینه‌ای ساده درباره‌ی موضوع «{$topic}» "
            . "برای دانش‌آموز پایه‌ی {$grade} بساز. فقط یک JSON معتبر برگردان به شکل آرایه‌ای از اشیاء با کلیدهای: "
            . "prompt (متن سؤال)، choices (آرایه‌ی ۴ شیء با کلیدهای value و correct که فقط یکی true است). بدون توضیح اضافه.";

        $r = \App\Support\AiChat::send('فقط JSON معتبر برگردان.', $prompt, 2000);
        if (! $r['ok']) return [];
        $text = $r['text'];
        preg_match('/\[.*\]/s', $text, $m);
        $data = json_decode($m[0] ?? $text, true);

        $out = [];
        foreach ((array) $data as $q) {
            if (! empty($q['prompt']) && ! empty($q['choices'])) {
                $out[] = [
                    'prompt' => (string) $q['prompt'],
                    'choices' => array_map(fn ($c) => [
                        'value' => (string) ($c['value'] ?? ''),
                        'correct' => (bool) ($c['correct'] ?? false),
                    ], array_slice($q['choices'], 0, 4)),
                ];
            }
        }

        return $out ?: $this->fallback($topic, $count);
    }

    /** تولید محلی: سؤال‌های ساده‌ی حسابی (همیشه در دسترس، بدون نیاز به اینترنت/کلید). */
    private function fallback(string $topic, int $count): array
    {
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $a = random_int(2, 12);
            $b = random_int(2, 12);
            $ops = [['+', $a + $b], ['-', $a + $b - $b /* keep positive */], ['×', $a * $b]];
            [$op, $ans] = $ops[array_rand($ops)];
            if ($op === '-') {
                $ans = $a; // a+b - b = a
                $prompt = "حاصل ({$a} + {$b}) − {$b} چند می‌شود؟";
            } else {
                $prompt = "حاصل {$a} {$op} {$b} چند می‌شود؟";
            }

            $choices = [$ans];
            while (count($choices) < 4) {
                $cand = $ans + random_int(-5, 5);
                if ($cand >= 0 && ! in_array($cand, $choices, true)) {
                    $choices[] = $cand;
                }
            }
            shuffle($choices);
            $out[] = [
                'prompt' => $prompt,
                'choices' => array_map(fn ($v) => ['value' => (string) $v, 'correct' => $v === $ans], $choices),
            ];
        }

        return $out;
    }
}
