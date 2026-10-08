<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * طرحِ ظاهریِ هر مدرسه (clay = خمیرماه، classic = طرحِ قدیمی).
 * خالی یعنی پیش‌فرضِ سامانه (خمیرماه). فقط ادمینِ کل تعیینش می‌کند.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('schools', 'ui')) {
            Schema::table('schools', function (Blueprint $t) {
                $t->string('ui', 20)->nullable()->after('level');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('schools', 'ui')) {
            Schema::table('schools', fn (Blueprint $t) => $t->dropColumn('ui'));
        }
    }
};
