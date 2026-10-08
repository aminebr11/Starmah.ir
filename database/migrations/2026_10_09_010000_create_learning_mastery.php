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

        // هدفِ درسیِ سؤال‌های موجودِ بانک (سؤال‌های تازه را خودِ مدل پر می‌کند).
        // با سقفِ زمان: بانکِ بزرگ روی هاست نباید نصب را از زمانِ مجاز رد کند؛
        // باقی‌مانده را بازدیدهای بعدی پر می‌کنند (RemediationService::continueBackfill).
        if (Schema::hasColumn('smart_question_bank', 'objective_id')) {
            rescue(fn () => \App\Services\LearningService::fillBankObjectives(12), null, true);
        }

        $this->create('objective_reviews', function (Blueprint $t, bool $fk) {
                $t->id();
                $this->ref($t, 'school_id', 'schools', $fk, true);
                $this->ref($t, 'student_id', 'users', $fk);
                $this->ref($t, 'objective_id', 'learning_objectives', $fk);
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

        $this->create('practice_answers', function (Blueprint $t, bool $fk) {
                $t->id();
                $this->ref($t, 'school_id', 'schools', $fk, true);
                $this->ref($t, 'student_id', 'users', $fk);
                $this->ref($t, 'objective_id', 'learning_objectives', $fk);
                $t->unsignedBigInteger('bank_id')->nullable()->index();
                $t->string('source', 20);          // mission|review|smart_exam|game
                $t->unsignedBigInteger('source_id')->nullable();
                $t->boolean('correct');
                $t->boolean('first_try')->default(true);
                $t->boolean('hinted')->default(false);
                $t->timestamp('created_at')->nullable();
                $t->index(['student_id', 'objective_id', 'created_at']);
        });

        $this->create('review_completions', function (Blueprint $t, bool $fk) {
                $t->id();
                $this->ref($t, 'student_id', 'users', $fk);
                $t->date('play_date');
                $t->unsignedInteger('score')->default(0);
                $t->unsignedInteger('total')->default(0);
                $t->unsignedInteger('xp_awarded')->default(0);
                $t->timestamps();
                $t->unique(['student_id', 'play_date']);
        });
    }

    /**
     * ساختِ جدول با کلیدِ خارجی؛ اگر پایگاه‌داده‌ی قدیمیِ هاست (نوع یا موتورِ متفاوتِ جدولِ users/schools)
     * کلیدِ خارجی را نپذیرفت، همان جدول بدونِ کلیدِ خارجی (فقط با ایندکس) ساخته می‌شود.
     */
    private function create(string $table, \Closure $build): void
    {
        if (Schema::hasTable($table)) {
            return;
        }
        try {
            Schema::create($table, fn (Blueprint $t) => $build($t, true));
        } catch (\Throwable $e) {
            Schema::dropIfExists($table);
            Schema::create($table, fn (Blueprint $t) => $build($t, false));
        }
    }

    private function ref(Blueprint $t, string $col, string $on, bool $fk, bool $nullable = false): void
    {
        if ($fk) {
            $c = $t->foreignId($col);
            if ($nullable) {
                $c->nullable();
            }
            $c->constrained($on)->cascadeOnDelete();
        } else {
            $c = $t->unsignedBigInteger($col);
            if ($nullable) {
                $c->nullable();
            }
            $c->index();
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
