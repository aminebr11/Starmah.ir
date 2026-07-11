<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * برنامه‌ی کلاسی می‌تواند «همیشگی» (specific_date = null) یا محدود به یک تاریخِ خاص باشد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('schedule_entries', 'specific_date')) {
                $table->date('specific_date')->nullable()->after('day_of_week');
            }
        });
    }

    public function down(): void
    {
        Schema::table('schedule_entries', function (Blueprint $table) {
            if (Schema::hasColumn('schedule_entries', 'specific_date')) {
                $table->dropColumn('specific_date');
            }
        });
    }
};
