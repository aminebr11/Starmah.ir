<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\SmartExamAiRequest;
use Illuminate\Support\Facades\Http;

/**
 * دستیار هوشمند طراحی سؤال (آزمایشگاه هوشمند) — مستقل از AiExamService قدیمی.
 * از Provider تنظیم‌شده‌ی ادمین استفاده می‌کند (anthropic|openai|off).
 * خروجی با اسکیمای JSON اعتبارسنجی می‌شود؛ سؤالِ نامعتبر منتشر نمی‌شود.
 * fallback موضوع‌آگاه است (برای فارسی/علوم سؤال ریاضی تولید نمی‌کند).
 */
class SmartExamAiService
{
    /**
     * @return array{ok:bool, mode:string, questions:array, message:?string}
     */
    public function generate(array $opts): array
    {
        $count = max(1, min(30, (int) ($opts['count'] ?? 5)));
        $subject = trim($opts['subject'] ?? '');
        $topic = trim($opts['topic'] ?? ($opts['chapter'] ?? $subject));
        $grade = $opts['grade'] ?? 'چهارم';
        $difficulty = $opts['difficulty'] ?? 'medium';
        $type = $opts['type'] ?? 'mc';
        $flavor = trim($opts['flavor'] ?? '');
        $sampleMode = (bool) ($opts['sample'] ?? false);

        $provider = Setting::get('ai_provider', 'anthropic');
        $key = $provider === 'openai' ? Setting::get('openai_key') : Setting::get('anthropic_key');

        // حالت نمونه: صراحتاً برچسب می‌خورد و به‌جای AI واقعی نیست
        if ($sampleMode || $provider === 'off' || ! $key) {
            if (! $sampleMode) {
                return [
                    'ok' => false, 'mode' => 'unavailable', 'questions' => [],
                    'message' => $provider === 'off'
                        ? 'سرویس هوش مصنوعی توسط ادمین غیرفعال است.'
                        : 'کلید هوش مصنوعی تنظیم نشده — برای تولید نمونه‌ی آزمایشی، گزینه‌ی «حالت نمونه» را بزنید.',
                ];
            }
            return ['ok' => true, 'mode' => 'sample', 'questions' => $this->sample($subject, $topic, $count, $type, $flavor), 'message' => 'این‌ها سؤال‌های نمونه‌ی آزمایشی‌اند (نه تولید واقعیِ هوش مصنوعی).'];
        }

        $book = trim($opts['book'] ?? '');
        $chapter = trim($opts['chapter'] ?? '');
        $goal = trim($opts['goal'] ?? '');
        try {
            $ctx = compact('subject', 'topic', 'grade', 'count', 'difficulty', 'type', 'flavor', 'book', 'chapter', 'goal');
            $raw = $provider === 'openai' ? $this->viaOpenAi($key, $ctx) : $this->viaAnthropic($key, $ctx);
            $questions = $this->validate($raw, $type);
            $this->log($opts, $provider, $count, count($questions), true, null);
            if (! $questions) {
                return ['ok' => false, 'mode' => 'invalid', 'questions' => [], 'message' => 'خروجی هوش مصنوعی نامعتبر بود؛ دوباره تلاش کنید.'];
            }
            return ['ok' => true, 'mode' => 'ai', 'questions' => $questions, 'message' => null];
        } catch (\Throwable $e) {
            $this->log($opts, $provider, $count, 0, false, $e->getMessage());
            return ['ok' => false, 'mode' => 'error', 'questions' => [], 'message' => 'خطا در ارتباط با سرویس هوش مصنوعی: ' . $e->getMessage()];
        }
    }

    private function promptText(array $o): string
    {
        $typeFa = ['mc' => 'چهارگزینه‌ای', 'tf' => 'درست/نادرست', 'desc' => 'تشریحی', 'blank' => 'جای خالی'][$o['type']] ?? 'چهارگزینه‌ای';
        $diffFa = ['easy' => 'آسان', 'medium' => 'متوسط', 'hard' => 'دشوار'][$o['difficulty']] ?? 'متوسط';
        $ctx = '';
        if (! empty($o['book'])) {
            $ctx .= " از کتابِ «{$o['book']}»";
        }
        if (! empty($o['chapter'])) {
            $ctx .= " فصلِ «{$o['chapter']}»";
        }
        if (! empty($o['goal'])) {
            $ctx .= "؛ با هدفِ آموزشیِ «{$o['goal']}»";
        }
        $p = "تو یک معلمِ باتجربه‌ی ایرانی هستی. برای دانش‌آموزِ پایه‌ی «{$o['grade']}»، "
            . "دقیقاً درباره‌ی درسِ «{$o['subject']}» و موضوعِ «{$o['topic']}»{$ctx}، تعداد {$o['count']} سؤالِ {$typeFa} با سطحِ {$diffFa} بساز. "
            . "سؤال‌ها باید کاملاً مرتبط با همان درس و موضوع باشند (اگر درس فارسی یا علوم یا مطالعات است، سؤالِ ریاضی نساز). ";
        if (! empty($o['flavor'])) {
            $p .= "بافت و مثال‌های سؤال را از دنیای «{$o['flavor']}» بساز (مثلاً اگر فوتبال است، صحنه‌ها و شخصیت‌ها فوتبالی باشند)، "
                . "ولی مفهومِ درسی و پاسخِ صحیح دقیق و علمی بماند و از سطحِ پایه خارج نشود. ";
        }
        $p .= "فقط و فقط یک آرایه‌ی JSON معتبر برگردان؛ هر عضو با کلیدهای: "
            . "prompt (متن سؤال)، type ('{$o['type']}')، choices (برای mc/tf آرایه‌ای از اشیاء {value, correct})، "
            . "answer (برای desc/blank رشته‌ی پاسخِ نمونه)، explanation (توضیح آموزشی)، difficulty، goal (هدف آموزشی)، topic. "
            . "بدون هیچ متنِ اضافه بیرون از JSON.";
        return $p;
    }

    private function viaAnthropic(string $key, array $o): array
    {
        $model = Setting::get('anthropic_model', 'claude-haiku-4-5-20251001');
        $res = Http::withHeaders([
            'x-api-key' => $key, 'anthropic-version' => '2023-06-01', 'content-type' => 'application/json',
        ])->timeout(45)->post('https://api.anthropic.com/v1/messages', [
            'model' => $model, 'max_tokens' => 3000,
            'messages' => [['role' => 'user', 'content' => $this->promptText($o)]],
        ]);
        if ($res->failed()) {
            throw new \RuntimeException('HTTP ' . $res->status());
        }
        return $this->extractJson(data_get($res->json(), 'content.0.text', ''));
    }

    private function viaOpenAi(string $key, array $o): array
    {
        $model = Setting::get('openai_model', 'gpt-4o-mini');
        $res = Http::withToken($key)->timeout(45)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $model, 'temperature' => 0.5,
            'messages' => [
                ['role' => 'system', 'content' => 'You return only valid JSON arrays of exam questions in Persian.'],
                ['role' => 'user', 'content' => $this->promptText($o)],
            ],
        ]);
        if ($res->failed()) {
            throw new \RuntimeException('HTTP ' . $res->status());
        }
        return $this->extractJson(data_get($res->json(), 'choices.0.message.content', ''));
    }

    private function extractJson(string $text): array
    {
        if (preg_match('/\[.*\]/s', $text, $m)) {
            $text = $m[0];
        }
        $data = json_decode($text, true);
        return is_array($data) ? $data : [];
    }

    /** اعتبارسنجیِ اسکیما — سؤالِ ناقص کنار گذاشته می‌شود. */
    private function validate(array $raw, string $type): array
    {
        $out = [];
        foreach ($raw as $q) {
            if (empty($q['prompt']) || ! is_string($q['prompt'])) {
                continue;
            }
            $t = in_array(($q['type'] ?? $type), ['mc', 'tf', 'desc', 'blank'], true) ? $q['type'] : $type;
            $item = [
                'type' => $t,
                'prompt' => (string) $q['prompt'],
                'explanation' => isset($q['explanation']) ? (string) $q['explanation'] : null,
                'goal' => isset($q['goal']) ? (string) $q['goal'] : null,
                'difficulty' => in_array(($q['difficulty'] ?? 'medium'), ['easy', 'medium', 'hard'], true) ? $q['difficulty'] : 'medium',
                'topic' => isset($q['topic']) ? (string) $q['topic'] : null,
                'choices' => [], 'answer' => null,
            ];
            if ($t === 'mc' || $t === 'tf') {
                $choices = $t === 'tf'
                    ? [['value' => 'درست', 'correct' => false], ['value' => 'نادرست', 'correct' => false]]
                    : [];
                if ($t === 'mc') {
                    foreach (array_slice((array) ($q['choices'] ?? []), 0, 4) as $c) {
                        if (isset($c['value'])) {
                            $choices[] = ['value' => (string) $c['value'], 'correct' => (bool) ($c['correct'] ?? false)];
                        }
                    }
                    if (count($choices) < 2 || ! collect($choices)->contains('correct', true)) {
                        continue; // نامعتبر
                    }
                } else {
                    // tf: تعیین پاسخ درست از row
                    $ans = $q['answer'] ?? ($q['correct'] ?? null);
                    $isTrue = in_array($ans, [true, 'true', 'درست', 1, '1'], true);
                    $choices[0]['correct'] = $isTrue;
                    $choices[1]['correct'] = ! $isTrue;
                }
                $item['choices'] = $choices;
            } else {
                $item['answer'] = isset($q['answer']) ? (string) $q['answer'] : '';
            }
            $out[] = $item;
        }
        return $out;
    }

    private function log(array $opts, string $provider, int $requested, int $produced, bool $ok, ?string $err): void
    {
        try {
            SmartExamAiRequest::create([
                'school_id' => $opts['school_id'] ?? null,
                'teacher_id' => $opts['teacher_id'] ?? null,
                'provider' => $provider,
                'model' => $provider === 'openai' ? Setting::get('openai_model') : Setting::get('anthropic_model'),
                'subject' => $opts['subject'] ?? null,
                'requested' => $requested, 'produced' => $produced, 'ok' => $ok,
                'error' => $err ? mb_substr($err, 0, 240) : null,
            ]);
        } catch (\Throwable $e) {
            // ثبت آمار نباید مسیر اصلی را بشکند
        }
    }

    /** نمونه‌ی موضوع‌آگاه — بدون fallbackِ ریاضی برای دروسِ غیرریاضی. */
    private function sample(string $subject, string $topic, int $count, string $type, string $flavor = ''): array
    {
        $isMath = $this->looksMath($subject);
        $fx = $flavor ? " (در فضای «{$flavor}»)" : '';
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            if ($type === 'desc') {
                $out[] = ['type' => 'desc', 'prompt' => "درباره‌ی «{$topic}»{$fx} یک توضیح کوتاه بنویس. (سؤال نمونه)", 'answer' => 'پاسخ نمونه', 'explanation' => null, 'difficulty' => 'medium', 'goal' => null, 'topic' => $topic, 'choices' => []];
                continue;
            }
            if ($isMath) {
                $a = random_int(2, 12); $b = random_int(2, 12); $ans = $a * $b;
                $ch = [$ans, $ans + 1, $ans - 1, $ans + 2];
                shuffle($ch);
                $out[] = ['type' => 'mc', 'prompt' => "حاصل {$a} × {$b} چند می‌شود؟{$fx} (نمونه)", 'difficulty' => 'medium', 'goal' => null, 'topic' => $topic, 'explanation' => null,
                    'choices' => array_map(fn ($v) => ['value' => (string) $v, 'correct' => $v === $ans], $ch), 'answer' => null];
            } else {
                // نمونه‌ی متنیِ عمومیِ موضوع‌محور (نه ریاضی)
                $out[] = ['type' => 'mc', 'prompt' => "کدام گزینه درباره‌ی «{$topic}»{$fx} درست است؟ (سؤال نمونه — نیازمند ویرایش معلم)", 'difficulty' => 'medium', 'goal' => null, 'topic' => $topic, 'explanation' => null,
                    'choices' => [
                        ['value' => 'گزینه‌ی درست (ویرایش کنید)', 'correct' => true],
                        ['value' => 'گزینه‌ی نادرست ۱', 'correct' => false],
                        ['value' => 'گزینه‌ی نادرست ۲', 'correct' => false],
                        ['value' => 'گزینه‌ی نادرست ۳', 'correct' => false],
                    ], 'answer' => null];
            }
        }
        return $out;
    }

    private function looksMath(string $subject): bool
    {
        foreach (['ریاض', 'حساب', 'هندسه', 'math'] as $k) {
            if (mb_strpos($subject, $k) !== false) {
                return true;
            }
        }
        return false;
    }
}
