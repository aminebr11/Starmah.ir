<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 🖼️ شکلِ سؤال: ذخیره (SVGِ رسم‌شده، عکسِ معلم، برشِ برگه) و نشانیِ نمایش.
 *
 * در ستونِ media_path (آزمون، بازی، بانک) یا نشانیِ کامل/نسبی ذخیره می‌شود (/storage/…)
 * یا مسیرِ دیسکِ public (bank-media/…)؛ url() هر دو را به نشانیِ قابلِ نمایش تبدیل می‌کند.
 */
class QuestionMedia
{
    public const DIR = 'question-media';

    public static function url(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }
        if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, '/') || str_starts_with($path, 'data:')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    /** نشانیِ شکل که از فرم آمده: فقط فایلِ همین سایت (/storage/…) یا http(s)؛ بقیه دور ریخته می‌شود. */
    public static function safeUrl(mixed $url): ?string
    {
        $url = trim(is_string($url) ? $url : '');
        if ($url === '' || mb_strlen($url) > 500 || str_contains($url, '..') || preg_match('/[\s"<>]/', $url)) {
            return null;
        }
        $base = rtrim((string) Storage::disk('public')->url(''), '/');

        return str_starts_with($url, '/storage/') || ($base !== '' && str_starts_with($url, $base . '/')) || preg_match('#^https?://#i', $url) ? $url : null;
    }

    /** شکلِ رسم‌شده از توصیف → نشانی (یا null). */
    public static function storeFigure(mixed $spec): ?string
    {
        $svg = Figure::svg($spec);

        return $svg ? self::put($svg, 'svg') : null;
    }

    /** عکسِ معلم یا برش (فقط JPEG/PNG/WebP/GIF؛ SVGِ بیرونی پذیرفته نمی‌شود). */
    public static function storeImage(string $bytes): ?string
    {
        $mime = \App\Support\Bot\BotFile::sniff($bytes, '');
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? null;
        if (! $ext || strlen($bytes) > 6 * 1024 * 1024) {
            return null;
        }
        // عکسِ بزرگِ گوشی → حداکثر ۱۴۰۰ پیکسل (اگر GD هست)
        if ($ext !== 'gif' && function_exists('imagecreatefromstring') && ($im = @imagecreatefromstring($bytes))) {
            $w = imagesx($im);
            $h = imagesy($im);
            if (max($w, $h) > 1400 || strlen($bytes) > 700 * 1024) {
                $k = min(1, 1400 / max($w, $h));
                $out = self::jpeg($im, (int) round($w * $k), (int) round($h * $k), 0, 0, $w, $h);
                if ($out) {
                    return self::put($out, 'jpg');
                }
            }
            imagedestroy($im);
        }

        return self::put($bytes, $ext);
    }

    /**
     * برشِ شکل از عکسِ برگه/صفحه‌ی کتاب.
     * $box = [ymin, xmin, ymax, xmax] در مقیاسِ ۰ تا ۱۰۰۰ (قالبِ رایجِ مدل‌های تصویرخوان).
     */
    public static function crop(string $bytes, array $box): ?string
    {
        if (count($box) !== 4 || ! function_exists('imagecreatefromstring') || ! ($im = @imagecreatefromstring($bytes))) {
            return null;
        }
        [$y0, $x0, $y1, $x1] = array_map(fn ($v) => max(0, min(1000, (float) $v)), array_values($box));
        if ($y1 < $y0) {
            [$y0, $y1] = [$y1, $y0];
        }
        if ($x1 < $x0) {
            [$x0, $x1] = [$x1, $x0];
        }
        if ($y1 - $y0 < 20 || $x1 - $x0 < 20 || ($y1 - $y0) * ($x1 - $x0) > 950_000) {
            imagedestroy($im);

            return null;   // خیلی کوچک یا تقریباً کلِ صفحه → برش معنا ندارد
        }
        $W = imagesx($im);
        $H = imagesy($im);
        $pad = 18;   // کمی حاشیه تا لبه‌ی شکل بریده نشود
        $sx = (int) max(0, floor($x0 / 1000 * $W) - $W * $pad / 1000);
        $sy = (int) max(0, floor($y0 / 1000 * $H) - $H * $pad / 1000);
        $ex = (int) min($W, ceil($x1 / 1000 * $W) + $W * $pad / 1000);
        $ey = (int) min($H, ceil($y1 / 1000 * $H) + $H * $pad / 1000);
        $cw = $ex - $sx;
        $ch = $ey - $sy;
        $k = min(1, 1000 / max($cw, $ch));
        $out = self::jpeg($im, (int) round($cw * $k), (int) round($ch * $k), $sx, $sy, $cw, $ch);

        return $out ? self::put($out, 'jpg') : null;
    }

    private static function jpeg(\GdImage $im, int $w, int $h, int $sx, int $sy, int $sw, int $sh): ?string
    {
        $dst = imagecreatetruecolor(max(1, $w), max(1, $h));
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $im, 0, 0, $sx, $sy, $w, $h, $sw, $sh);
        ob_start();
        imagejpeg($dst, null, 86);
        $out = ob_get_clean();
        imagedestroy($dst);
        imagedestroy($im);

        return $out ?: null;
    }

    private static function put(string $bytes, string $ext): ?string
    {
        $path = self::DIR . '/' . now()->format('Ym') . '/' . Str::random(32) . '.' . $ext;
        if (! Storage::disk('public')->put($path, $bytes)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
