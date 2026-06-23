<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Theme;
use App\Models\User;

/**
 * موتور تم — هسته‌ی تمایز محصول.
 *
 * مسئولیت‌ها:
 *  - تشخیص تم فعال کاربر (با fallback به تم پیش‌فرض)
 *  - تحویل لایه‌های skin/narrative به فرانت
 *  - «روکش‌زنی» سؤال خنثی با تم انتخابی کاربر (لایه ۳)
 */
class ThemeEngine
{
    /** تم فعال برای یک کاربر؛ اگر تمی انتخاب نکرده، اولین تم فعال */
    public function for(?User $user): ?Theme
    {
        $themes = $this->all();

        if ($user && $user->theme_id && isset($themes[$user->theme_id])) {
            return $themes[$user->theme_id];
        }

        return $themes->first();
    }

    /** همه‌ی تم‌های فعال، با کلید id (در یک request یک‌بار حل می‌شود) */
    public function all()
    {
        return once(fn () => Theme::where('is_active', true)->orderBy('sort')->get()->keyBy('id'));
    }

    /**
     * بسته‌ای که به فرانت‌اند (Inertia/PWA) داده می‌شود تا کل ظاهر و واژگان
     * را بر اساس تم بسازد. عمداً ساده و serializable است.
     */
    public function presentation(?Theme $theme): array
    {
        if (! $theme) {
            return ['key' => null, 'skin' => [], 'narrative' => []];
        }

        return [
            'id'        => $theme->id,
            'key'       => $theme->key,
            'name'      => $theme->name,
            'emoji'     => $theme->emoji,
            'header'    => $theme->header_image ? \Illuminate\Support\Facades\Storage::url($theme->header_image) : null,
            'skin'      => $theme->skin,
            'narrative' => $theme->narrative,
        ];
    }

    /**
     * یک سؤال خنثی را با تم کاربر «روکش» می‌زند و نمونه‌ی قابل‌نمایش می‌سازد.
     * محتوای آموزشی (عمل ریاضی) ثابت می‌ماند؛ فقط روایت عوض می‌شود.
     *
     * @return array{prompt:string, answer:int|float, choices:array, xp:int}
     */
    public function renderQuestion(Question $question, ?Theme $theme): array
    {
        $pools = $theme?->content_pools ?? [];

        // ۱) انتخاب مقدار برای متغیرهای عددی طبق محدوده
        $vars = [];
        foreach (($question->variables ?? []) as $name => $range) {
            if (is_array($range) && count($range) === 2 && is_numeric($range[0])) {
                $vars[$name] = random_int((int) $range[0], (int) $range[1]);
            }
        }

        // ۲) انتخاب اسم‌های تم‌دار برای جای‌خالی‌ها (team, unit, hero, ...)
        $nouns = [];
        foreach ($pools as $slot => $items) {
            if (is_array($items) && $items) {
                $nouns[$slot] = $items[array_rand($items)];
            }
        }

        // ۳) ساخت متن نهایی
        $replace = [];
        foreach (array_merge($vars, $nouns) as $k => $v) {
            $replace['{' . $k . '}'] = $v;
        }
        $prompt = strtr($question->template, $replace);

        // ۴) محاسبه‌ی پاسخ صحیح از روی فرمول ساده (n*k, n+k, ...)
        $answer = $this->evaluate($question->answer_expr, $vars);

        // ۵) ساخت گزینه‌ها برای سؤال چندگزینه‌ای
        $choices = $this->buildChoices($answer);

        return [
            'prompt'  => $prompt,
            'answer'  => $answer,
            'choices' => $choices,
            'xp'      => $question->xp,
        ];
    }

    /** ارزیاب امن برای عبارت‌های حسابیِ ساده با متغیرها (بدون eval خطرناک) */
    private function evaluate(?string $expr, array $vars): int|float
    {
        if (! $expr) {
            return 0;
        }

        $expr = strtr($expr, array_map('strval', $vars));

        // فقط ارقام، فاصله و عملگرهای پایه مجازند
        if (! preg_match('/^[0-9+\-*\/().\s]+$/', $expr)) {
            return 0;
        }

        try {
            return eval("return {$expr};"); // ورودی به‌شدت محدود شده در بالا
        } catch (\Throwable) {
            return 0;
        }
    }

    /** ساخت چهار گزینه: پاسخ درست + سه فریب نزدیک */
    private function buildChoices(int|float $answer): array
    {
        $set = [(int) $answer];
        $tries = 0;
        while (count($set) < 4 && $tries++ < 30) {
            $delta = random_int(1, 4) * (random_int(0, 1) ? 1 : -1);
            $cand = (int) $answer + $delta;
            if ($cand >= 0 && ! in_array($cand, $set, true)) {
                $set[] = $cand;
            }
        }
        shuffle($set);

        return array_map(fn ($v) => [
            'value'   => $v,
            'correct' => $v === (int) $answer,
        ], $set);
    }
}
