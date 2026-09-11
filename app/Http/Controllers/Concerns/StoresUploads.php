<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * ذخیره‌ی امنِ فایلِ آپلودی بدونِ نیاز به افزونه‌ی php_fileinfo.
 * (متد پیش‌فرضِ store()/hashName() و قاعده‌ی mimes به fileinfo نیاز دارند؛ روی برخی
 * هاست‌های cPanel این افزونه غیرفعال است و باعثِ خطای «Unable to guess the MIME type» می‌شود.)
 */
trait StoresUploads
{
    /** پسوندهایی که به‌عنوانِ عکس پذیرفته می‌شوند. */
    protected array $imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif'];

    /** نامِ فایل را از پسوندِ کلاینت می‌سازد (بدونِ MIME-guessing) و در دیسک ذخیره می‌کند. */
    protected function storeUpload(UploadedFile $file, string $dir, string $disk = 'public'): string|false
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

    /**
     * ذخیره‌ی عکس با خطای روشن به‌جای شکستِ خاموش.
     *
     * پیش از این، اگر پسوند پذیرفته نمی‌شد یا نوشتن روی دیسک شکست می‌خورد،
     * فایل **بی‌هیچ پیامی** دور انداخته می‌شد: فرم با موفقیت ذخیره می‌شد اما
     * عکس ذخیره نمی‌شد و کاربر دلیلش را نمی‌فهمید. (دیسکِ public با
     * throw=false پیکربندی شده، پس storeAs در شکست فقط false برمی‌گرداند.)
     *
     * @param  string  $field  نامِ فیلد برای نمایشِ خطا در فرم
     * @throws ValidationException
     */
    protected function storeImageOrFail(UploadedFile $file, string $dir, string $field = 'avatar', string $disk = 'public'): string
    {
        if (! $this->extensionAllowed($file, $this->imageExtensions)) {
            throw ValidationException::withMessages([
                $field => 'فرمتِ این فایل پشتیبانی نمی‌شود. یک عکس با فرمتِ JPG، PNG یا WebP انتخاب کنید.',
            ]);
        }

        $path = $this->storeUpload($file, $dir, $disk);

        if ($path === false || $path === '') {
            // معمولاً یعنی پوشه‌ی مقصد روی هاست قابلِ نوشتن نیست.
            throw ValidationException::withMessages([
                $field => 'ذخیره‌ی عکس روی سرور ممکن نشد. لطفاً به مدیرِ سایت اطلاع دهید (دسترسیِ نوشتن روی پوشه‌ی آپلود).',
            ]);
        }

        // تأییدِ نهایی: فایل واقعاً روی دیسک نشسته باشد.
        if (! Storage::disk($disk)->exists($path)) {
            throw ValidationException::withMessages([
                $field => 'عکس ارسال شد اما روی سرور ذخیره نشد. لطفاً دوباره تلاش کنید.',
            ]);
        }

        return $path;
    }
}
