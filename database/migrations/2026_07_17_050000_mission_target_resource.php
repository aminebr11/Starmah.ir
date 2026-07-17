<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * هدف‌گیریِ مأموریت بر اساسِ تیم (تم) + انتخابِ منبعِ مشخص (پادکست/کاربرگ/بازیِ خاص).
 * - missions.theme_id: تیمِ هدف (NULL = همه‌ی تیم‌ها)
 * - missions.resource_id: شناسه‌ی پادکست/کاربرگ/بازیِ مشخص (NULL = هر موردی از آن نوع)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('missions')) {
            Schema::table('missions', function (Blueprint $table) {
                if (! Schema::hasColumn('missions', 'theme_id')) {
                    $table->unsignedBigInteger('theme_id')->nullable()->after('classroom_id');
                }
                if (! Schema::hasColumn('missions', 'resource_id')) {
                    $table->unsignedBigInteger('resource_id')->nullable()->after('type');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('missions')) {
            Schema::table('missions', function (Blueprint $table) {
                foreach (['theme_id', 'resource_id'] as $c) {
                    if (Schema::hasColumn('missions', $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }
    }
};
