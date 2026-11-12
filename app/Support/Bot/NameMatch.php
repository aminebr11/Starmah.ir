<?php

namespace App\Support\Bot;

/**
 * 🔤 تطبیقِ نامِ «شنیده‌شده» با فهرستِ کلاس — مقاوم در برابرِ خطای صدا به متن و تایپ.
 *
 * تبدیلِ صدا به متن حروفِ هم‌صدا را جابه‌جا می‌نویسد («ظهرا» به‌جای «زهرا»، «سنا» به‌جای «ثنا»،
 * «محمّد/ممد») و گاهی یک حرف را جا می‌اندازد. این‌جا هر دو طرف به «شکلِ آوایی» برده می‌شوند و
 * نزدیک‌ترین نام (کامل، کوچک یا خانوادگی) با فاصله‌ی ویرایشی پیدا می‌شود. فقط وقتی پذیرفته می‌شود
 * که شباهت بالا و بی‌رقیب باشد؛ وگرنه «مبهم» برمی‌گردد تا بات بپرسد.
 */
class NameMatch
{
    /** شکلِ آوایی: حروفِ هم‌صدا یکی، اعراب و فاصله حذف. */
    public static function fold(string $s): string
    {
        $s = BotParser::norm($s);
        $s = strtr($s, [
            'ث' => 'س', 'ص' => 'س', 'ذ' => 'ز', 'ض' => 'ز', 'ظ' => 'ز', 'ط' => 'ت', 'ح' => 'ه', 'غ' => 'ق',
            'ع' => 'ا', 'ء' => '', 'ئ' => 'ی', 'ؤ' => 'و', 'ة' => 'ه', 'ي' => 'ی', 'ك' => 'ک',
        ]);

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $s) ?? $s;
    }

    /** شباهتِ ۰ تا ۱ (بر پایه‌ی فاصله‌ی ویرایشیِ حرف‌به‌حرف). */
    public static function similarity(string $a, string $b): float
    {
        $a = self::fold($a);
        $b = self::fold($b);
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 1.0;
        }
        $ca = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY);
        $cb = preg_split('//u', $b, -1, PREG_SPLIT_NO_EMPTY);
        // هر حرفِ یکتا → یک بایت، تا levenshteinِ خودِ PHP درست کار کند
        $map = [];
        $enc = function (array $chars) use (&$map) {
            $o = '';
            foreach ($chars as $c) {
                $map[$c] ??= chr(33 + count($map) % 200);
                $o .= $map[$c];
            }

            return $o;
        };
        $d = levenshtein($enc($ca), $enc($cb));

        return max(0.0, 1 - $d / max(count($ca), count($cb)));
    }

    /**
     * نزدیک‌ترین دانش‌آموز به نامِ شنیده‌شده.
     *
     * @param  array<int,string>  $roster
     * @return array{id:?int, score:float, ambiguous:array<int,string>}
     */
    public static function best(string $heard, array $roster): array
    {
        $heard = trim($heard);
        $words = count(preg_split('/\s+/u', BotParser::norm($heard)) ?: []);
        $scores = [];
        foreach ($roster as $id => $name) {
            $parts = preg_split('/\s+/u', BotParser::norm($name)) ?: [];
            $cands = [$name];
            if ($words === 1 && $parts) {
                // «سارا» با نامِ کوچک، «محمدی» با نامِ خانوادگی
                $cands[] = $parts[0];
                if (count($parts) > 1) {
                    $cands[] = end($parts);
                    $cands[] = implode(' ', array_slice($parts, 0, -1));   // نامِ کوچکِ دوتکه‌ای («محمد علی»)
                }
            }
            $scores[(int) $id] = max(array_map(fn ($c) => self::similarity($heard, $c), $cands));
        }
        arsort($scores);
        $ids = array_keys($scores);
        $top = $ids[0] ?? null;
        $s1 = $top !== null ? $scores[$top] : 0.0;
        $s2 = isset($ids[1]) ? $scores[$ids[1]] : 0.0;
        // نامِ کوتاه («علی»، «ولی») سخت‌گیریِ بیشتری می‌خواهد
        $min = mb_strlen(self::fold($heard)) <= 4 ? 0.8 : 0.72;
        if ($top === null || $s1 < $min) {
            return ['id' => null, 'score' => $s1, 'ambiguous' => []];
        }
        if ($s1 - $s2 < 0.08) {
            $close = array_filter($scores, fn ($v) => $s1 - $v < 0.08);

            return ['id' => null, 'score' => $s1, 'ambiguous' => array_map(fn ($id) => $roster[$id], array_keys(array_slice($close, 0, 4, true)))];
        }

        return ['id' => $top, 'score' => $s1, 'ambiguous' => []];
    }
}
