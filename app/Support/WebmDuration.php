<?php

namespace App\Support;

/**
 * نوشتنِ «مدت» در سرآیندِ فایلِ WebM.
 *
 * ضبطِ مرورگر (MediaRecorder) مدتِ فیلم را در فایل نمی‌نویسد؛ آن‌وقت نوارِ
 * پیشرفتِ پخش‌کننده کار نمی‌کند و روی گوشی نمی‌شود جلو/عقب رفت. اینجا
 * عنصرِ Duration در بخشِ Info اضافه (یا اصلاح) می‌شود. بدونِ ffmpeg و فقط با PHP.
 */
class WebmDuration
{
    /** @return array{0:int,1:int} [مقدار، طولِ بایت] برای یک شناسه/اندازه‌ی EBML */
    private static function vint(string $buf, int $pos, bool $keepMarker): array
    {
        $first = ord($buf[$pos] ?? "\0");
        if ($first === 0) {
            throw new \RuntimeException('bad vint');
        }
        $len = 1;
        while (! ($first & (0x80 >> ($len - 1)))) {
            $len++;
        }
        $val = $keepMarker ? $first : ($first & (0xFF >> $len));
        for ($i = 1; $i < $len; $i++) {
            $val = ($val << 8) | ord($buf[$pos + $i]);
        }

        return [$val, $len];
    }

    private static function size(int $value, int $len): string
    {
        $out = '';
        for ($i = $len - 1; $i >= 0; $i--) {
            $out .= chr(($value >> (8 * $i)) & 0xFF);
        }
        $out[0] = chr(ord($out[0]) | (0x80 >> ($len - 1)));

        return $out;
    }

    public static function fix(string $path, float $ms): bool
    {
        if ($ms <= 0 || ! is_file($path)) {
            return false;
        }
        $fh = fopen($path, 'rb');
        $head = fread($fh, 65536);
        fclose($fh);
        try {
            [$id, $l] = self::vint($head, 0, true);
            if ($id !== 0x1A45DFA3) {
                return false;
            }
            [$sz, $sl] = self::vint($head, $l, false);
            $pos = $l + $sl + $sz;                           // ابتدای Segment
            [$id, $l] = self::vint($head, $pos, true);
            if ($id !== 0x18538067) {
                return false;
            }
            [, $sl] = self::vint($head, $pos + $l, false);
            $pos += $l + $sl;                                // ابتدای فرزندانِ Segment
            while ($pos < strlen($head) - 8) {
                [$id, $l] = self::vint($head, $pos, true);
                [$sz, $sl] = self::vint($head, $pos + $l, false);
                if ($id === 0x1549A966) {                    // Info
                    $dataStart = $pos + $l + $sl;
                    $info = substr($head, $dataStart, $sz);
                    $value = pack('E', $ms);
                    // اگر Duration هست، فقط مقدارش عوض شود
                    $p = 0;
                    while ($p < strlen($info)) {
                        [$cid, $cl] = self::vint($info, $p, true);
                        [$csz, $csl] = self::vint($info, $p + $cl, false);
                        if ($cid === 0x4489 && $csz === 8) {
                            $fh = fopen($path, 'r+b');
                            fseek($fh, $dataStart + $p + $cl + $csl);
                            fwrite($fh, $value);
                            fclose($fh);

                            return true;
                        }
                        $p += $cl + $csl + $csz;
                    }
                    $add = "\x44\x89\x88" . $value;
                    $newSize = $sz + strlen($add);
                    if ($newSize >= (1 << (7 * $sl)) - 1) {
                        return false;
                    }
                    $tmp = $path . '.tmp';
                    $in = fopen($path, 'rb');
                    $out = fopen($tmp, 'wb');
                    fwrite($out, substr($head, 0, $pos + $l) . self::size($newSize, $sl) . $info . $add);
                    fseek($in, $dataStart + $sz);
                    stream_copy_to_stream($in, $out);
                    fclose($in);
                    fclose($out);

                    return rename($tmp, $path);
                }
                $pos += $l + $sl + $sz;
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }
}
