<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دوره‌ی اجرای مأموریت.
 *
 * تا امروز هر مأموریتِ فعال «هر روز، برای همیشه» تکرار می‌شد و معلم برای
 * یک چالشِ یک‌روزه مجبور بود فردایش دستی خاموشش کند.
 *
 *   repeat = 'daily' → از starts_on تا ends_on هر روز (هر دو می‌توانند خالی
 *                      باشند: یعنی از همین حالا، بی‌پایان — رفتارِ قبلی)
 *   repeat = 'once'  → فقط در همان یک روزِ starts_on
 *
 * مأموریت‌های موجود بدونِ تاریخ می‌مانند، پس دقیقاً مثلِ قبل کار می‌کنند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            if (! Schema::hasColumn('missions', 'repeat_mode')) {
                $table->string('repeat_mode', 10)->default('daily')->after('is_active');
            }
            if (! Schema::hasColumn('missions', 'starts_on')) {
                $table->date('starts_on')->nullable()->after('repeat_mode');
            }
            if (! Schema::hasColumn('missions', 'ends_on')) {
                $table->date('ends_on')->nullable()->after('starts_on');
            }
        });
    }

    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            foreach (['repeat_mode', 'starts_on', 'ends_on'] as $c) {
                if (Schema::hasColumn('missions', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
