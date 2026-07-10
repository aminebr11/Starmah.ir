<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** افزودن ساعت شروع/پایان و نوع (درس/زنگ تفریح) به برنامه‌ی کلاسی. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_entries', function (Blueprint $table) {
            $table->string('start_time', 5)->nullable()->after('period'); // «08:00»
            $table->string('end_time', 5)->nullable()->after('start_time');
            $table->string('kind', 10)->default('class')->after('end_time'); // class | recess
        });
    }

    public function down(): void
    {
        Schema::table('schedule_entries', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time', 'kind']);
        });
    }
};
