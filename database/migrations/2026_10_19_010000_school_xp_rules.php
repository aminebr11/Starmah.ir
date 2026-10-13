<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «⚡ مدیریتِ امتیازها»: قاعده‌های XP هر مدرسه.
 * schools.xp_rules (JSON) فقط تفاوت‌ها با پیش‌فرض را نگه می‌دارد؛ خالی = رفتارِ پیش‌فرضِ سیستم.
 * بدونِ کلیدِ خارجی تا روی پایگاه‌داده‌ی قدیمیِ هاست هم ساخته شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('schools') && ! Schema::hasColumn('schools', 'xp_rules')) {
            Schema::table('schools', function (Blueprint $t) {
                $t->text('xp_rules')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('schools') && Schema::hasColumn('schools', 'xp_rules')) {
            Schema::table('schools', fn (Blueprint $t) => $t->dropColumn('xp_rules'));
        }
    }
};
