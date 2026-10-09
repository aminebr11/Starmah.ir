<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * تحویلِ فایلِ صوتی/تصویریِ آپلودی با نوعِ درست (از روی پسوند، نه fileinfo).
 *
 * fileinfo صدای ضبطِ مرورگر را «video/webm» یا «application/octet-stream» تشخیص می‌دهد
 * و روی برخی هاست‌ها اصلاً نصب نیست؛ سافاری و بعضی گوشی‌ها با این نوع‌ها صدا را پخش نمی‌کنند.
 * BinaryFileResponse درخواستِ Range (۲۰۶) را هم پشتیبانی می‌کند که آیفون برای پخش لازم دارد.
 */
class MediaFile
{
    public const TYPES = [
        'webm' => 'audio/webm', 'weba' => 'audio/webm', 'ogg' => 'audio/ogg', 'oga' => 'audio/ogg', 'opus' => 'audio/ogg',
        'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'mp4' => 'audio/mp4', 'aac' => 'audio/aac', 'wav' => 'audio/wav',
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif',
        'pdf' => 'application/pdf',
    ];

    public static function type(string $path): string
    {
        return self::TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    }

    public static function isAudio(string $path): bool
    {
        return str_starts_with(self::type($path), 'audio/');
    }

    public static function response(string $path, string $name, string $disk = 'public'): BinaryFileResponse
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $res = response()->file(Storage::disk($disk)->path($path), [
            'Content-Type' => self::type($path),
            'Content-Disposition' => 'inline; filename="' . $name . ($ext ? '.' . $ext : '') . '"',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $res->headers->set('Accept-Ranges', 'bytes');

        return $res;
    }
}
