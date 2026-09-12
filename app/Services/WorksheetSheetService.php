<?php

namespace App\Services;

use App\Models\Worksheet;
use Illuminate\Support\Facades\Storage;

/**
 * ساختِ «برگه»ی کاربرگ — یک سندِ یکپارچه‌ی قابلِ چاپ.
 *
 * ── چه چیزی عوض شد ────────────────────────────────────────────────────
 * پیش از این تصویرِ کاربرگ یک بلوکِ جدا بالای صفحه بود و سؤال‌ها در یک
 * بلوکِ دیگر زیرش می‌آمدند؛ یعنی عملاً دو تکه‌ی بی‌ربط که هنگامِ چاپ هم
 * از هم جدا می‌افتادند. حالا تصویر **سرلوحه‌ی خودِ برگه** است و سؤال‌ها
 * داخلِ همان برگه می‌نشینند.
 *
 * رندر «زنده» انجام می‌شود (نه فقط خواندنِ render_htmlِ ذخیره‌شده) تا
 * کاربرگ‌هایی که پیش‌تر ساخته شده‌اند هم همین چیدمانِ یکپارچه را بگیرند.
 */
class WorksheetSheetService
{
    private const THEMES = [
        'stars'   => ['label' => 'ستاره ماه 🌙', 'flavor' => 'ماجراجویی فضایی و ستاره‌ها', 'p1' => '#3d7bf0', 'p2' => '#7a5cf0', 'bg' => '#0d1b3e', 'ic' => ['⭐', '🌙', '✨', '☄️', '🚀']],
        'pitch'   => ['label' => 'شلیک آتشین ⚽', 'flavor' => 'فوتبال و ورزش', 'p1' => '#2bb673', 'p2' => '#0f9d58', 'bg' => '#0b3d2e', 'ic' => ['⚽', '🥅', '🏆', '👟', '🔥']],
        'blocks'  => ['label' => 'کریپر ماینکرفت 🟩', 'flavor' => 'دنیای مکعبی و ساخت‌وساز', 'p1' => '#4caf50', 'p2' => '#2e7d32', 'bg' => '#1b3a1e', 'ic' => ['🟩', '⛏️', '💎', '🧱', '🌳']],
        'speed'   => ['label' => 'سوپر اسپید 🏎️', 'flavor' => 'مسابقه و سرعت', 'p1' => '#e8862e', 'p2' => '#d64545', 'bg' => '#3a1c0b', 'ic' => ['🏎️', '🏁', '💨', '🔥', '🏆']],
        'classic' => ['label' => 'کلاسیک 📘', 'flavor' => '', 'p1' => '#3d7bf0', 'p2' => '#2555c0', 'bg' => '#16264f', 'ic' => ['📘', '✏️', '📐', '🍎', '⭐']],
    ];

    /** فهرستِ تم‌ها برای منوی انتخابِ معلم. */
    public function themeList(): array
    {
        return collect(self::THEMES)->map(fn ($t, $k) => ['key' => $k, 'label' => $t['label']])->values()->all();
    }

    /** کلیدِ تمِ معتبر (هر چیزِ ناشناخته → classic). */
    public function themeKey(?string $key): string
    {
        return array_key_exists((string) $key, self::THEMES) ? (string) $key : 'classic';
    }

    /** اطلاعاتِ یک تم. */
    public function theme(?string $key): array
    {
        return self::THEMES[$this->themeKey($key)];
    }

    /**
     * برگه‌ی کاربرگ را از روی خودِ مدل می‌سازد.
     *
     * چرا زنده رندر می‌کنیم و به render_htmlِ ذخیره‌شده بسنده نمی‌کنیم:
     * کاربرگ‌هایی که پیش از این ساخته شده‌اند تصویرشان بیرونِ برگه بود و
     * سؤال‌ها زیرِ یک عکسِ جدا می‌آمدند. با رندرِ زنده، همه‌ی کاربرگ‌ها —
     * قدیمی و تازه — یک برگه‌ی یکپارچه می‌شوند.
     */
    public function sheet(Worksheet $worksheet): ?string
    {
        $questions = is_array($worksheet->questions) ? $worksheet->questions : [];
        if (empty($questions)) {
            return $worksheet->render_html;   // حالتِ بارگذاریِ فایل
        }

        return $this->renderWorksheet(
            (string) $worksheet->title,
            (string) ($worksheet->subject ?? ''),
            (string) ($worksheet->grade ?? ''),
            (string) ($worksheet->theme ?? 'classic'),
            $questions,
            $worksheet->image_path ? Storage::disk('public')->url($worksheet->image_path) : null
        );
    }

    public function renderWorksheet(string $title, string $subject, string $grade, string $themeKey, array $questions, ?string $imageUrl = null): string
    {
        $t = self::THEMES[$themeKey] ?? self::THEMES['classic'];
        $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $fa = fn ($n) => strtr((string) $n, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);

        // اگر تصویر داریم، خودِ تصویر سرلوحه‌ی کاربرگ می‌شود و شکلک‌های شناور
        // لازم نیستند؛ وگرنه همان سرلوحه‌ی رنگیِ قبلی با شکلک‌ها ساخته می‌شود.
        $floats = '';
        if (! $imageUrl) {
            $positions = [[6, 8], [90, 12], [12, 88], [88, 86], [50, 5], [50, 94], [4, 50], [95, 48]];
            foreach ($positions as $i => [$x, $y]) {
                $ic = $t['ic'][$i % count($t['ic'])];
                $floats .= "<span style=\"position:absolute;left:{$x}%;top:{$y}%;font-size:26px;opacity:.28;transform:translate(-50%,-50%)\">{$ic}</span>";
            }
        }

        $rows = '';
        foreach (array_values($questions) as $idx => $q) {
            $n = $fa($idx + 1);
            $prompt = $e($q['prompt'] ?? '');
            $type = $q['type'] ?? 'mc';
            $body = '';
            if (in_array($type, ['mc', 'tf'], true) && ! empty($q['choices'])) {
                $body .= '<div style="display:flex;flex-wrap:wrap;gap:10px 22px;margin-top:8px">';
                foreach ($q['choices'] as $ci => $c) {
                    $lbl = $e($c['value'] ?? '');
                    $letters = ['الف', 'ب', 'ج', 'د'];
                    $L = $letters[$ci] ?? ($ci + 1);
                    $body .= "<span style=\"display:inline-flex;align-items:center;gap:7px;font-size:14px;color:#1b2742\"><span style=\"display:inline-flex;width:22px;height:22px;border:2px solid {$t['p1']};border-radius:50%;flex:0 0 auto\"></span><b style=\"color:{$t['p2']}\">{$L})</b> {$lbl}</span>";
                }
                $body .= '</div>';
            } else {
                // تشریحی جای بیشتری می‌خواهد، جای‌خالی فقط یک خط.
                $lines = $type === 'desc' ? 4 : ($type === 'blank' ? 1 : 2);
                $body .= '<div style="margin-top:10px">';
                for ($li = 0; $li < $lines; $li++) {
                    $mt = $li ? 'margin-top:6px' : '';
                    $body .= "<div style=\"border-bottom:2px dotted #c9d3e6;height:22px;{$mt}\"></div>";
                }
                $body .= '</div>';
            }
            $rows .= "<div style=\"background:#fff;border:2px solid #eef2fa;border-inline-start:6px solid {$t['p1']};border-radius:14px;padding:14px 16px;margin-bottom:14px;box-shadow:0 4px 14px -10px rgba(0,0,0,.3)\">"
                . "<div style=\"display:flex;gap:10px;align-items:flex-start\">"
                . "<span style=\"flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:10px;background:linear-gradient(135deg,{$t['p1']},{$t['p2']});color:#fff;font-weight:900\">{$n}</span>"
                . "<div style=\"flex:1\"><div style=\"font-weight:800;font-size:15px;color:#1b2742;line-height:1.9\">{$prompt}</div>{$body}</div></div></div>";
        }

        $subjectLine = trim($subject . ($grade ? ' — پایه‌ی ' . $grade : ''));

        // سرلوحه: با تصویر → خودِ تصویر پس‌زمینه است و یک لایه‌ی تیره روی آن
        // می‌نشیند تا نوشته‌ها خوانا بمانند. print-color-adjust لازم است وگرنه
        // مرورگر هنگامِ چاپ پس‌زمینه را حذف می‌کند.
        $headBg = $imageUrl
            // شیبِ تیره فقط جایی پررنگ است که نوشته می‌نشیند؛ بالای بند تقریباً
            // شفاف می‌ماند تا خودِ طرح دیده شود.
            ? "background-image:linear-gradient(180deg,rgba(8,14,34,.10) 0%,rgba(8,14,34,.28) 42%,rgba(8,14,34,.78) 100%),url('" . $e($imageUrl) . "');"
                . 'background-size:cover;background-position:center;min-height:210px;'
                . 'display:flex;flex-direction:column;justify-content:flex-end;'
                . '-webkit-print-color-adjust:exact;print-color-adjust:exact;'
            : "background:linear-gradient(135deg,{$t['p1']},{$t['p2']});";
        $headPad = $imageUrl ? '110px 24px 20px' : '26px 24px';

        return '<div class="ws-paper" style="font-family:Vazirmatn,Tahoma,sans-serif;direction:rtl;max-width:820px;margin:0 auto;background:#f4f7fd;color:#1b2742;border-radius:22px;overflow:hidden;box-shadow:0 20px 50px -24px rgba(0,0,0,.5)">'
            . "<div class=\"ws-head\" style=\"position:relative;{$headBg}color:#fff;padding:{$headPad};overflow:hidden\">"
            . $floats
            . "<div style=\"position:relative\"><div style=\"font-size:12px;opacity:.9;letter-spacing:1px;text-shadow:0 1px 3px rgba(0,0,0,.5)\">کاربرگ آموزشی · ستاره ماه</div>"
            . "<div style=\"font-size:24px;font-weight:900;margin-top:4px;text-shadow:0 2px 6px rgba(0,0,0,.55)\">" . $e($title) . '</div>'
            . ($subjectLine ? "<div style=\"font-size:14px;opacity:.95;margin-top:4px;text-shadow:0 1px 4px rgba(0,0,0,.5)\">" . $e($subjectLine) . '</div>' : '')
            . '<div style="display:flex;flex-wrap:wrap;gap:10px 20px;margin-top:14px;font-size:13px;opacity:.97;text-shadow:0 1px 4px rgba(0,0,0,.5)">'
            . '<span>👤 نام: ....................</span><span>🏫 کلاس: ..............</span><span>📅 تاریخ: ..............</span>'
            . '</div></div></div>'
            . '<div class="ws-body" style="padding:22px 20px">' . $rows . '</div>'
            . "<div style=\"text-align:center;padding:14px;color:{$t['p2']};font-weight:800;font-size:13px\">🌟 آفرین! تو می‌تونی 🌟</div>"
            . '</div>';
    }
}
