<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** لینکِ مقصد برای اعلان — کلیکِ دانش‌آموز روی اعلان مستقیم به همان صفحه (مثلاً آزمون/کاربرگ) می‌رود. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('announcements') && ! Schema::hasColumn('announcements', 'link')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->string('link')->nullable()->after('body');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('announcements', 'link')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->dropColumn('link');
            });
        }
    }
};
