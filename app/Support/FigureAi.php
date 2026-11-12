<?php

namespace App\Support;

/** 🤖 پیشنهادِ شکل برای یک سؤال (آزمون‌ساز، استودیوی بازی، بات). */
class FigureAi
{
    /** @return array{ok:bool, spec?:array, message?:string} */
    public static function suggest(string $prompt, array $choices = [], string $answer = '', ?string $grade = null, ?string $subject = null, string $hint = ''): array
    {
        $sys = "تو طراحِ شکلِ سؤال‌های دبستان هستی. برای سؤالِ زیر بهترین شکل را انتخاب کن و فقط یک شیءِ JSON از یکی از قالب‌های زیر برگردان (بدونِ هیچ متنِ دیگر):\n"
            . Figure::guide()
            . "\nقواعد: عددهای شکل دقیقاً با سؤال جور باشد؛ پاسخ را روی شکل ننویس (به‌جایش «؟»). برچسب‌ها فارسی و کوتاه. اگر هیچ شکلی به فهمِ سؤال کمک نمی‌کند، فقط بنویس {\"type\":\"none\"}.";
        $user = 'پایه: ' . ($grade ?: '—') . ' · درس: ' . ($subject ?: '—') . "\nسؤال: " . $prompt
            . ($choices ? "\nگزینه‌ها: " . implode(' | ', array_filter($choices)) : '')
            . ($answer !== '' ? "\nپاسخِ درست: " . $answer : '')
            . ($hint !== '' ? "\nتوضیحِ معلم دربارهٔ شکل: " . $hint : '');
        AiUsage::$feature = 'figure';
        $r = AiChat::send($sys, $user, 500, null, 40);
        if (! $r['ok']) {
            return ['ok' => false, 'message' => $r['error'] ?: 'هوش مصنوعی پاسخ نداد.'];
        }
        $spec = preg_match('/\{.*\}/su', $r['text'], $m) ? json_decode($m[0], true) : null;
        if (is_array($spec) && ($spec['type'] ?? '') === 'none') {
            return ['ok' => false, 'message' => 'هوش مصنوعی برای این سؤال شکلِ مناسبی پیدا نکرد؛ اگر لازم است خودتان رسم کنید یا عکس بگذارید.'];
        }
        $spec = Figure::normalize($spec);

        return $spec ? ['ok' => true, 'spec' => $spec]
            : ['ok' => false, 'message' => 'شکلِ پیشنهادیِ هوش مصنوعی کامل نبود؛ دوباره امتحان کنید یا خودتان رسم کنید.'];
    }
}
