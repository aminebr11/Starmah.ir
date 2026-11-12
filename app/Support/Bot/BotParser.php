<?php

namespace App\Support\Bot;

/**
 * فهمیدنِ دستورهای فارسیِ معلم بدونِ هوش مصنوعی (سریع، رایگان، قابلِ پیش‌بینی).
 * اگر چیزی فهمیده نشد، TeacherBot سراغِ هوش مصنوعی می‌رود و خروجیِ آن هم دوباره از همین‌جا
 * (تطبیقِ نام با فهرستِ کلاس) می‌گذرد؛ پس هیچ نامِ ساختگی‌ای ثبت نمی‌شود.
 */
class BotParser
{
    public const RATINGS = ['خیلی خوب', 'قابل قبول', 'نیاز به تلاش', 'خوب'];
    public const HOMEWORK = ['انجام نداده', 'کامل', 'ناقص'];
    public const LESSONS = ['ریاضی', 'علوم', 'فارسی', 'املا', 'نگارش', 'مطالعات اجتماعی', 'مطالعات', 'هدیه‌های آسمانی', 'هدیه', 'قرآن', 'تفکر و پژوهش', 'هنر', 'ورزش', 'انگلیسی', 'عربی', 'کار و فناوری'];

    /** یکسان‌سازیِ نوشتار برای مقایسه (ی/ک عربی، نیم‌فاصله، اعراب، رقم‌ها). */
    public static function norm(string $s): string
    {
        $s = strtr($s, ['ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', 'ة' => 'ه', 'ۀ' => 'ه', 'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', "\u{200C}" => ' ', "\u{200F}" => '', "\u{200E}" => '']);
        $s = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $s) ?? $s;
        $s = self::digits($s);

        return trim(preg_replace('/\s+/u', ' ', mb_strtolower($s)) ?? $s);
    }

    public static function digits(string $s): string
    {
        return strtr($s, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.', '/' => '/']);
    }

    /** آیا «کلمه»ی $w (یک یا چند کلمه) در متن هست (نه تکه‌ای از کلمه‌ی دیگر)؟ */
    public static function has(string $text, string $w): bool
    {
        $w = self::norm($w);
        if ($w === '') {
            return false;
        }

        return (bool) preg_match('/(?<![\p{L}\p{N}])' . preg_quote($w, '/') . '(?![\p{L}\p{N}])/u', self::norm($text));
    }

    /**
     * دانش‌آموزانی که در این متن نام برده شده‌اند.
     * نامِ کامل اولویت دارد؛ نامِ کوچک یا نامِ خانوادگی فقط اگر یکتا باشد. نامِ مبهم جدا برمی‌گردد.
     *
     * @param  array<int,string>  $roster  [id => name]
     * @return array{ids:int[], ambiguous:array<string,string[]>}
     */
    public static function namesIn(string $text, array $roster): array
    {
        $ids = [];
        $amb = [];
        $t = self::norm($text);
        $used = $t;
        // ۱) نامِ کامل
        foreach ($roster as $id => $name) {
            if (self::has($used, $name)) {
                $ids[] = (int) $id;
                $used = preg_replace('/(?<![\p{L}\p{N}])' . preg_quote(self::norm($name), '/') . '(?![\p{L}\p{N}])/u', ' ', $used) ?? $used;
            }
        }
        // ۲) نامِ کوچک یا خانوادگی
        $parts = [];
        foreach ($roster as $id => $name) {
            if (in_array((int) $id, $ids, true)) {
                continue;
            }
            $w = explode(' ', self::norm($name));
            foreach (array_unique([$w[0] ?? '', count($w) > 1 ? end($w) : '']) as $p) {
                if (mb_strlen($p) >= 2) {
                    $parts[$p][] = (int) $id;
                }
            }
        }
        // نامِ کوچکِ تکراری بینِ همه‌ی کلاس (حتی آن‌که با نامِ کامل آمده) مبهم است
        $first = [];
        foreach ($roster as $id => $name) {
            $first[explode(' ', self::norm($name))[0] ?? ''][] = (int) $id;
        }
        foreach ($parts as $p => $owners) {
            if (! self::has($used, $p)) {
                continue;
            }
            $all = array_values(array_unique(array_merge($owners, $first[$p] ?? [])));
            $left = array_values(array_diff($all, $ids));
            if (count($all) === 1) {
                $ids[] = $all[0];
            } elseif (count($left) === 1 && count($owners) === 1 && count($first[$p] ?? []) <= 1) {
                $ids[] = $left[0];
            } elseif ($left) {
                $amb[$p] = array_map(fn ($i) => $roster[$i], $all);
            }
        }

        return ['ids' => array_values(array_unique($ids)), 'ambiguous' => $amb];
    }

    /** تکه‌های جمله (بند به بند). */
    public static function clauses(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[،,.;؛\n]+|(?<=\s)و(?=\s+(?:همه|بقیه))/u', $text) ?: [])));
    }

    /* ───────────── حضور و غیاب ───────────── */

    public static function looksAttendance(string $t): bool
    {
        return (bool) preg_match('/(حاضر|غایب|غیبت|تاخیر|تأخیر|دیر\s*(اومد|آمد|رسید)|موجه|حضور\s*و\s*غیاب)/u', self::norm($t) . ' ' . $t);
    }

    /**
     * «همه حاضر جز علی و سارا، رضا تأخیر داشت» → [studentId => status].
     * هر کس نامش نیامده «حاضر» است (در پیش‌نمایش گفته می‌شود).
     *
     * @return array{status:array<int,string>, ambiguous:array, found:int}
     */
    public static function attendance(string $text, array $roster): array
    {
        $status = array_fill_keys(array_map('intval', array_keys($roster)), 'present');
        $amb = [];
        $found = 0;
        foreach (self::clauses($text) as $c) {
            $n = self::norm($c);
            $st = preg_match('/(تاخیر|تأخیر|دیر)/u', $n . $c) ? 'late'
                : (preg_match('/موجه/u', $n) ? 'excused'
                    : (preg_match('/(غایب|غیبت|نیامد|نیومد|نبود|جز|بجز|غیر\s*از)/u', $n) ? 'absent' : null));
            if (! $st) {
                continue;
            }
            $r = self::namesIn($c, $roster);
            $amb += $r['ambiguous'];
            foreach ($r['ids'] as $id) {
                $status[$id] = $st;
                $found++;
            }
        }

        return ['status' => $status, 'ambiguous' => $amb, 'found' => $found];
    }

    /* ───────────── نمره ───────────── */

    public static function looksGrades(string $t): bool
    {
        $n = self::norm($t);
        if (preg_match('/(نمره|ارزشیابی|ارزیابی|بارم|دفتر\s*نمره)/u', $n)) {
            return true;
        }
        foreach (array_merge(self::RATINGS, self::HOMEWORK) as $r) {
            if (self::has($n, $r) && preg_match('/[:：]/u', $t)) {
                return true;
            }
        }

        return false;
    }

    /**
     * «ریاضی، کسرها، توصیفی: علی خیلی خوب، سارا خوب، رضا ۱۸، بقیه خوب»
     *
     * @return array{type:string,lesson:?string,topic:?string,title:string,max:float,grades:array<int,array>,ambiguous:array,rest:?string}
     */
    public static function grades(string $text, array $roster, array $lessons = []): array
    {
        $parts = preg_split('/[:：\n]/u', $text, 2) ?: [$text];
        $head = count($parts) > 1 ? $parts[0] : '';
        $body = count($parts) > 1 ? $parts[1] : $parts[0];

        $lesson = null;
        foreach (array_unique(array_merge($lessons, self::LESSONS)) as $l) {
            if ($l && self::has($text, $l)) {
                $lesson = $l;
                break;
            }
        }
        $topic = trim(preg_replace('/(نمره|نمرات|ثبت|ارزشیابی|ارزیابی|توصیفی|عددی|تکلیف|کلاسی|برای|ی|از\s*\d+|' . ($lesson ? preg_quote($lesson, '/') : 'ZZZ') . ')(?![\p{L}])/u', ' ', $head) ?? '');
        $topic = trim(preg_replace('/[\s،,\-–]+/u', ' ', $topic) ?? '') ?: null;
        $max = preg_match('/از\s*(\d+(?:\.\d+)?)/u', self::digits($head), $mm) ? (float) $mm[1] : 20.0;

        $grades = [];
        $amb = [];
        $rest = null;
        $kinds = ['numeric' => 0, 'descriptive' => 0, 'homework' => 0];
        foreach (preg_split('/[،,\n;؛]+/u', $body) ?: [] as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            $val = self::value($item);
            if (! $val) {
                continue;
            }
            $kinds[$val['kind']]++;
            if (preg_match('/(بقیه|سایرین|باقی|همه)/u', $item)) {
                $rest = $val;
                continue;
            }
            $r = self::namesIn($item, $roster);
            $amb += $r['ambiguous'];
            foreach ($r['ids'] as $id) {
                $grades[$id] = $val;
            }
        }
        if ($rest) {
            foreach ($roster as $id => $_) {
                $grades[(int) $id] ??= $rest;
            }
        }
        $type = preg_match('/تکلیف/u', $head) ? 'homework' : (preg_match('/توصیفی/u', $head) ? 'descriptive' : (preg_match('/عددی/u', $head) ? 'numeric' : array_search(max($kinds), $kinds, true)));
        // نمره‌های ناسازگار با نوع کنار می‌روند (در پیش‌نمایش دیده می‌شود)
        $grades = array_filter($grades, fn ($v) => $v['kind'] === $type);

        return [
            'type' => $type ?: 'descriptive', 'lesson' => $lesson, 'topic' => $topic, 'max' => $max,
            'title' => $type === 'homework' ? 'تکلیف' : 'ارزیابی کلاسی',
            'grades' => $grades, 'ambiguous' => $amb, 'rest' => $rest['value'] ?? null,
        ];
    }

    /** مقدارِ نمره در یک تکه: «خیلی خوب» / «۱۸» / «کامل». */
    public static function value(string $item): ?array
    {
        $n = self::norm($item);
        foreach (self::HOMEWORK as $h) {
            if (self::has($n, $h)) {
                return ['kind' => 'homework', 'value' => $h];
            }
        }
        foreach (self::RATINGS as $r) {
            if (self::has($n, $r)) {
                return ['kind' => 'descriptive', 'value' => $r];
            }
        }
        if (preg_match('/(?<![\p{L}\d.])(\d{1,3}(?:\.\d{1,2})?)(?![\d.])/u', $n, $m)) {
            return ['kind' => 'numeric', 'value' => (float) $m[1]];
        }

        return null;
    }

    /* ───────────── اطلاعیه ───────────── */

    /** «اطلاعیه: …» / «به کلاس بگو …» / «به علی بگو …» → ['to' => 'class'|name, 'text' => …] */
    public static function announce(string $t): ?array
    {
        $t = trim($t);
        if (preg_match('/^(?:📢\s*)?(?:اطلاعیه|اعلان|پیام)\s*(?:به\s*کلاس|برای\s*کلاس)?\s*[:：]\s*(.+)$/su', $t, $m)) {
            return ['to' => 'class', 'text' => trim($m[1])];
        }
        if (preg_match('/^به\s+(?:بچه‌?\s?ها|کلاس|همه)\s+(?:بگو|اطلاع\s*بده|خبر\s*بده)\s*[:：]?\s*(.+)$/su', $t, $m)) {
            return ['to' => 'class', 'text' => trim($m[1])];
        }
        if (preg_match('/^به\s+(.{2,40}?)\s+(?:بگو|پیام\s*بده|اطلاع\s*بده)\s*[:：]?\s*(.+)$/su', $t, $m)) {
            return ['to' => trim($m[1]), 'text' => trim($m[2])];
        }

        return null;
    }
}
