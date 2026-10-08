<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * هسته‌ی یادگیری: «هدفِ درسی»، شواهدِ سؤال‌به‌سؤال و مرورِ فاصله‌دار.
 *
 * - learning_objectives: فهرستِ سراسریِ هدف‌ها (پایه + درس + فصل + مبحث) — بدونِ داده‌ی دانش‌آموز.
 * - objective_reviews: زمان‌بندیِ مرورِ فاصله‌دار (جعبه‌ی لایتنر) برای هر دانش‌آموز و هر هدف.
 * - practice_answers: هر پاسخِ مأموریت/مرور یک ردیف، با «تلاشِ اول/دوم» و «راهنما» —
 *   شاهدِ دقیق‌تر برای موتورِ تسلط (MasteryService) به‌جای نمره‌ی کلِ روزِ مأموریت.
 * - review_completions: مرورِ روزانه، یک‌بار در روز.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('learning_objectives')) {
            Schema::create('learning_objectives', function (Blueprint $t) {
                $t->id();
                $t->char('key', 40)->unique();
                $t->string('level')->nullable();
                $t->string('grade')->nullable();
                $t->string('subject')->nullable();
                $t->unsignedBigInteger('chapter_id')->nullable();
                $t->string('chapter')->nullable();
                $t->string('lesson_no', 40)->nullable();
                $t->string('topic')->nullable();
                $t->string('label', 300);
                $t->timestamps();
                $t->index(['grade', 'subject']);
            });
        }

        if (Schema::hasTable('smart_question_bank') && ! Schema::hasColumn('smart_question_bank', 'objective_id')) {
            Schema::table('smart_question_bank', function (Blueprint $t) {
                $t->unsignedBigInteger('objective_id')->nullable()->index();
            });
        }

        // هدفِ درسیِ سؤال‌های موجودِ بانک (سؤال‌های تازه را خودِ مدل پر می‌کند)
        if (Schema::hasColumn('smart_question_bank', 'objective_id')) {
            DB::table('smart_question_bank')->whereNull('objective_id')->orderBy('id')
                ->chunkById(500, function ($rows) {
                    foreach ($rows as $r) {
                        DB::table('smart_question_bank')->where('id', $r->id)
                            ->update(['objective_id' => \App\Support\Objectives::idFor($r)]);
                    }
                });
        }

        if (! Schema::hasTable('objective_reviews')) {
            Schema::create('objective_reviews', function (Blueprint $t) {
                $t->id();
                $t->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
                $t->foreignId('student_id')->constrained('users')->cascadeOnDelete();
                $t->foreignId('objective_id')->constrained('learning_objectives')->cascadeOnDelete();
                $t->unsignedTinyInteger('box')->default(1);
                $t->date('due_at')->nullable();
                $t->unsignedInteger('attempts')->default(0);
                $t->unsignedInteger('correct')->default(0);
                $t->timestamp('last_seen_at')->nullable();
                $t->unsignedTinyInteger('best_level')->default(0);
                $t->timestamps();
                $t->unique(['student_id', 'objective_id']);
                $t->index(['student_id', 'due_at']);
            });
        }

        if (! Schema::hasTable('practice_answers')) {
            Schema::create('practice_answers', function (Blueprint $t) {
                $t->id();
                $t->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
                $t->foreignId('student_id')->constrained('users')->cascadeOnDelete();
                $t->foreignId('objective_id')->constrained('learning_objectives')->cascadeOnDelete();
                $t->unsignedBigInteger('bank_id')->nullable()->index();
                $t->string('source', 20);          // mission|review|smart_exam|game
                $t->unsignedBigInteger('source_id')->nullable();
                $t->boolean('correct');
                $t->boolean('first_try')->default(true);
                $t->boolean('hinted')->default(false);
                $t->timestamp('created_at')->nullable();
                $t->index(['student_id', 'objective_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('review_completions')) {
            Schema::create('review_completions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('student_id')->constrained('users')->cascadeOnDelete();
                $t->date('play_date');
                $t->unsignedInteger('score')->default(0);
                $t->unsignedInteger('total')->default(0);
                $t->unsignedInteger('xp_awarded')->default(0);
                $t->timestamps();
                $t->unique(['student_id', 'play_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('review_completions');
        Schema::dropIfExists('practice_answers');
        Schema::dropIfExists('objective_reviews');
        if (Schema::hasColumn('smart_question_bank', 'objective_id')) {
            Schema::table('smart_question_bank', fn (Blueprint $t) => $t->dropColumn('objective_id'));
        }
        Schema::dropIfExists('learning_objectives');
    }
};
