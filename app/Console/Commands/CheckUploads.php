<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * بررسیِ سلامتِ مسیرِ آپلود — برای اجرا روی هاست (cPanel → Terminal).
 *
 * علتِ وجود: دیسکِ public با throw=false پیکربندی شده، پس اگر پوشه‌ی مقصد
 * قابلِ نوشتن نباشد، آپلود **بی‌صدا** شکست می‌خورد. این دستور همان شکست را
 * پیش از آنکه کاربر با «عکس ذخیره نمی‌شود» روبه‌رو شود، آشکار می‌کند.
 *
 *   php artisan uploads:check
 */
class CheckUploads extends Command
{
    protected $signature = 'uploads:check {--fix : ساختِ پوشه‌های نبود و اصلاحِ دسترسی}';

    protected $description = 'بررسیِ اینکه پوشه‌ی آپلود وجود دارد و قابلِ نوشتن است';

    /** زیرپوشه‌هایی که برنامه در آن‌ها فایل می‌نویسد. */
    private array $dirs = [
        'avatars',
        'school-logos',
        'class-content',
        'worksheets',
        'worksheet-submissions',
        'bank-media',
    ];

    public function handle(): int
    {
        $root = config('filesystems.disks.public.root');
        $url  = config('filesystems.disks.public.url');

        $this->line('');
        $this->info('بررسیِ مسیرِ آپلود');
        $this->line("  پوشه‌ی ریشه : {$root}");
        $this->line("  نشانیِ وب   : {$url}");
        $this->line('');

        $problems = 0;

        // ۱) ریشه
        if (! is_dir($root)) {
            if ($this->option('fix')) {
                @mkdir($root, 0755, true);
                $this->line(is_dir($root) ? '  ✅ پوشه‌ی ریشه ساخته شد' : '  ❌ ساختِ پوشه‌ی ریشه ممکن نشد');
                $problems += is_dir($root) ? 0 : 1;
            } else {
                $this->error('  ❌ پوشه‌ی ریشه وجود ندارد. با سوییچِ --fix دوباره اجرا کنید.');
                $problems++;
            }
        } else {
            $this->line('  ✅ پوشه‌ی ریشه موجود است');
        }

        if (is_dir($root) && ! is_writable($root)) {
            $this->error('  ❌ پوشه‌ی ریشه قابلِ نوشتن نیست — دسترسی را روی ۷۵۵ تنظیم کنید.');
            $problems++;
        } elseif (is_dir($root)) {
            $this->line('  ✅ پوشه‌ی ریشه قابلِ نوشتن است');
        }

        // ۲) زیرپوشه‌ها + نوشتنِ واقعیِ آزمایشی
        $this->line('');
        foreach ($this->dirs as $dir) {
            $probe = "{$dir}/.write-test";
            $ok = false;

            try {
                $ok = Storage::disk('public')->put($probe, 'ok') && Storage::disk('public')->exists($probe);
            } catch (\Throwable $e) {
                $ok = false;
            }

            if ($ok) {
                Storage::disk('public')->delete($probe);
                $this->line(sprintf('  ✅ %-24s نوشتن موفق', $dir));
            } else {
                $this->error(sprintf('  ❌ %-24s نوشتن ناموفق', $dir));
                $problems++;
            }
        }

        // ۳) هشدارِ پیکربندی
        $this->line('');
        if (config('filesystems.disks.public.throw') === false) {
            $this->warn('  ℹ️ دیسکِ public با throw=false کار می‌کند: شکستِ نوشتن استثنا پرتاب نمی‌کند.');
            $this->line('     کنترلرها با storeImageOrFail این حالت را می‌گیرند و به کاربر پیام می‌دهند.');
        }

        $this->line('');
        if ($problems === 0) {
            $this->info('✅ همه‌چیز سالم است — آپلودِ عکس روی این سرور کار می‌کند.');
            return self::SUCCESS;
        }

        $this->error("❌ {$problems} مشکل پیدا شد. تا رفعِ آن‌ها، آپلودِ عکس روی سایت انجام نمی‌شود.");
        $this->line('   راهنمای سریع: در File Manager، دسترسیِ پوشه‌ی public_html/storage را ۷۵۵ کنید.');

        return self::FAILURE;
    }
}
