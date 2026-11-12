<?php

namespace App\Support;

/**
 * 📐 شکلِ سؤال از روی یک «توصیفِ کوتاه» (JSON) → تصویرِ SVGِ تمیز و راست‌به‌چپ.
 *
 * هوش مصنوعی (یا معلم در «رسمِ شکل») به‌جای کشیدنِ تصویر فقط می‌گوید چه شکلی لازم است؛ مثلاً
 *   {"type":"pizza","parts":8,"shaded":3}
 *   {"type":"bar_chart","labels":["شنبه","یکشنبه"],"values":[4,7],"title":"کتاب‌های خوانده‌شده"}
 * و این کلاس آن را دقیق می‌کشد. پس عددها همیشه با متنِ سؤال جورند، فارسی درست نمایش داده
 * می‌شود و هیچ سرویسِ تصویرسازی (که از ایران در دسترس نیست یا گران است) لازم نیست.
 *
 * خروجی فقط از عددها و متن‌های escape‌شده ساخته می‌شود؛ هیچ ورودیِ خامی واردِ SVG نمی‌شود.
 */
class Figure
{
    public const TYPES = [
        'pizza' => '🍕 کسر با پیتزا / دایره',
        'fraction_bar' => '🍫 کسر با نوار',
        'grid' => '🔲 جدولِ خانه‌ها (کسر، درصد، اعشار، مساحت)',
        'number_line' => '📏 محورِ اعداد',
        'bar_chart' => '📊 نمودارِ ستونی',
        'pie_chart' => '🥧 نمودارِ دایره‌ای',
        'pictograph' => '🍎 گروه‌ها / آرایه‌ی اشیا (ضرب و تقسیم)',
        'base10' => '🧱 مکعب‌های صدتایی، ده‌تایی، یکی',
        'clock' => '🕒 ساعت',
        'shape' => '🔺 شکلِ هندسی با اندازه',
        'angle' => '📐 زاویه',
        'ruler' => '📏 اندازه‌گیری با خط‌کش',
        'table' => '🧾 جدول',
    ];

    private const FONT = "Vazirmatn,Vazir,Tahoma,'Segoe UI',sans-serif";

    private const C = ['#7c3aed', '#f59e0b', '#10b981', '#ef4444', '#3b82f6', '#ec4899', '#14b8a6', '#f97316', '#8b5cf6', '#84cc16'];

    /** راهنمای کوتاهِ قالب برای پرامپتِ هوش مصنوعی. */
    public static function guide(): string
    {
        return implode("\n", [
            '{"type":"pizza","parts":8,"shaded":3,"wholes":1} — کسر با پیتزا/دایره (shaded خانه‌ی رنگی؛ کسرِ بزرگ‌تر از ۱: wholes>1)',
            '{"type":"fraction_bar","parts":5,"shaded":2,"wholes":1} — کسر با نوارِ شکلات',
            '{"type":"grid","rows":10,"cols":10,"shaded":35} — جدولِ خانه‌ها (درصد، اعشار، مساحت با شمردنِ خانه)',
            '{"type":"number_line","from":0,"to":10,"step":1,"points":[{"at":7,"label":"؟"}],"jumps":[{"from":2,"to":5}],"labels":null} — محورِ اعداد (labels: برچسبِ دلخواهِ هر درجه، مثلاً ["۰","۱/۴","۲/۴","۳/۴","۱"])',
            '{"type":"bar_chart","title":"…","labels":["…","…"],"values":[3,5],"unit":"نفر"} — نمودارِ ستونی',
            '{"type":"pie_chart","labels":["…","…"],"values":[50,25]} — نمودارِ دایره‌ای',
            '{"type":"pictograph","groups":[{"emoji":"🍎","count":4},{"emoji":"🍎","count":4}]} یا {"type":"pictograph","rows":3,"cols":5,"emoji":"⭐"} — گروه‌ها/آرایه برای جمع، ضرب و تقسیم',
            '{"type":"base10","hundreds":2,"tens":3,"ones":5} — ارزشِ مکانی',
            '{"type":"clock","hour":3,"minute":30} — ساعتِ عقربه‌ای',
            '{"type":"shape","kind":"triangle|right_triangle|square|rectangle|parallelogram|trapezoid|rhombus|circle|pentagon|hexagon","side_labels":["۸ سانتی‌متر","۵ سانتی‌متر"],"angle_labels":[],"center_label":null} — شکلِ هندسی. ترتیبِ side_labels: مستطیل/مربع/متوازی‌الاضلاع/ذوزنقه = [بالا، راست، پایین، چپ]؛ مثلثِ قائم‌الزاویه = [ضلعِ عمودی، ضلعِ افقی، وتر]؛ مثلث = [راست، پایین، چپ]؛ دایره = [برچسبِ شعاع]. ضلعِ بی‌برچسب = ""',
            '{"type":"angle","degrees":60,"label":"؟"} — زاویه',
            '{"type":"ruler","length":7,"max":10,"object":"✏️"} — اندازه‌گیری با خط‌کش (سانتی‌متر)',
            '{"type":"table","headers":["…","…"],"rows":[["…","…"]]} — جدولِ داده',
            'همه می‌توانند "caption" (زیرنویسِ کوتاه) داشته باشند.',
        ]);
    }

    /** پاک‌سازی و محدودکردنِ توصیف؛ null اگر نامعتبر است. */
    public static function normalize(mixed $spec): ?array
    {
        if (is_string($spec)) {
            $spec = json_decode(trim($spec), true);
        }
        if (! is_array($spec) || ! isset(self::TYPES[$spec['type'] ?? ''])) {
            return null;
        }
        $t = $spec['type'];
        $int = fn ($k, $min, $max, $def) => max($min, min($max, (int) round((float) self::num($spec[$k] ?? $def))));
        $str = fn ($v, $n = 40) => mb_substr(trim(strip_tags(is_scalar($v) ? (string) $v : '')), 0, $n);
        $out = ['type' => $t];
        if (! empty($spec['caption'])) {
            $out['caption'] = $str($spec['caption'], 80);
        }
        switch ($t) {
            case 'pizza':
            case 'fraction_bar':
                $out['parts'] = $int('parts', 1, 16, 4);
                $out['wholes'] = $int('wholes', 1, 4, max(1, (int) ceil(self::num($spec['shaded'] ?? 0) / max(1, $out['parts']))));
                $out['shaded'] = $int('shaded', 0, $out['parts'] * $out['wholes'], 1);
                break;
            case 'grid':
                $out['rows'] = $int('rows', 1, 12, 10);
                $out['cols'] = $int('cols', 1, 12, 10);
                $out['shaded'] = $int('shaded', 0, $out['rows'] * $out['cols'], 0);
                break;
            case 'number_line':
                $from = self::num($spec['from'] ?? 0);
                $to = self::num($spec['to'] ?? 10);
                if ($to <= $from) {
                    return null;
                }
                $step = self::num($spec['step'] ?? 1);
                $step = $step > 0 ? $step : 1;
                if (($to - $from) / $step > 40) {
                    $step = ($to - $from) / 20;
                }
                $out += ['from' => $from, 'to' => $to, 'step' => $step];
                $out['points'] = array_values(array_filter(array_map(fn ($p) => is_array($p) && isset($p['at']) ? ['at' => self::num($p['at']), 'label' => $str($p['label'] ?? '', 12)] : null, array_slice((array) ($spec['points'] ?? []), 0, 6))));
                $out['jumps'] = array_values(array_filter(array_map(fn ($j) => is_array($j) && isset($j['from'], $j['to']) ? ['from' => self::num($j['from']), 'to' => self::num($j['to']), 'label' => $str($j['label'] ?? '', 10)] : null, array_slice((array) ($spec['jumps'] ?? []), 0, 8))));
                $out['labels'] = is_array($spec['labels'] ?? null) ? array_map(fn ($l) => $str($l, 8), array_slice($spec['labels'], 0, 41)) : null;
                break;
            case 'bar_chart':
            case 'pie_chart':
                $labels = array_map(fn ($l) => $str($l, 18), array_slice((array) ($spec['labels'] ?? []), 0, 8));
                $values = array_map(fn ($v) => max(0, self::num($v)), array_slice((array) ($spec['values'] ?? []), 0, count($labels)));
                if (count($values) < 1 || count($values) !== count($labels) || array_sum($values) <= 0) {
                    return null;
                }
                $out += ['labels' => $labels, 'values' => $values, 'title' => $str($spec['title'] ?? '', 50), 'unit' => $str($spec['unit'] ?? '', 14)];
                break;
            case 'pictograph':
                $emoji = fn ($e) => mb_substr(trim((string) ($e ?? '')), 0, 4) ?: '🍎';
                if (! empty($spec['groups']) && is_array($spec['groups'])) {
                    $out['groups'] = array_values(array_filter(array_map(fn ($g) => is_array($g) ? ['emoji' => $emoji($g['emoji'] ?? null), 'count' => max(0, min(20, (int) self::num($g['count'] ?? 0)))] : null, array_slice($spec['groups'], 0, 8))));
                    if (! $out['groups']) {
                        return null;
                    }
                } else {
                    $out['rows'] = $int('rows', 1, 10, 3);
                    $out['cols'] = $int('cols', 1, 12, 4);
                    $out['emoji'] = $emoji($spec['emoji'] ?? null);
                }
                break;
            case 'base10':
                $out['hundreds'] = $int('hundreds', 0, 9, 0);
                $out['tens'] = $int('tens', 0, 9, 0);
                $out['ones'] = $int('ones', 0, 9, 0);
                break;
            case 'clock':
                $out['hour'] = $int('hour', 0, 23, 3) % 12 ?: 12;
                $out['minute'] = $int('minute', 0, 59, 0);
                break;
            case 'shape':
                $kinds = ['triangle', 'right_triangle', 'square', 'rectangle', 'parallelogram', 'trapezoid', 'rhombus', 'circle', 'pentagon', 'hexagon'];
                $out['kind'] = in_array($spec['kind'] ?? '', $kinds, true) ? $spec['kind'] : 'rectangle';
                $out['side_labels'] = array_map(fn ($l) => $str($l, 16), array_slice((array) ($spec['side_labels'] ?? []), 0, 6));
                $out['angle_labels'] = array_map(fn ($l) => $str($l, 10), array_slice((array) ($spec['angle_labels'] ?? []), 0, 6));
                $out['center_label'] = $str($spec['center_label'] ?? '', 16);
                break;
            case 'angle':
                $out['degrees'] = $int('degrees', 1, 359, 60);
                $out['label'] = $str($spec['label'] ?? '', 12);
                break;
            case 'ruler':
                $out['max'] = $int('max', 3, 20, 10);
                $out['length'] = max(0.5, min($out['max'], round(self::num($spec['length'] ?? 5) * 2) / 2));
                $out['object'] = mb_substr(trim((string) ($spec['object'] ?? '')), 0, 4);
                break;
            case 'table':
                $headers = array_map(fn ($h) => $str($h, 20), array_slice((array) ($spec['headers'] ?? []), 0, 6));
                $rows = [];
                foreach (array_slice((array) ($spec['rows'] ?? []), 0, 8) as $r) {
                    $rows[] = array_map(fn ($c) => $str($c, 20), array_slice((array) $r, 0, max(1, count($headers) ?: 6)));
                }
                if (! $headers && ! $rows) {
                    return null;
                }
                $out += ['headers' => $headers, 'rows' => $rows];
                break;
        }

        return $out;
    }

    /** توصیف → SVG (یا null اگر نامعتبر است). */
    public static function svg(mixed $spec): ?string
    {
        $s = self::normalize($spec);
        if (! $s) {
            return null;
        }
        [$w, $h, $body] = match ($s['type']) {
            'pizza' => self::pizza($s),
            'fraction_bar' => self::bar($s),
            'grid' => self::grid($s),
            'number_line' => self::numberLine($s),
            'bar_chart' => self::barChart($s),
            'pie_chart' => self::pieChart($s),
            'pictograph' => self::pictograph($s),
            'base10' => self::base10($s),
            'clock' => self::clock($s),
            'shape' => self::shape($s),
            'angle' => self::angle($s),
            'ruler' => self::ruler($s),
            'table' => self::table($s),
        };
        if (! empty($s['caption'])) {
            $body .= self::text($w / 2, $h + 22, $s['caption'], 17, '#334155', 'middle', 700);
            $h += 34;
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '" font-family="' . self::FONT . '">'
            . '<rect width="100%" height="100%" fill="#ffffff"/>' . $body . '</svg>';
    }

    /* ─────────────── شکل‌ها ─────────────── */

    private static function pizza(array $s): array
    {
        $r = 88;
        $n = $s['wholes'];
        $w = max(240, $n * 210 + 30);
        $out = '';
        for ($k = 0; $k < $n; $k++) {
            $cx = $w - 120 - $k * 210;   // راست‌به‌چپ: اولین پیتزا سمتِ راست
            $cy = 112;
            $out .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . ($r + 9) . '" fill="#d97706"/>';
            for ($i = 0; $i < $s['parts']; $i++) {
                $on = $k * $s['parts'] + $i < $s['shaded'];
                $a0 = 360 / $s['parts'] * $i;
                $a1 = 360 / $s['parts'] * ($i + 1);
                $out .= '<path d="' . self::wedge($cx, $cy, $r, $a0, $a1) . '" fill="' . ($on ? '#fcd34d' : '#fff7ed') . '" stroke="#92400e" stroke-width="2.5"/>';
                if ($on) {   // فلفل‌قرمز روی برش‌های رنگی
                    $mid = deg2rad(($a0 + $a1) / 2 - 90);
                    $d = $s['parts'] > 10 ? 0.62 : 0.55;
                    $out .= '<circle cx="' . round($cx + cos($mid) * $r * $d, 1) . '" cy="' . round($cy + sin($mid) * $r * $d, 1) . '" r="' . ($s['parts'] > 10 ? 5 : 8) . '" fill="#dc2626"/>';
                }
            }
        }

        return [$w, 225, $out];
    }

    private static function bar(array $s): array
    {
        $bw = 420;
        $out = '';
        for ($k = 0; $k < $s['wholes']; $k++) {
            $y = 20 + $k * 74;
            $cw = $bw / $s['parts'];
            for ($i = 0; $i < $s['parts']; $i++) {
                $on = $k * $s['parts'] + $i < $s['shaded'];
                $x = 20 + $bw - ($i + 1) * $cw;   // از راست پر می‌شود
                $out .= '<rect x="' . round($x, 1) . '" y="' . $y . '" width="' . round($cw, 1) . '" height="56" fill="' . ($on ? '#8b5a2b' : '#f5e6d3') . '" stroke="#5b3a1a" stroke-width="2.5"/>';
            }
        }

        return [460, 20 + $s['wholes'] * 74, $out];
    }

    private static function grid(array $s): array
    {
        $cell = min(34, (int) floor(340 / max($s['rows'], $s['cols'])));
        $w = $s['cols'] * $cell + 40;
        $h = $s['rows'] * $cell + 40;
        $out = '';
        $n = 0;
        for ($r = 0; $r < $s['rows']; $r++) {
            for ($c = 0; $c < $s['cols']; $c++) {
                $on = $n++ < $s['shaded'];
                $x = 20 + ($s['cols'] - 1 - $c) * $cell;
                $out .= '<rect x="' . $x . '" y="' . (20 + $r * $cell) . '" width="' . $cell . '" height="' . $cell . '" fill="' . ($on ? '#a78bfa' : '#ffffff') . '" stroke="#475569" stroke-width="1.5"/>';
            }
        }

        return [$w, $h, $out];
    }

    private static function numberLine(array $s): array
    {
        $w = 560;
        $x0 = 40;
        $x1 = $w - 40;
        $y = 90;
        $pos = fn ($v) => round($x0 + ($v - $s['from']) / ($s['to'] - $s['from']) * ($x1 - $x0), 1);
        $out = '<line x1="' . ($x0 - 18) . '" y1="' . $y . '" x2="' . ($x1 + 18) . '" y2="' . $y . '" stroke="#1e293b" stroke-width="3"/>'
            . '<path d="M' . ($x1 + 22) . ' ' . $y . ' l-12 -7 v14 z M' . ($x0 - 22) . ' ' . $y . ' l12 -7 v14 z" fill="#1e293b"/>';
        $ticks = (int) round(($s['to'] - $s['from']) / $s['step']);
        $every = $ticks > 20 ? (int) ceil($ticks / 10) : 1;
        for ($i = 0; $i <= $ticks; $i++) {
            $v = $s['from'] + $i * $s['step'];
            $x = $pos($v);
            $out .= '<line x1="' . $x . '" y1="' . ($y - 10) . '" x2="' . $x . '" y2="' . ($y + 10) . '" stroke="#1e293b" stroke-width="2"/>';
            $label = $s['labels'][$i] ?? ($i % $every === 0 ? self::fa(self::fmt($v)) : null);
            if ($label !== null && $label !== '') {
                $out .= self::text($x, $y + 34, $label, 16, '#1e293b');
            }
        }
        foreach ($s['jumps'] as $k => $j) {
            $a = $pos($j['from']);
            $b = $pos($j['to']);
            $top = $y - 22 - min(46, abs($b - $a) / 3);
            $out .= '<path d="M' . $a . ' ' . ($y - 6) . ' Q' . (($a + $b) / 2) . ' ' . $top . ' ' . $b . ' ' . ($y - 6) . '" fill="none" stroke="' . self::C[$k % 10] . '" stroke-width="2.5"/>'
                . '<circle cx="' . $b . '" cy="' . ($y - 6) . '" r="4" fill="' . self::C[$k % 10] . '"/>';
            if ($j['label'] !== '') {
                $out .= self::text(($a + $b) / 2, ($y + $top) / 2 - 4, self::fa($j['label']), 15, self::C[$k % 10]);
            }
        }
        foreach ($s['points'] as $p) {
            $x = $pos($p['at']);
            $out .= '<circle cx="' . $x . '" cy="' . $y . '" r="8" fill="#ef4444" stroke="#fff" stroke-width="2"/>';
            if ($p['label'] !== '') {
                $out .= self::text($x, $y - 20, self::fa($p['label']), 18, '#ef4444', 'middle', 800);
            }
        }

        return [$w, 140, $out];
    }

    private static function barChart(array $s): array
    {
        $n = count($s['values']);
        $w = max(360, 90 + $n * 74);
        $top = $s['title'] !== '' ? 46 : 20;
        $h = $top + 230;
        $max = self::niceMax(max($s['values']));
        $base = $top + 180;
        $x0 = 30;
        $x1 = $w - 56;
        $out = $s['title'] !== '' ? self::text($w / 2, 28, $s['title'], 18, '#1e293b', 'middle', 800) : '';
        for ($g = 0; $g <= 4; $g++) {
            $v = $max / 4 * $g;
            $gy = round($base - 180 * $g / 4, 1);
            $out .= '<line x1="' . $x0 . '" y1="' . $gy . '" x2="' . $x1 . '" y2="' . $gy . '" stroke="#e2e8f0" stroke-width="1.5"/>'
                . self::text($x1 + 8, $gy + 5, self::fa(self::fmt($v)), 13, '#64748b', 'start');
        }
        $slot = ($x1 - $x0) / $n;
        foreach ($s['values'] as $i => $v) {
            $bh = round(180 * $v / $max, 1);
            $x = round($x1 - ($i + 1) * $slot + $slot * 0.18, 1);   // اولین ستون سمتِ راست
            $bw = round($slot * 0.64, 1);
            $out .= '<rect x="' . $x . '" y="' . ($base - $bh) . '" width="' . $bw . '" height="' . $bh . '" rx="5" fill="' . self::C[$i % 10] . '"/>'
                . self::text($x + $bw / 2, $base - $bh - 7, self::fa(self::fmt($v)), 14, '#1e293b', 'middle', 700)
                . self::text($x + $bw / 2, $base + 22, $s['labels'][$i], 14, '#334155');
        }
        $out .= '<line x1="' . $x0 . '" y1="' . $base . '" x2="' . $x1 . '" y2="' . $base . '" stroke="#1e293b" stroke-width="2.5"/>';
        if ($s['unit'] !== '') {
            $out .= self::text($x1 + 8, $top - 6, '(' . $s['unit'] . ')', 12, '#64748b', 'start');
        }

        return [$w, $h, $out];
    }

    private static function pieChart(array $s): array
    {
        $sum = array_sum($s['values']);
        $cx = 380;
        $cy = 130;
        $r = 105;
        $top = $s['title'] !== '' ? 30 : 0;
        $out = $s['title'] !== '' ? self::text(260, 26, $s['title'], 18, '#1e293b', 'middle', 800) : '';
        $a = 0;
        foreach ($s['values'] as $i => $v) {
            $b = $a + 360 * $v / $sum;
            $out .= $v >= $sum
                ? '<circle cx="' . $cx . '" cy="' . ($cy + $top) . '" r="' . $r . '" fill="' . self::C[$i % 10] . '" stroke="#fff" stroke-width="3"/>'
                : '<path d="' . self::wedge($cx, $cy + $top, $r, $a, $b) . '" fill="' . self::C[$i % 10] . '" stroke="#fff" stroke-width="3"/>';
            $a = $b;
            $ly = $top + 40 + $i * 30;
            $out .= '<rect x="232" y="' . ($ly - 13) . '" width="18" height="18" rx="4" fill="' . self::C[$i % 10] . '"/>'
                . self::text(222, $ly + 1, $s['labels'][$i] . ': ' . self::fa(self::fmt(round(100 * $v / $sum, 1))) . '٪', 16, '#1e293b', 'end');
        }

        return [520, max(270, $top + 50 + count($s['values']) * 30) + $top, $out];
    }

    private static function pictograph(array $s): array
    {
        $out = '';
        if (isset($s['groups'])) {
            $per = 5;
            $gx = 20;
            $maxH = 0;
            $cells = [];
            foreach ($s['groups'] as $g) {
                $cols = min($per, max(1, $g['count']));
                $rows = max(1, (int) ceil($g['count'] / $per));
                $cells[] = [$g, $cols * 36 + 20, $rows * 38 + 18];
                $maxH = max($maxH, $rows * 38 + 18);
            }
            $w = array_sum(array_column($cells, 1)) + 16 * count($cells) + 24;
            $x = $w - 20;
            foreach ($cells as [$g, $bw, $bh]) {
                $x -= $bw;
                $out .= '<rect x="' . $x . '" y="16" width="' . $bw . '" height="' . $bh . '" rx="14" fill="#f8fafc" stroke="#94a3b8" stroke-width="2" stroke-dasharray="6 5"/>';
                for ($i = 0; $i < $g['count']; $i++) {
                    $out .= self::text($x + $bw - 28 - ($i % $per) * 36, 50 + intdiv($i, $per) * 38, $g['emoji'], 28, '#000');
                }
                $x -= 16;
            }

            return [max(200, $w), $maxH + 32, $out];
        }
        $w = $s['cols'] * 42 + 40;
        for ($r = 0; $r < $s['rows']; $r++) {
            for ($c = 0; $c < $s['cols']; $c++) {
                $out .= self::text($w - 40 - $c * 42, 48 + $r * 42, $s['emoji'], 30, '#000');
            }
        }

        return [max(160, $w), $s['rows'] * 42 + 30, $out];
    }

    private static function base10(array $s): array
    {
        // مثلِ نوشتنِ عدد: صدتایی‌ها چپ، ده‌تایی‌ها وسط، یکی‌ها راست
        $out = '';
        $x = 20;
        $u = 14;
        for ($i = 0; $i < $s['hundreds']; $i++) {
            for ($r = 0; $r < 10; $r++) {
                for ($c = 0; $c < 10; $c++) {
                    $out .= '<rect x="' . ($x + $c * $u) . '" y="' . (40 + $r * $u) . '" width="' . $u . '" height="' . $u . '" fill="#c4b5fd" stroke="#6d28d9" stroke-width="1"/>';
                }
            }
            $x += 10 * $u + 14;
        }
        $x += $s['hundreds'] ? 10 : 0;
        for ($i = 0; $i < $s['tens']; $i++) {
            for ($k = 0; $k < 10; $k++) {
                $out .= '<rect x="' . $x . '" y="' . (40 + $k * $u) . '" width="' . $u . '" height="' . $u . '" fill="#93c5fd" stroke="#1d4ed8" stroke-width="1.2"/>';
            }
            $x += $u + 8;
        }
        $x += $s['tens'] ? 18 : 0;
        for ($i = 0; $i < $s['ones']; $i++) {
            $out .= '<rect x="' . ($x + ($i % 3) * ($u + 4)) . '" y="' . (126 + intdiv($i, 3) * ($u + 4)) . '" width="' . $u . '" height="' . $u . '" fill="#fde68a" stroke="#b45309" stroke-width="1.5"/>';
        }
        $x += $s['ones'] ? 60 : 0;

        return [max(200, $x + 10), 200, $out];
    }

    private static function clock(array $s): array
    {
        $cx = 130;
        $cy = 130;
        $r = 108;
        $out = '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="#fff" stroke="#1e293b" stroke-width="6"/>';
        for ($i = 0; $i < 60; $i++) {
            $a = deg2rad($i * 6 - 90);
            $l = $i % 5 ? 6 : 13;
            $out .= '<line x1="' . round($cx + cos($a) * ($r - 4), 1) . '" y1="' . round($cy + sin($a) * ($r - 4), 1) . '" x2="' . round($cx + cos($a) * ($r - 4 - $l), 1) . '" y2="' . round($cy + sin($a) * ($r - 4 - $l), 1) . '" stroke="#1e293b" stroke-width="' . ($i % 5 ? 1.5 : 3) . '"/>';
        }
        for ($n = 1; $n <= 12; $n++) {
            $a = deg2rad($n * 30 - 90);
            $out .= self::text(round($cx + cos($a) * ($r - 32), 1), round($cy + sin($a) * ($r - 32) + 7, 1), self::fa((string) $n), 20, '#1e293b', 'middle', 800);
        }
        $ha = deg2rad(($s['hour'] % 12 + $s['minute'] / 60) * 30 - 90);
        $ma = deg2rad($s['minute'] * 6 - 90);
        $out .= '<line x1="' . $cx . '" y1="' . $cy . '" x2="' . round($cx + cos($ha) * 55, 1) . '" y2="' . round($cy + sin($ha) * 55, 1) . '" stroke="#1e293b" stroke-width="8" stroke-linecap="round"/>'
            . '<line x1="' . $cx . '" y1="' . $cy . '" x2="' . round($cx + cos($ma) * 84, 1) . '" y2="' . round($cy + sin($ma) * 84, 1) . '" stroke="#7c3aed" stroke-width="5" stroke-linecap="round"/>'
            . '<circle cx="' . $cx . '" cy="' . $cy . '" r="7" fill="#1e293b"/>';

        return [260, 260, $out];
    }

    private static function shape(array $s): array
    {
        $W = 440;
        $H = 300;
        $pts = match ($s['kind']) {
            'triangle' => [[220, 40], [380, 250], [60, 250]],
            'right_triangle' => [[90, 50], [90, 250], [360, 250]],
            'square' => [[120, 50], [320, 50], [320, 250], [120, 250]],
            'rectangle' => [[60, 80], [380, 80], [380, 230], [60, 230]],
            'parallelogram' => [[130, 70], [390, 70], [310, 230], [50, 230]],
            'trapezoid' => [[140, 70], [300, 70], [390, 230], [50, 230]],
            'rhombus' => [[220, 30], [360, 150], [220, 270], [80, 150]],
            'pentagon', 'hexagon' => self::regular($s['kind'] === 'pentagon' ? 5 : 6, 220, 150, 115),
            default => [],
        };
        if ($s['kind'] === 'circle') {
            $out = '<circle cx="220" cy="150" r="115" fill="#ede9fe" stroke="#5b21b6" stroke-width="3.5"/>'
                . '<circle cx="220" cy="150" r="4" fill="#5b21b6"/><line x1="220" y1="150" x2="335" y2="150" stroke="#5b21b6" stroke-width="2.5" stroke-dasharray="7 5"/>';
            if ($l = $s['side_labels'][0] ?? '') {
                $out .= self::text(278, 140, self::fa($l), 17, '#1e293b', 'middle', 700);
            }
            if ($s['center_label'] !== '') {
                $out .= self::text(220, 190, self::fa($s['center_label']), 17, '#1e293b', 'middle', 700);
            }

            return [$W + 140, $H, '<g transform="translate(70,0)">' . $out . '</g>'];
        }
        $poly = implode(' ', array_map(fn ($p) => $p[0] . ',' . $p[1], $pts));
        $out = '<polygon points="' . $poly . '" fill="#ede9fe" stroke="#5b21b6" stroke-width="3.5" stroke-linejoin="round"/>';
        $n = count($pts);
        [$mx, $my] = [array_sum(array_column($pts, 0)) / $n, array_sum(array_column($pts, 1)) / $n];
        foreach ($s['side_labels'] as $i => $l) {
            if ($l === '' || $i >= $n) {
                continue;
            }
            [$a, $b] = [$pts[$i], $pts[($i + 1) % $n]];
            $x = ($a[0] + $b[0]) / 2;
            $y = ($a[1] + $b[1]) / 2;
            $dx = $x - $mx;
            $dy = $y - $my;
            $d = max(1, sqrt($dx * $dx + $dy * $dy));
            $ux = $dx / $d;
            $anchor = $ux > 0.5 ? 'start' : ($ux < -0.5 ? 'end' : 'middle');
            $out .= self::text(round($x + $ux * 16, 1), round($y + $dy / $d * 24 + 6, 1), self::fa($l), 17, '#1e293b', $anchor, 700);
        }
        foreach ($s['angle_labels'] as $i => $l) {
            if ($l === '' || $i >= $n) {
                continue;
            }
            $p = $pts[$i];
            $out .= self::text(round($p[0] + ($mx - $p[0]) * 0.22, 1), round($p[1] + ($my - $p[1]) * 0.22 + 6, 1), self::fa($l), 15, '#b45309', 'middle', 700);
        }
        if ($s['kind'] === 'right_triangle') {
            $out .= '<path d="M90 228 h22 v22" fill="none" stroke="#5b21b6" stroke-width="2.5"/>';
        }
        if ($s['center_label'] !== '') {
            $out .= self::text(round($mx, 1), round($my + 6, 1), self::fa($s['center_label']), 17, '#5b21b6', 'middle', 800);
        }

        // حاشیه‌ی دو طرف برای برچسبِ ضلع‌های کناری
        return [$W + 140, $H, '<g transform="translate(70,0)">' . $out . '</g>'];
    }

    private static function angle(array $s): array
    {
        $cx = 160;
        $cy = 200;
        $L = 170;
        $a = deg2rad(-$s['degrees']);
        $x2 = round($cx + cos($a) * $L, 1);
        $y2 = round($cy + sin($a) * $L, 1);
        $r = 46;
        $large = $s['degrees'] > 180 ? 1 : 0;
        $out = '<line x1="' . $cx . '" y1="' . $cy . '" x2="' . ($cx + $L) . '" y2="' . $cy . '" stroke="#1e293b" stroke-width="4" stroke-linecap="round"/>'
            . '<line x1="' . $cx . '" y1="' . $cy . '" x2="' . $x2 . '" y2="' . $y2 . '" stroke="#1e293b" stroke-width="4" stroke-linecap="round"/>'
            . ($s['degrees'] === 90
                ? '<path d="M' . ($cx + 26) . ' ' . $cy . ' v-26 h-26" fill="none" stroke="#7c3aed" stroke-width="3"/>'
                : '<path d="M' . ($cx + $r) . ' ' . $cy . ' A' . $r . ' ' . $r . ' 0 ' . $large . ' 0 ' . round($cx + cos($a) * $r, 1) . ' ' . round($cy + sin($a) * $r, 1) . '" fill="none" stroke="#7c3aed" stroke-width="3"/>');
        $m = $a / 2;
        $out .= self::text(round($cx + cos($m) * ($r + 30), 1), round($cy + sin($m) * ($r + 30) + 6, 1), self::fa($s['label'] !== '' ? $s['label'] : $s['degrees'] . '°'), 18, '#7c3aed', 'middle', 800);

        return [360, 240, $out];
    }

    private static function ruler(array $s): array
    {
        $u = (int) floor(480 / $s['max']);
        $w = $s['max'] * $u + 60;
        $x0 = 30;
        $out = '<rect x="' . ($x0 - 10) . '" y="96" width="' . ($s['max'] * $u + 20) . '" height="58" rx="6" fill="#fef9c3" stroke="#a16207" stroke-width="2"/>';
        for ($i = 0; $i <= $s['max'] * 2; $i++) {
            $x = $x0 + $i * $u / 2;
            $out .= '<line x1="' . $x . '" y1="96" x2="' . $x . '" y2="' . (96 + ($i % 2 ? 12 : 22)) . '" stroke="#713f12" stroke-width="2"/>';
            if ($i % 2 === 0) {
                $out .= self::text($x, 144, self::fa((string) ($i / 2)), 14, '#713f12');
            }
        }
        // جسم از صفر شروع می‌شود (خط‌کش از چپ مثلِ واقعیت)
        $len = $s['length'] * $u;
        $out .= '<rect x="' . $x0 . '" y="50" width="' . $len . '" height="30" rx="12" fill="#a78bfa" stroke="#5b21b6" stroke-width="2"/>'
            . ($s['object'] !== '' ? self::text($x0 + $len / 2, 73, $s['object'], 22, '#000') : '')
            . '<line x1="' . $x0 . '" y1="80" x2="' . $x0 . '" y2="96" stroke="#5b21b6" stroke-dasharray="3 3"/><line x1="' . ($x0 + $len) . '" y1="80" x2="' . ($x0 + $len) . '" y2="96" stroke="#5b21b6" stroke-dasharray="3 3"/>';

        return [$w, 170, $out];
    }

    private static function table(array $s): array
    {
        $cols = max(count($s['headers']), max(array_map('count', $s['rows'] ?: [[1]])));
        $cw = min(150, (int) floor(560 / $cols));
        $w = $cols * $cw + 20;
        $y = 10;
        $out = '';
        $row = function (array $cells, bool $head) use (&$out, &$y, $cols, $cw, $w) {
            for ($c = 0; $c < $cols; $c++) {
                $x = $w - 10 - ($c + 1) * $cw;   // ستونِ اول سمتِ راست
                $out .= '<rect x="' . $x . '" y="' . $y . '" width="' . $cw . '" height="40" fill="' . ($head ? '#ede9fe' : '#ffffff') . '" stroke="#64748b" stroke-width="1.5"/>'
                    . self::text($x + $cw / 2, $y + 26, self::fa($cells[$c] ?? ''), 15, '#1e293b', 'middle', $head ? 800 : 500);
            }
            $y += 40;
        };
        if ($s['headers']) {
            $row($s['headers'], true);
        }
        foreach ($s['rows'] as $r) {
            $row($r, false);
        }

        return [$w, $y + 10, $out];
    }

    /* ─────────────── کمکی ─────────────── */

    private static function regular(int $n, float $cx, float $cy, float $r): array
    {
        $pts = [];
        for ($i = 0; $i < $n; $i++) {
            $a = deg2rad(-90 + 360 / $n * $i);
            $pts[] = [round($cx + cos($a) * $r, 1), round($cy + sin($a) * $r, 1)];
        }

        return $pts;
    }

    /** برشِ دایره از زاویه‌ی a0 تا a1 (درجه، از بالا و ساعت‌گرد). */
    private static function wedge(float $cx, float $cy, float $r, float $a0, float $a1): string
    {
        if ($a1 - $a0 >= 359.99) {
            return 'M' . ($cx - $r) . ' ' . $cy . ' a' . $r . ' ' . $r . ' 0 1 0 ' . (2 * $r) . ' 0 a' . $r . ' ' . $r . ' 0 1 0 ' . (-2 * $r) . ' 0';
        }
        $p = fn ($a) => round($cx + cos(deg2rad($a - 90)) * $r, 2) . ' ' . round($cy + sin(deg2rad($a - 90)) * $r, 2);

        return 'M' . $cx . ' ' . $cy . ' L' . $p($a0) . ' A' . $r . ' ' . $r . ' 0 ' . ($a1 - $a0 > 180 ? 1 : 0) . ' 1 ' . $p($a1) . ' Z';
    }

    /** $anchor بصری است: start = لبه‌ی چپِ متن روی x، end = لبه‌ی راست. (در direction=rtl معنای start/end برعکس است) */
    private static function text(float $x, float $y, string $t, int $size, string $fill, string $anchor = 'middle', int $weight = 600): string
    {
        $anchor = ['start' => 'end', 'end' => 'start'][$anchor] ?? 'middle';

        return '<text x="' . round($x, 1) . '" y="' . round($y, 1) . '" font-size="' . $size . '" fill="' . $fill . '" text-anchor="' . $anchor . '" font-weight="' . $weight . '" direction="rtl">'
            . htmlspecialchars($t, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</text>';
    }

    private static function niceMax(float $v): float
    {
        if ($v <= 0) {
            return 1;
        }
        $p = 10 ** floor(log10($v));
        foreach ([1, 2, 2.5, 5, 10] as $m) {
            if ($v <= $m * $p) {
                return $m * $p;
            }
        }

        return 10 * $p;
    }

    /** «۳/۴»، «۲٫۵»، «12» → عدد. */
    public static function num(mixed $v): float
    {
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        $s = strtr(trim((string) $v), ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٫' => '.', '/' => '/', '÷' => '/']);
        if (preg_match('#^(-?\d+(?:\.\d+)?)\s*/\s*(\d+(?:\.\d+)?)$#', $s, $m) && (float) $m[2] != 0.0) {
            return (float) $m[1] / (float) $m[2];
        }

        return (float) $s;
    }

    private static function fmt(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') ?: '0';
    }

    private static function fa(string $s): string
    {
        return strtr($s, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹', '.' => '٫']);
    }
}
