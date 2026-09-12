<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * سرویس‌دهیِ پشتیبانِ فایل‌های آپلودی روی مسیرِ /storage.
 *
 * ── چرا لازم است ──────────────────────────────────────────────────────
 * فایل‌های آپلودی (عکسِ دانش‌آموز، لوگوی مدرسه، محتوای کلاس، کاربرگ) با
 * آدرسِ «/storage/...» صدا زده می‌شوند. اگر آن پوشه دقیقاً زیرِ ریشه‌ی
 * وب‌سرور نباشد — که در چیدمانِ رایجِ cPanel با پوشه‌ی جدا برای برنامه و
 * public_html همین‌طور است — وب‌سرور فایل را پیدا نمی‌کند و همه‌ی عکس‌ها
 * ۴۰۴ می‌شوند، بی‌آنکه در کد چیزی خراب باشد.
 *
 * این مسیر فقط وقتی اجرا می‌شود که وب‌سرور خودش فایل را پیدا نکرده باشد
 * (وگرنه Apache/LiteSpeed مستقیم همان فایل را می‌دهد و اصلاً به لاراول
 * نمی‌رسد). پس در حالتِ درست هیچ هزینه‌ای ندارد و فقط تورِ ایمنی است.
 *
 * ── محدوده‌ی امنیت ────────────────────────────────────────────────────
 * فقط از همان دیسکِ «public» می‌خواند که ذاتاً عمومی است، مسیرِ صعودی
 * (..) را رد می‌کند و فایل‌های اجرایی را هرگز تحویل نمی‌دهد.
 */
class PublicFileController extends Controller
{
    /** پسوندهایی که هرگز نباید تحویل داده شوند، حتی اگر روی دیسک باشند. */
    private const BLOCKED = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'phar',
        'htaccess', 'htpasswd', 'ini', 'sh', 'bash', 'exe', 'bat', 'cmd', 'cgi', 'pl',
    ];

    public function __invoke(Request $request, string $path): Response
    {
        $path = ltrim($path, '/');

        // صعود به پوشه‌ی بالاتر، بک‌اسلش و بایتِ تهی → رد
        if ($path === ''
            || str_contains($path, '..')
            || str_contains($path, '\\')
            || str_contains($path, "\0")) {
            abort(404);
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === '' || in_array($ext, self::BLOCKED, true)) {
            abort(404);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            abort(404);
        }

        return $disk->response($path, null, [
            // فایل‌های آپلودی نامِ تصادفی دارند و عوض نمی‌شوند، پس کشِ طولانی امن است
            'Cache-Control' => 'public, max-age=31536000',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
