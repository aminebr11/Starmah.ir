<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * تصویرسازِ محلیِ کاربرگ — بدونِ اینترنت، بدونِ کلید، بدونِ هزینه.
 *
 * ── چرا هست ───────────────────────────────────────────────────────────
 * تصویرسازِ هوش مصنوعی به کلیدِ API و اینترنت نیاز دارد و گاهی در دسترس
 * نیست. پیش از این، در نبودِ کلید هیچ تصویری ساخته نمی‌شد و کاربرگ فقط
 * یک قالبِ رنگیِ ثابت داشت. حالا در همان حالت هم یک «صحنه»ی واقعیِ
 * برداری (SVG) ساخته می‌شود که:
 *
 *   • با تمِ انتخابیِ معلم می‌خواند (فضایی، فوتبال، مکعبی، سرعت، کلاسیک)،
 *   • با عنوانِ کاربرگ «بذر» می‌گیرد، پس دو کاربرگ دو تصویرِ متفاوت دارند،
 *   • برداری است، پس چاپِ A4 هیچ پیکسلی نمی‌شود و حجمش چند کیلوبایت است.
 *
 * خروجی یک فایلِ .svg روی دیسکِ public است؛ دقیقاً مثل تصویرِ AI مصرف
 * می‌شود (همان ستونِ image_path).
 */
class WorksheetArtService
{
    /** پالتِ هر تم. */
    private const PALETTE = [
        'stars' => ['sky1' => '#0b1638', 'sky2' => '#3b1f6e', 'accent' => '#f5b53f', 'soft' => '#7aa2f7'],
        'pitch' => ['sky1' => '#7cc6f5', 'sky2' => '#cdeaff', 'accent' => '#2bb673', 'soft' => '#0f9d58'],
        'blocks' => ['sky1' => '#6fc2f0', 'sky2' => '#bfe6ff', 'accent' => '#4caf50', 'soft' => '#8d6e63'],
        'speed' => ['sky1' => '#2b1355', 'sky2' => '#e8862e', 'accent' => '#ffd166', 'soft' => '#d64545'],
        'classic' => ['sky1' => '#eef3fd', 'sky2' => '#ffffff', 'accent' => '#3d7bf0', 'soft' => '#2555c0'],
    ];

    private const W = 1024;
    private const H = 560;

    /** مولدِ شبه‌تصادفیِ بذردار — همان عنوان همیشه همان تصویر را می‌دهد. */
    private int $seed = 1;

    /** پسوندِ یکتای شناسه‌های داخلیِ SVG. */
    private string $uid = 'x';

    /**
     * ساخت و ذخیره‌ی تصویر.
     *
     * @return string|null مسیرِ نسبیِ فایل روی دیسکِ public
     */
    public function generate(string $title, string $subject, string $themeKey, string $spec = ''): ?string
    {
        try {
            $svg = $this->svg($title, $subject, $themeKey, $spec);
            $path = 'worksheet-images/art_' . substr(sha1($title . $themeKey . microtime(true)), 0, 16) . '.svg';
            Storage::disk('public')->put($path, $svg);

            return $path;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** همان تصویر، ولی به‌صورتِ رشته (برای پیش‌نمایشِ بدونِ ذخیره). */
    public function svg(string $title, string $subject, string $themeKey, string $spec = ''): string
    {
        $this->seed = max(1, crc32($title . '|' . $subject . '|' . $spec) % 2147483647);
        $this->uid = substr(sha1($title . '|' . $subject . '|' . $themeKey . '|' . $spec), 0, 8);
        $key = isset(self::PALETTE[$themeKey]) ? $themeKey : 'classic';
        $p = self::PALETTE[$key];

        $scene = match ($key) {
            'stars' => $this->sceneStars($p),
            'pitch' => $this->scenePitch($p),
            'blocks' => $this->sceneBlocks($p),
            'speed' => $this->sceneSpeed($p),
            default => $this->sceneClassic($p),
        };

        $w = self::W;
        $h = self::H;
        $u = $this->uid;   // شناسه‌های یکتا: دو تصویر در یک صفحه نباید gradient هم را بدزدند

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$w} {$h}" width="{$w}" height="{$h}" role="img" aria-label="تصویرِ کاربرگ">
          <defs>
            <linearGradient id="sky{$u}" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="{$p['sky1']}"/><stop offset="100%" stop-color="{$p['sky2']}"/>
            </linearGradient>
            <radialGradient id="glow{$u}" cx="50%" cy="45%" r="70%">
              <stop offset="0%" stop-color="#ffffff" stop-opacity=".16"/>
              <stop offset="100%" stop-color="#ffffff" stop-opacity="0"/>
            </radialGradient>
            <clipPath id="frame{$u}"><rect x="0" y="0" width="{$w}" height="{$h}" rx="28"/></clipPath>
          </defs>
          <g clip-path="url(#frame{$u})">
            <rect width="{$w}" height="{$h}" fill="url(#sky{$u})"/>
            {$scene}
            <rect width="{$w}" height="{$h}" fill="url(#glow{$u})"/>
          </g>
          <rect x="3" y="3" width="{$this->i($w - 6)}" height="{$this->i($h - 6)}" rx="26" fill="none" stroke="{$p['accent']}" stroke-opacity=".55" stroke-width="6"/>
        </svg>
        SVG;
    }

    /* ═══════════════════════════ صحنه‌ها ═══════════════════════════ */

    /** شبِ پرستاره: ماه، سیاره‌ی حلقه‌دار، موشک و ستاره‌های درخشان. */
    private function sceneStars(array $p): string
    {
        $out = '';

        // ستاره‌های ریز
        for ($i = 0; $i < 90; $i++) {
            $x = $this->rnd(10, self::W - 10);
            $y = $this->rnd(10, self::H - 10);
            $r = $this->rnd(1, 3);
            $o = $this->rnd(25, 95) / 100;
            $out .= "<circle cx=\"{$x}\" cy=\"{$y}\" r=\"{$r}\" fill=\"#fff\" opacity=\"{$o}\"/>";
        }

        // ستاره‌های چهارپَر
        for ($i = 0; $i < 7; $i++) {
            $x = $this->rnd(70, self::W - 70);
            $y = $this->rnd(50, self::H - 120);
            $s = $this->rnd(9, 18);
            $out .= $this->sparkle($x, $y, $s, $p['accent']);
        }

        // ماه (هلال با ماسکِ دو دایره)
        $mx = 190;
        $my = 150;
        $out .= '<mask id="moon' . $this->uid . '"><rect width="' . self::W . '" height="' . self::H . '" fill="#000"/>'
            . "<circle cx=\"{$mx}\" cy=\"{$my}\" r=\"78\" fill=\"#fff\"/>"
            . '<circle cx="' . ($mx - 34) . '" cy="' . ($my - 26) . '" r="70" fill="#000"/></mask>'
            . "<circle cx=\"{$mx}\" cy=\"{$my}\" r=\"78\" fill=\"#ffe6a3\" mask=\"url(#moon{$this->uid})\"/>";

        // سیاره‌ی حلقه‌دار
        $px = self::W - 200;
        $py = 190;
        $out .= "<g transform=\"rotate(-18 {$px} {$py})\">"
            . "<ellipse cx=\"{$px}\" cy=\"{$py}\" rx=\"128\" ry=\"26\" fill=\"none\" stroke=\"{$p['soft']}\" stroke-width=\"11\" opacity=\".75\"/>"
            . "<circle cx=\"{$px}\" cy=\"{$py}\" r=\"62\" fill=\"{$p['accent']}\"/>"
            . '<circle cx="' . ($px - 20) . '" cy="' . ($py - 18) . '" r="12" fill="#fff" opacity=".28"/>'
            . '<circle cx="' . ($px + 24) . '" cy="' . ($py + 14) . '" r="8" fill="#fff" opacity=".2"/>'
            . "<ellipse cx=\"{$px}\" cy=\"{$py}\" rx=\"128\" ry=\"26\" fill=\"none\" stroke=\"{$p['soft']}\" stroke-width=\"11\" opacity=\".95\" stroke-dasharray=\"170 400\"/>"
            . '</g>';

        // موشک
        $rx = self::W / 2 + $this->rnd(-90, 90);
        $ry = self::H - 190;
        $out .= $this->rocket($rx, $ry, $p);

        // زمینِ افق (تپه‌ی تیره)
        $out .= '<path d="M0,' . (self::H - 70) . ' C 200,' . (self::H - 130) . ' 420,' . (self::H - 40)
            . ' 640,' . (self::H - 90) . ' S 900,' . (self::H - 140) . ' ' . self::W . ',' . (self::H - 80)
            . ' L' . self::W . ',' . self::H . ' L0,' . self::H . ' Z" fill="#0a1130" opacity=".85"/>';

        return $out;
    }

    private function rocket(int $x, int $y, array $p): string
    {
        return "<g transform=\"translate({$x},{$y}) rotate(18)\">"
            . "<path d=\"M0,-70 C22,-42 26,-2 22,30 L-22,30 C-26,-2 -22,-42 0,-70 Z\" fill=\"#f4f7ff\"/>"
            . "<path d=\"M0,-70 C10,-52 14,-32 15,-14 L-15,-14 C-14,-32 -10,-52 0,-70 Z\" fill=\"{$p['accent']}\" opacity=\".9\"/>"
            . "<circle cx=\"0\" cy=\"-12\" r=\"12\" fill=\"{$p['soft']}\"/><circle cx=\"0\" cy=\"-12\" r=\"6\" fill=\"#fff\" opacity=\".65\"/>"
            . "<path d=\"M-22,10 L-46,42 L-20,32 Z\" fill=\"{$p['soft']}\"/><path d=\"M22,10 L46,42 L20,32 Z\" fill=\"{$p['soft']}\"/>"
            . "<path d=\"M-13,30 C-8,58 8,58 13,30 Z\" fill=\"#ff9f43\"/><path d=\"M-7,32 C-4,52 4,52 7,32 Z\" fill=\"#ffe066\"/>"
            . '</g>';
    }

    /** زمینِ فوتبال: چمنِ راه‌راه، دروازه با تور، توپ و نورافکن. */
    private function scenePitch(array $p): string
    {
        $out = '';
        $horizon = 250;

        // خورشید و ابرها
        $out .= '<circle cx="' . (self::W - 140) . '" cy="105" r="52" fill="#ffe08a"/>'
            . '<circle cx="' . (self::W - 140) . '" cy="105" r="72" fill="#ffe08a" opacity=".28"/>';
        for ($i = 0; $i < 4; $i++) {
            $out .= $this->cloud($this->rnd(60, self::W - 200), $this->rnd(50, 170), $this->rnd(70, 130) / 100);
        }

        // چمن
        $out .= "<rect y=\"{$horizon}\" width=\"" . self::W . '" height="' . (self::H - $horizon) . "\" fill=\"{$p['soft']}\"/>";
        for ($i = 0; $i < 9; $i++) {
            if ($i % 2 === 0) {
                $yy = $horizon + (int) round(($i / 9) * (self::H - $horizon));
                $hh = (int) ceil((self::H - $horizon) / 9) + 2;
                $out .= "<rect y=\"{$yy}\" width=\"" . self::W . "\" height=\"{$hh}\" fill=\"#ffffff\" opacity=\".07\"/>";
            }
        }
        $out .= "<rect y=\"{$horizon}\" width=\"" . self::W . '" height="8" fill="#ffffff" opacity=".35"/>';

        // خط‌کشیِ زمین
        $out .= '<ellipse cx="' . (self::W / 2) . '" cy="' . (self::H - 60) . '" rx="210" ry="58" fill="none" stroke="#fff" stroke-width="5" opacity=".55"/>'
            . '<line x1="0" y1="' . ($horizon + 70) . '" x2="' . self::W . '" y2="' . ($horizon + 70) . '" stroke="#fff" stroke-width="4" opacity=".35"/>';

        // دروازه با تور
        $gx = 120;
        $gy = $horizon + 4;
        $gw = 300;
        $gh = 140;
        $net = '';
        for ($i = 0; $i <= 10; $i++) {
            $lx = $gx + (int) round($i * $gw / 10);
            $net .= "<line x1=\"{$lx}\" y1=\"{$gy}\" x2=\"{$lx}\" y2=\"" . ($gy + $gh) . '" stroke="#fff" stroke-width="1.6" opacity=".5"/>';
        }
        for ($i = 0; $i <= 5; $i++) {
            $ly = $gy + (int) round($i * $gh / 5);
            $net .= "<line x1=\"{$gx}\" y1=\"{$ly}\" x2=\"" . ($gx + $gw) . "\" y2=\"{$ly}\" stroke=\"#fff\" stroke-width=\"1.6\" opacity=\".5\"/>";
        }
        $out .= $net . "<rect x=\"{$gx}\" y=\"{$gy}\" width=\"{$gw}\" height=\"{$gh}\" fill=\"none\" stroke=\"#fff\" stroke-width=\"9\" stroke-linejoin=\"round\"/>";

        // توپ
        $bx = self::W - 300 + $this->rnd(-60, 60);
        $by = self::H - 130;
        $out .= $this->ball($bx, $by, 52);

        // نورافکن‌ها
        $out .= $this->floodlight(80, $horizon, $p) . $this->floodlight(self::W - 300, $horizon, $p);

        return $out;
    }

    private function ball(int $x, int $y, int $r): string
    {
        $out = "<ellipse cx=\"{$x}\" cy=\"" . ($y + $r - 4) . '" rx="' . (int) ($r * 0.95) . '" ry="9" fill="#000" opacity=".18"/>'
            . "<circle cx=\"{$x}\" cy=\"{$y}\" r=\"{$r}\" fill=\"#fff\"/>"
            . "<circle cx=\"{$x}\" cy=\"{$y}\" r=\"{$r}\" fill=\"none\" stroke=\"#d5dce8\" stroke-width=\"3\"/>";
        // پنج‌ضلعی‌های ساده
        $out .= $this->polygon($x, $y, (int) ($r * 0.42), 5, -90, '#1b2742');
        for ($k = 0; $k < 5; $k++) {
            $a = deg2rad(-90 + $k * 72);
            $px = (int) round($x + cos($a) * $r * 0.78);
            $py = (int) round($y + sin($a) * $r * 0.78);
            $out .= $this->polygon($px, $py, (int) ($r * 0.22), 5, 90 + $k * 72, '#1b2742');
        }

        return $out;
    }

    private function floodlight(int $x, int $baseY, array $p): string
    {
        $top = 118;

        return "<g><rect x=\"" . ($x - 5) . "\" y=\"{$top}\" width=\"10\" height=\"" . ($baseY - $top) . "\" fill=\"#37474f\"/>"
            . '<rect x="' . ($x - 46) . '" y="' . ($top - 34) . '" width="92" height="34" rx="8" fill="#455a64"/>'
            . '<rect x="' . ($x - 40) . '" y="' . ($top - 29) . '" width="80" height="24" rx="5" fill="#ffe08a"/>'
            . '<circle cx="' . $x . '" cy="' . ($top - 17) . '" r="52" fill="#ffe08a" opacity=".16"/></g>';
    }

    /** دنیای مکعبی: زمینِ بلوکی، درخت، معدن و ابرهای پیکسلی. */
    private function sceneBlocks(array $p): string
    {
        $out = '';
        $u = 44;                       // اندازه‌ی هر بلوک
        $groundTop = self::H - $u * 3;

        // خورشیدِ مربعی و ابرهای پیکسلی
        $out .= '<rect x="' . (self::W - 170) . '" y="70" width="76" height="76" fill="#ffe08a"/>';
        for ($i = 0; $i < 3; $i++) {
            $cx = $this->rnd(60, self::W - 260);
            $cy = $this->rnd(60, 190);
            $out .= "<g fill=\"#fff\" opacity=\".9\"><rect x=\"{$cx}\" y=\"{$cy}\" width=\"110\" height=\"26\"/>"
                . '<rect x="' . ($cx + 26) . '" y="' . ($cy - 22) . '" width="62" height="24"/></g>';
        }

        // زمین: چمن + خاک + سنگ
        for ($col = 0; $col * $u < self::W; $col++) {
            $x = $col * $u;
            $wobble = ($col % 4 === 0) ? -$u : 0;   // پله‌های کوچک برای طبیعی‌شدن
            $top = $groundTop + $wobble;
            $out .= $this->cube($x, $top, $u, '#5db85c', '#3f8f45');
            for ($row = 1; $row <= 3; $row++) {
                $y = $top + $row * $u;
                if ($y < self::H + $u) {
                    $out .= $this->cube($x, $y, $u, $row >= 3 ? '#8d8d8d' : '#9c6b4a', $row >= 3 ? '#6f6f6f' : '#79523a');
                }
            }
        }

        // درختِ بلوکی
        $tx = $this->rnd(2, 5) * $u;
        $ty = $groundTop - $u * 4;
        $out .= "<rect x=\"{$tx}\" y=\"" . ($ty + $u * 2) . "\" width=\"{$u}\" height=\"" . ($u * 2) . '" fill="#7a5230"/>';
        for ($r = 0; $r < 2; $r++) {
            for ($c = -1; $c <= 1; $c++) {
                $out .= $this->cube($tx + $c * $u, $ty + $r * $u, $u, '#3fae4a', '#2f8a39');
            }
        }
        $out .= $this->cube($tx, $ty - $u, $u, '#3fae4a', '#2f8a39');

        // بلوک‌های شناور (الماس و طلا)
        $out .= $this->oreBlock(self::W - 330, 250, $u + 12, '#38e0d0');
        $out .= $this->oreBlock(self::W - 210, 340, $u, $p['accent']);

        // کلنگ
        $out .= '<g transform="translate(' . (self::W - 420) . ',180) rotate(-28)">'
            . '<rect x="-6" y="0" width="12" height="120" rx="4" fill="#8d6e63"/>'
            . '<path d="M-58,4 C-30,-22 30,-22 58,4 C30,-6 -30,-6 -58,4 Z" fill="#b0bec5" stroke="#78909c" stroke-width="3"/></g>';

        return $out;
    }

    private function cube(int $x, int $y, int $u, string $face, string $edge): string
    {
        return "<g><rect x=\"{$x}\" y=\"{$y}\" width=\"{$u}\" height=\"{$u}\" fill=\"{$face}\"/>"
            . "<rect x=\"{$x}\" y=\"{$y}\" width=\"{$u}\" height=\"{$u}\" fill=\"none\" stroke=\"{$edge}\" stroke-width=\"3\"/>"
            . '<rect x="' . ($x + 5) . '" y="' . ($y + 5) . '" width="' . (int) ($u * .28) . '" height="' . (int) ($u * .28) . '" fill="#fff" opacity=".16"/></g>';
    }

    private function oreBlock(int $x, int $y, int $u, string $gem): string
    {
        $out = $this->cube($x, $y, $u, '#8d8d8d', '#6f6f6f');
        foreach ([[.25, .3], [.62, .2], [.4, .62], [.72, .66]] as [$fx, $fy]) {
            $out .= '<rect x="' . (int) ($x + $fx * $u) . '" y="' . (int) ($y + $fy * $u) . '" width="' . (int) ($u * .18) . '" height="' . (int) ($u * .18) . "\" rx=\"3\" fill=\"{$gem}\"/>";
        }

        return $out;
    }

    /** مسابقه: جاده‌ی پرسپکتیو، خطوطِ سرعت، پرچمِ شطرنجی و ماشین. */
    private function sceneSpeed(array $p): string
    {
        $out = '';
        $horizon = 230;

        // خورشیدِ نواری (رِترو)
        $sx = self::W / 2;
        $out .= "<circle cx=\"{$sx}\" cy=\"{$horizon}\" r=\"140\" fill=\"{$p['accent']}\" opacity=\".9\"/>";
        for ($i = 0; $i < 7; $i++) {
            $yy = $horizon - 120 + $i * 26;
            $hh = 4 + $i;
            $out .= '<rect x="' . ($sx - 150) . "\" y=\"{$yy}\" width=\"300\" height=\"{$hh}\" fill=\"{$p['sky1']}\" opacity=\".8\"/>";
        }

        // جاده
        $out .= '<path d="M' . ($sx - 46) . ",{$horizon} L" . ($sx + 46) . ",{$horizon} L" . (self::W + 240) . ',' . self::H
            . ' L' . (-240) . ',' . self::H . ' Z" fill="#2b2b3a"/>';

        // خطِ وسطِ چین‌چین با پرسپکتیو
        for ($i = 0; $i < 8; $i++) {
            $t1 = $i / 8;
            $t2 = ($i + 0.5) / 8;
            $y1 = (int) round($horizon + $t1 * (self::H - $horizon));
            $y2 = (int) round($horizon + $t2 * (self::H - $horizon));
            $w1 = 3 + $t1 * 22;
            $w2 = 3 + $t2 * 22;
            $out .= '<path d="M' . ($sx - $w1) . ",{$y1} L" . ($sx + $w1) . ",{$y1} L" . ($sx + $w2) . ",{$y2} L" . ($sx - $w2) . ",{$y2} Z\" fill=\"#ffe9a8\" opacity=\".9\"/>";
        }
        // حاشیه‌های جاده
        $out .= '<path d="M' . ($sx - 50) . ",{$horizon} L" . (-244) . ',' . self::H . ' L' . (-210) . ',' . self::H . ' L' . ($sx - 44) . ",{$horizon} Z\" fill=\"#fff\" opacity=\".75\"/>"
            . '<path d="M' . ($sx + 50) . ",{$horizon} L" . (self::W + 244) . ',' . self::H . ' L' . (self::W + 210) . ',' . self::H . ' L' . ($sx + 44) . ",{$horizon} Z\" fill=\"#fff\" opacity=\".75\"/>";

        // خطوطِ سرعت
        for ($i = 0; $i < 14; $i++) {
            $y = $this->rnd(60, self::H - 40);
            $x = $this->rnd(0, self::W - 200);
            $w = $this->rnd(60, 190);
            $o = $this->rnd(10, 30) / 100;
            $out .= "<rect x=\"{$x}\" y=\"{$y}\" width=\"{$w}\" height=\"5\" rx=\"3\" fill=\"#fff\" opacity=\"{$o}\"/>";
        }

        // پرچمِ شطرنجی
        $out .= $this->checkerFlag(110, 120);

        // ماشین
        $out .= $this->raceCar((int) $sx + $this->rnd(-40, 40), self::H - 110, $p);

        return $out;
    }

    private function checkerFlag(int $x, int $y): string
    {
        $out = '<g transform="translate(' . $x . ',' . $y . ')">'
            . '<rect x="-7" y="-10" width="11" height="300" rx="5" fill="#cfd8dc"/>';
        $c = 8;
        $u = 21;
        for ($r = 0; $r < 5; $r++) {
            for ($k = 0; $k < $c; $k++) {
                $fill = (($r + $k) % 2 === 0) ? '#ffffff' : '#1b2027';
                $skew = (int) round(sin(($k / $c) * 3.14159) * 9);
                $out .= '<rect x="' . (6 + $k * $u) . '" y="' . (-6 + $r * $u + $skew) . "\" width=\"{$u}\" height=\"{$u}\" fill=\"{$fill}\"/>";
            }
        }

        return $out . '</g>';
    }

    private function raceCar(int $x, int $y, array $p): string
    {
        return "<g transform=\"translate({$x},{$y})\">"
            . '<ellipse cx="0" cy="56" rx="130" ry="16" fill="#000" opacity=".28"/>'
            . "<path d=\"M-130,36 L-108,-4 C-96,-22 -60,-30 0,-30 C60,-30 96,-22 108,-4 L130,36 Z\" fill=\"{$p['soft']}\"/>"
            . '<path d="M-62,-6 C-50,-22 50,-22 62,-6 Z" fill="#bfe6ff" opacity=".9"/>'
            . "<rect x=\"-130\" y=\"30\" width=\"260\" height=\"16\" rx=\"8\" fill=\"{$p['accent']}\"/>"
            . '<circle cx="-82" cy="48" r="26" fill="#1b2027"/><circle cx="-82" cy="48" r="11" fill="#cfd8dc"/>'
            . '<circle cx="82" cy="48" r="26" fill="#1b2027"/><circle cx="82" cy="48" r="11" fill="#cfd8dc"/>'
            . '<path d="M-150,10 L-104,10" stroke="#fff" stroke-width="6" stroke-linecap="round" opacity=".8"/>'
            . '<path d="M-168,26 L-116,26" stroke="#fff" stroke-width="5" stroke-linecap="round" opacity=".55"/>'
            . '</g>';
    }

    /** کلاسیک: دفترِ خط‌دار، مداد، خط‌کش، سیب و کتابِ باز. */
    private function sceneClassic(array $p): string
    {
        $out = '';

        // کاغذِ خط‌دار
        for ($y = 70; $y < self::H - 40; $y += 34) {
            $out .= '<line x1="60" y1="' . $y . '" x2="' . (self::W - 60) . '" y2="' . $y . "\" stroke=\"{$p['accent']}\" stroke-width=\"2\" opacity=\".16\"/>";
        }
        $out .= '<line x1="' . (self::W - 120) . '" y1="40" x2="' . (self::W - 120) . '" y2="' . (self::H - 30) . '" stroke="#e8505b" stroke-width="3" opacity=".3"/>';

        // سوراخ‌های شیرازه
        for ($i = 0; $i < 5; $i++) {
            $out .= '<circle cx="34" cy="' . (80 + $i * 100) . '" r="13" fill="#dbe4f5"/>'
                . '<circle cx="34" cy="' . (80 + $i * 100) . '" r="13" fill="none" stroke="#c2cee6" stroke-width="3"/>';
        }

        // کتابِ باز
        $bx = 170;
        $by = self::H - 215;
        $out .= "<g transform=\"translate({$bx},{$by}) rotate(-6)\">"
            . "<path d=\"M0,0 C-70,-22 -140,-14 -176,4 L-176,120 C-140,102 -70,94 0,116 Z\" fill=\"#fff\" stroke=\"{$p['soft']}\" stroke-width=\"4\"/>"
            . "<path d=\"M0,0 C70,-22 140,-14 176,4 L176,120 C140,102 70,94 0,116 Z\" fill=\"#f4f8ff\" stroke=\"{$p['soft']}\" stroke-width=\"4\"/>"
            . '<path d="M0,0 L0,116" stroke="' . $p['soft'] . '" stroke-width="4"/>';
        for ($i = 0; $i < 4; $i++) {
            $ly = 26 + $i * 20;
            $out .= '<line x1="-150" y1="' . $ly . '" x2="-24" y2="' . ($ly + 6) . '" stroke="#c9d3e6" stroke-width="4" stroke-linecap="round"/>'
                . '<line x1="24" y1="' . ($ly + 6) . '" x2="150" y2="' . $ly . '" stroke="#c9d3e6" stroke-width="4" stroke-linecap="round"/>';
        }
        $out .= '</g>';

        // مداد
        $out .= '<g transform="translate(' . (self::W - 330) . ',' . (self::H - 330) . ') rotate(38)">'
            . '<rect x="0" y="0" width="52" height="250" fill="#f5b53f"/>'
            . '<rect x="0" y="0" width="16" height="250" fill="#e09b21"/>'
            . '<path d="M0,250 L52,250 L26,310 Z" fill="#f0d2a8"/><path d="M12,286 L40,286 L26,310 Z" fill="#333"/>'
            . '<rect x="0" y="-42" width="52" height="42" fill="#e8505b"/><rect x="0" y="-52" width="52" height="12" fill="#cfd8dc"/></g>';

        // خط‌کش
        $out .= '<g transform="translate(' . (self::W - 560) . ',' . (self::H - 120) . ') rotate(-10)">'
            . '<rect width="330" height="52" rx="8" fill="#bfe6ff" stroke="#7aa2f7" stroke-width="3"/>';
        for ($i = 1; $i < 16; $i++) {
            $h = $i % 5 === 0 ? 24 : 13;
            $out .= '<line x1="' . ($i * 20) . '" y1="0" x2="' . ($i * 20) . '" y2="' . $h . '" stroke="#3d7bf0" stroke-width="3"/>';
        }
        $out .= '</g>';

        // سیب
        $ax = self::W - 180;
        $ay = 150;
        $out .= "<g><path d=\"M{$ax},"
            . ($ay - 46) . ' C' . ($ax - 62) . ',' . ($ay - 58) . ' ' . ($ax - 74) . ',' . ($ay + 34) . " {$ax}," . ($ay + 60)
            . ' C' . ($ax + 74) . ',' . ($ay + 34) . ' ' . ($ax + 62) . ',' . ($ay - 58) . " {$ax}," . ($ay - 46) . ' Z" fill="#e8505b"/>'
            . '<rect x="' . ($ax - 4) . '" y="' . ($ay - 74) . '" width="8" height="30" rx="4" fill="#7a5230"/>'
            . '<path d="M' . ($ax + 4) . ',' . ($ay - 62) . ' C' . ($ax + 40) . ',' . ($ay - 88) . ' ' . ($ax + 46) . ',' . ($ay - 52) . ' ' . ($ax + 6) . ',' . ($ay - 50) . ' Z" fill="#3fae4a"/>'
            . '<ellipse cx="' . ($ax - 22) . '" cy="' . ($ay - 8) . '" rx="12" ry="18" fill="#fff" opacity=".3"/></g>';

        // ستاره‌های تشویقی
        for ($i = 0; $i < 5; $i++) {
            $out .= $this->sparkle($this->rnd(120, self::W - 200), $this->rnd(60, self::H - 120), $this->rnd(8, 15), $p['accent']);
        }

        return $out;
    }

    /* ═══════════════════════════ ابزارها ═══════════════════════════ */

    private function sparkle(int $x, int $y, int $s, string $color): string
    {
        $l = $s * 3;

        return "<path d=\"M{$x}," . ($y - $l) . " C{$x}," . ($y - $s) . ' ' . ($x - $s) . ",{$y} " . ($x - $l) . ",{$y} "
            . "C" . ($x - $s) . ",{$y} {$x}," . ($y + $s) . " {$x}," . ($y + $l) . ' '
            . "C{$x}," . ($y + $s) . ' ' . ($x + $s) . ",{$y} " . ($x + $l) . ",{$y} "
            . "C" . ($x + $s) . ",{$y} {$x}," . ($y - $s) . " {$x}," . ($y - $l) . " Z\" fill=\"{$color}\" opacity=\".92\"/>";
    }

    private function cloud(int $x, int $y, float $s): string
    {
        return '<g transform="translate(' . $x . ',' . $y . ') scale(' . round($s, 2) . ')" fill="#fff" opacity=".88">'
            . '<ellipse cx="0" cy="0" rx="52" ry="30"/><ellipse cx="42" cy="8" rx="40" ry="24"/><ellipse cx="-40" cy="10" rx="36" ry="22"/></g>';
    }

    private function polygon(int $cx, int $cy, int $r, int $n, float $rotDeg, string $fill): string
    {
        $pts = [];
        for ($i = 0; $i < $n; $i++) {
            $a = deg2rad($rotDeg + $i * (360 / $n));
            $pts[] = round($cx + cos($a) * $r, 1) . ',' . round($cy + sin($a) * $r, 1);
        }

        return '<polygon points="' . implode(' ', $pts) . "\" fill=\"{$fill}\"/>";
    }

    /** عددِ شبه‌تصادفیِ بذردار (xorshift) — بازتولیدپذیر و مستقل از mt_rand سراسری. */
    private function rnd(int $min, int $max): int
    {
        $x = $this->seed;
        $x ^= ($x << 13) & 0x7fffffff;
        $x ^= $x >> 17;
        $x ^= ($x << 5) & 0x7fffffff;
        $this->seed = $x ?: 1;

        return $min + ($this->seed % max(1, $max - $min + 1));
    }

    private function i(int|float $v): int
    {
        return (int) $v;
    }
}
