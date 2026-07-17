<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سه‌حالته‌کردنِ کاربرگ (دستی/بارگذاری/هوش مصنوعی) + امتیازِ یک‌بارِ دانلود و ارسال.
 * - worksheets.file_path: فایلِ آماده‌ای که معلم بارگذاری می‌کند (حالتِ «بارگذاری»)
 * - worksheets.mode: manual|upload|ai
 * - worksheet_submissions: file_path اختیاری + ردیابیِ دانلود و امتیازها
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('worksheets')) {
            Schema::table('worksheets', function (Blueprint $table) {
                if (! Schema::hasColumn('worksheets', 'file_path')) {
                    $table->string('file_path')->nullable()->after('image_path');
                }
                if (! Schema::hasColumn('worksheets', 'mode')) {
                    $table->string('mode', 20)->default('manual')->after('theme');
                }
            });
        }

        if (Schema::hasTable('worksheet_submissions')) {
            Schema::table('worksheet_submissions', function (Blueprint $table) {
                if (! Schema::hasColumn('worksheet_submissions', 'downloaded_at')) {
                    $table->timestamp('downloaded_at')->nullable()->after('submitted_at');
                }
                if (! Schema::hasColumn('worksheet_submissions', 'download_xp')) {
                    $table->boolean('download_xp')->default(false)->after('downloaded_at');
                }
                if (! Schema::hasColumn('worksheet_submissions', 'submit_xp')) {
                    $table->boolean('submit_xp')->default(false)->after('download_xp');
                }
            });

            // file_path را اختیاری کن (چون دانلود پیش از ارسال رخ می‌دهد)
            try {
                \Illuminate\Support\Facades\DB::statement('ALTER TABLE worksheet_submissions MODIFY file_path VARCHAR(255) NULL');
            } catch (\Throwable $e) {
                // درایورهای غیرMySQL یا ستونِ از پیش‌nullable — بی‌اثر
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('worksheets')) {
            Schema::table('worksheets', function (Blueprint $table) {
                foreach (['file_path', 'mode'] as $c) {
                    if (Schema::hasColumn('worksheets', $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }
        if (Schema::hasTable('worksheet_submissions')) {
            Schema::table('worksheet_submissions', function (Blueprint $table) {
                foreach (['downloaded_at', 'download_xp', 'submit_xp'] as $c) {
                    if (Schema::hasColumn('worksheet_submissions', $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }
    }
};
