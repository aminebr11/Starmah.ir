<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تصحیحِ کاربرگِ ارسالی توسطِ معلم: نمره‌ی توصیفی، امتیاز، توضیح و نسخه‌ی علامت‌خورده
 * (همان عکس با تیک/ضربدر/قلمِ معلم).
 *
 * و یک‌بار: اصلاحِ دسترسیِ فایل‌های بارگذاری‌شده (پوشه ۷۵۵، فایل ۶۴۴) — بعضی فایل‌ها روی هاست
 * با دسترسیِ محدود ذخیره شده بودند و مرورگر «403 Forbidden» می‌گرفت.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('worksheet_submissions')) {
            $add = [
                'grade' => fn (Blueprint $t) => $t->string('grade', 40)->nullable(),
                'grade_xp' => fn (Blueprint $t) => $t->smallInteger('grade_xp')->default(0),
                'feedback' => fn (Blueprint $t) => $t->string('feedback', 500)->nullable(),
                'marked_path' => fn (Blueprint $t) => $t->string('marked_path')->nullable(),
                'graded_at' => fn (Blueprint $t) => $t->timestamp('graded_at')->nullable(),
                'graded_by' => fn (Blueprint $t) => $t->unsignedBigInteger('graded_by')->nullable(),
            ];
            foreach ($add as $col => $def) {
                if (! Schema::hasColumn('worksheet_submissions', $col)) {
                    Schema::table('worksheet_submissions', $def);
                }
            }
        }

        rescue(function () {
            $root = config('filesystems.disks.public.root');
            if (! $root || ! is_dir($root)) {
                return;
            }
            $t0 = microtime(true);
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
            foreach ($it as $f) {
                if (microtime(true) - $t0 > 15) {
                    break;
                }
                @chmod($f->getPathname(), $f->isDir() ? 0755 : 0644);
            }
        }, null, false);
    }

    public function down(): void
    {
        foreach (['grade', 'grade_xp', 'feedback', 'marked_path', 'graded_at', 'graded_by'] as $c) {
            if (Schema::hasColumn('worksheet_submissions', $c)) {
                Schema::table('worksheet_submissions', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
    }
};
