<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دفتر کلاسی فعالیت‌محور (مطابق طرح قدیم): درس + موضوع + نوع نمره (عددی/توصیفی/تکلیف) + بازخورد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grade_columns', function (Blueprint $table) {
            if (! Schema::hasColumn('grade_columns', 'score_type')) {
                $table->string('score_type')->default('numeric')->after('type'); // numeric|descriptive|homework
            }
            if (! Schema::hasColumn('grade_columns', 'lesson')) {
                $table->string('lesson')->nullable()->after('score_type');
            }
            if (! Schema::hasColumn('grade_columns', 'topic')) {
                $table->string('topic')->nullable()->after('lesson');
            }
        });

        Schema::table('grades', function (Blueprint $table) {
            if (! Schema::hasColumn('grades', 'feedback')) {
                $table->string('feedback')->nullable()->after('text');
            }
        });

        // مقداردهی اولیه‌ی score_type از روی type برای رکوردهای قبلی
        \Illuminate\Support\Facades\DB::table('grade_columns')->update([
            'score_type' => \Illuminate\Support\Facades\DB::raw('type'),
        ]);
    }

    public function down(): void
    {
        Schema::table('grade_columns', function (Blueprint $table) {
            foreach (['score_type', 'lesson', 'topic'] as $c) {
                if (Schema::hasColumn('grade_columns', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
        Schema::table('grades', function (Blueprint $table) {
            if (Schema::hasColumn('grades', 'feedback')) {
                $table->dropColumn('feedback');
            }
        });
    }
};
