<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ذخیره‌ی امنِ فایلِ آپلودی بدونِ نیاز به افزونه‌ی php_fileinfo.
 * (متد پیش‌فرضِ store()/hashName() و قاعده‌ی mimes به fileinfo نیاز دارند؛ روی برخی
 * هاست‌های cPanel این افزونه غیرفعال است و باعثِ خطای «Unable to guess the MIME type» می‌شود.)
 */
trait StoresUploads
{
    /** نامِ فایل را از پسوندِ کلاینت می‌سازد (بدونِ MIME-guessing) و در دیسک ذخیره می‌کند. */
    protected function storeUpload(UploadedFile $file, string $dir, string $disk = 'public'): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $ext = preg_replace('/[^a-z0-9]+/', '', $ext) ?: 'bin';
        return $file->storeAs($dir, Str::random(40) . '.' . $ext, $disk);
    }

    /** بررسیِ پسوندِ مجاز بدونِ fileinfo (جایگزینِ قاعده‌ی mimes). */
    protected function extensionAllowed(UploadedFile $file, array $allowed): bool
    {
        return in_array(strtolower($file->getClientOriginalExtension()), $allowed, true);
    }
}
