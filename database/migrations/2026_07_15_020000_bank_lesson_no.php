<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** شماره درس (دستی) روی بانک سؤالات — بخشی از دسته‌بندی مقطع→کلاس→درس→شماره درس. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('smart_question_bank') && ! Schema::hasColumn('smart_question_bank', 'lesson_no')) {
            Schema::table('smart_question_bank', function (Blueprint $table) {
                $table->string('lesson_no', 40)->nullable()->after('subject');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('smart_question_bank', 'lesson_no')) {
            Schema::table('smart_question_bank', function (Blueprint $table) {
                $table->dropColumn('lesson_no');
            });
        }
    }
};
