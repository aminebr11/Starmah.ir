<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * هسته‌ی یادگیری — فاز ۲.
 *
 * - فصلِ هر سؤال (آزمون/بازی) و فصلِ هر ستونِ دفترِ نمره: رصدِ یادگیری بر پایه‌ی
 *   «فصلِ درست»، نه فقط فصلِ کلِ آزمون یا متنِ آزادِ مبحث.
 * - remediations: «یادآوریِ جبرانی» — برای هر سؤالی که دانش‌آموز اشتباه زده،
 *   سه نوبتِ فاصله‌دار با همان سؤال و سؤال‌های مشابه، و جبرانِ بخشی از امتیاز.
 *   فقط در حسابِ همان دانش‌آموز؛ معلم وضعیت را در گزارش‌ها می‌بیند.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['smart_exam_questions', 'edu_game_questions', 'grade_columns'] as $t) {
            if (Schema::hasTable($t) && ! Schema::hasColumn($t, 'chapter_id')) {
                Schema::table($t, fn (Blueprint $table) => $table->unsignedBigInteger('chapter_id')->nullable());
                rescue(fn () => Schema::table($t, fn (Blueprint $table) => $table->index('chapter_id')), null, false);
            }
        }

        if (! Schema::hasTable('remediations')) {
            Schema::create('remediations', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('school_id')->nullable()->index();
                $t->unsignedBigInteger('student_id');
                $t->unsignedBigInteger('teacher_id')->nullable()->index();
                $t->string('source', 20);                    // exam | game | mission
                $t->unsignedBigInteger('source_id');         // تلاشِ آزمون/بازی یا تکمیلِ مأموریت
                $t->unsignedBigInteger('source_ref')->nullable(); // شناسه‌ی آزمون/بازی/مأموریت
                $t->string('source_title', 200)->nullable();
                $t->string('q_key', 40);                     // «b۱۲» (بانک) یا «e۵۵» (سؤالِ بدونِ بانک)
                $t->unsignedBigInteger('bank_id')->nullable()->index();
                $t->unsignedBigInteger('objective_id')->nullable()->index();
                $t->string('objective_label', 300)->nullable();
                $t->json('question');                        // نسخه‌ی سؤال (برای وقتی در بانک نیست)
                $t->unsignedInteger('lost_xp')->default(0);
                $t->unsignedInteger('cap_xp')->default(0);   // سقفِ جبران (۵۰٪ امتیازِ از دست رفته)
                $t->unsignedInteger('recovered_xp')->default(0);
                $t->unsignedTinyInteger('step')->default(0); // نوبت‌های قبول‌شده
                $t->unsignedTinyInteger('steps')->default(3);
                $t->unsignedTinyInteger('tries')->default(0); // دفعاتِ انجام (قبول یا رد)
                $t->date('due_on')->nullable();
                $t->string('status', 12)->default('open');   // open | done | closed
                $t->boolean('ai_tried')->default(false);
                $t->timestamp('last_played_at')->nullable();
                $t->float('last_credit')->nullable();
                $t->timestamps();
                $t->index(['student_id', 'status', 'due_on']);
                $t->index(['source', 'source_id']);
                $t->unique(['student_id', 'source', 'source_id', 'q_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('remediations');
        foreach (['smart_exam_questions', 'edu_game_questions', 'grade_columns'] as $t) {
            if (Schema::hasColumn($t, 'chapter_id')) {
                Schema::table($t, fn (Blueprint $table) => $table->dropColumn('chapter_id'));
            }
        }
    }
};
