<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** کاربرگِ چندصفحه‌ای: چند فایل (هر صفحه یک عکس/PDF) برای کاربرگِ «بارگذاریِ فایل». */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('worksheets') && ! Schema::hasColumn('worksheets', 'files')) {
            Schema::table('worksheets', function (Blueprint $t) {
                $t->json('files')->nullable()->after('file_path');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('worksheets', 'files')) {
            Schema::table('worksheets', fn (Blueprint $t) => $t->dropColumn('files'));
        }
    }
};
