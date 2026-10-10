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

    /**
     * پسوندهایی که هرگز نباید روی دیسک بنشینند.
     *
     * پوشه‌ی آپلود مستقیماً توسطِ وب‌سرور سِرو می‌شود، پس اگر فایلی با پسوندِ
     * اجرایی ذخیره شود، وب‌سرور آن را **اجرا** می‌کند. این یعنی هر کسی که
     * اجازه‌ی آپلود دارد (مثلاً معلم، در بخشِ محتوای کلاس) می‌تواند روی
     * سرور کد اجرا کند. این فهرست آخرین سدِ دفاعی است و مستقل از
     * اعتبارسنجیِ هر فرم عمل می‌کند.
     */
    protected array $blockedExtensions = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'phar', 'pht',
        'htaccess', 'htpasswd', 'user.ini', 'ini',
        'sh', 'bash', 'zsh', 'cgi', 'pl', 'py', 'rb',
        'exe', 'bat', 'cmd', 'com', 'scr', 'msi', 'dll', 'jar',
        'svg',   // SVG می‌تواند اسکریپت داشته باشد و در مرورگر اجرا شود
        'html', 'htm', 'xhtml', 'shtml',
    ];

    /**
     * نامِ فایل را از پسوندِ کلاینت می‌سازد (بدونِ MIME-guessing) و در دیسک ذخیره می‌کند.
     * پسوندِ اجرایی در هر حالت رد می‌شود، حتی اگر فرم فراموش کرده باشد بررسی کند.
     */
    protected function storeUpload(UploadedFile $file, string $dir, string $disk = 'public'): string|false
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $ext = preg_replace('/[^a-z0-9]+/', '', $ext) ?: 'bin';

        if (in_array($ext, $this->blockedExtensions, true)) {
            return false;
        }

        return $file->storeAs($dir, Str::random(40) . '.' . $ext, $disk);
    }

    /** بررسیِ پسوندِ مجاز که فهرستِ ممنوع را هم در نظر می‌گیرد. */
    protected function extensionSafe(UploadedFile $file, array $allowed): bool
    {
        $ext = strtolower($file->getClientOriginalExtension());

        return $ext !== ''
            && ! in_array($ext, $this->blockedExtensions, true)
            && in_array($ext, $allowed, true);
    }

    /** نوعِ فایلِ صوتی از نظرِ مرورگر → پسوند (برای فایل‌هایی که نامشان پسوند ندارد، مثلِ برخی انتخابگرهای اندروید). */
    private const AUDIO_MIME_EXT = [
        'audio/mpeg' => 'mp3', 'audio/mp3' => 'mp3', 'audio/mpeg3' => 'mp3', 'audio/x-mpeg' => 'mp3', 'audio/x-mp3' => 'mp3',
        'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'audio/m4a' => 'm4a', 'audio/aac' => 'aac', 'audio/x-aac' => 'aac',
        'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/wave' => 'wav', 'audio/ogg' => 'ogg', 'audio/opus' => 'ogg', 'audio/webm' => 'webm',
    ];

    /** پسوندِ فایلِ صوتی (از نام، وگرنه از نوعِ اعلام‌شده)؛ null یعنی صوتی نیست. */
    protected function audioExtension(UploadedFile $file, array $allowed): ?string
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext !== '' && in_array($ext, $allowed, true)) {
            return $ext;
        }
        if ($ext !== '' && in_array($ext, $this->blockedExtensions, true)) {
            return null;
        }
        $mime = strtolower(trim(explode(';', (string) $file->getClientMimeType())[0]));
        $guess = self::AUDIO_MIME_EXT[$mime] ?? null;

        return $guess && in_array($guess, $allowed, true) ? $guess : null;
    }

    /** ذخیره‌ی فایلِ صوتی با پسوندِ درست (حتی اگر نامِ فایل پسوند نداشت). */
    protected function storeAudio(UploadedFile $file, string $dir, array $allowed, string $disk = 'public'): string|false
    {
        $ext = $this->audioExtension($file, $allowed);

        return $ext ? $file->storeAs($dir, Str::random(40) . '.' . $ext, $disk) : false;
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
