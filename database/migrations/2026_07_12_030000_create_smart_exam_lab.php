<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «آزمایشگاه هوشمند آزمون» — ماژول آزمایشیِ مستقل.
 * همه‌ی جدول‌ها با پیشوند smart_؛ جدا از آزمون‌های فعلی (assignments).
 * حذف این migration هیچ اثری روی آزمون‌های قدیمی ندارد.
 */
return new class extends Migration
{
    public function up(): void
    {
        // آزمون هوشمند
        Schema::create('smart_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('description', 600)->nullable();
            $table->string('grade')->nullable();
            $table->string('subject')->nullable();
            $table->string('book')->nullable();
            $table->string('chapter')->nullable();
            $table->string('topic')->nullable();
            $table->string('goal', 300)->nullable();
            $table->string('kind')->default('practice'); // diagnostic|practice|class|formal|remedial|game
            $table->string('status')->default('draft');   // draft|review|scheduled|published|closed|archived
            $table->boolean('adaptive')->default(false);
            $table->json('rules')->nullable();             // duration, attempts, pass, show_answer, shuffle...
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['school_id', 'status']);
        });

        // مخاطبان صریح (کلاس/گروه/دانش‌آموز) — بدون first()
        Schema::create('smart_exam_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('smart_exam_id')->constrained('smart_exams')->cascadeOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->cascadeOnDelete();
            $table->foreignId('theme_id')->nullable()->constrained('themes')->cascadeOnDelete(); // گروه
            $table->foreignId('student_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // سؤال‌های یک آزمون (کپیِ نسخه‌بندی‌شده از بانک یا دستی)
        Schema::create('smart_exam_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('smart_exam_id')->constrained('smart_exams')->cascadeOnDelete();
            $table->foreignId('bank_id')->nullable();  // منبع در بانک (اگر از بانک آمده)
            $table->string('type')->default('mc');     // mc|tf|desc|blank|multi
            $table->text('prompt');
            $table->json('choices')->nullable();
            $table->json('answer')->nullable();
            $table->text('explanation')->nullable();
            $table->string('goal', 300)->nullable();
            $table->string('difficulty')->default('medium');
            $table->unsignedInteger('points')->default(1);
            $table->unsignedInteger('time_limit')->nullable();
            $table->string('topic')->nullable();
            $table->string('tags')->nullable();
            $table->string('media_path')->nullable();
            $table->string('source')->default('manual'); // manual|ai|imported|bank
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        // بانک سؤال مستقل
        Schema::create('smart_question_bank', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope')->default('teacher'); // teacher|school|shared|global
            $table->string('type')->default('mc');
            $table->text('prompt');
            $table->json('choices')->nullable();
            $table->json('answer')->nullable();
            $table->text('explanation')->nullable();
            $table->string('grade')->nullable();
            $table->string('subject')->nullable();
            $table->string('book')->nullable();
            $table->string('chapter')->nullable();
            $table->string('topic')->nullable();
            $table->string('goal', 300)->nullable();
            $table->string('difficulty')->default('medium');
            $table->unsignedInteger('points')->default(1);
            $table->unsignedInteger('time_limit')->nullable();
            $table->string('tags')->nullable();
            $table->string('media_path')->nullable();
            $table->string('source')->default('manual');
            $table->string('approval')->default('approved'); // pending|approved|rejected
            $table->unsignedInteger('used_count')->default(0);
            $table->unsignedInteger('correct_pct')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['school_id', 'subject', 'grade', 'difficulty']);
        });

        // نسخه‌بندیِ سؤال بانک (ویرایش، نسخه‌های قبلیِ آزمون را خراب نکند)
        Schema::create('smart_question_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained('smart_question_bank')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->timestamps();
        });

        // تلاش‌های دانش‌آموز (هر تلاش رکورد مستقل)
        Schema::create('smart_exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('smart_exam_id')->constrained('smart_exams')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('attempt_no')->default(1);
            $table->string('token', 64)->nullable();       // توکنِ گره‌خورده به آزمون+دانش‌آموز+تلاش
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_sec')->default(0);
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('auto_score')->default(0);
            $table->unsignedInteger('desc_score')->default(0);
            $table->unsignedInteger('max_score')->default(0);
            $table->json('progress')->nullable();           // ذخیره‌ی خودکار (ادامه‌ی آزمون)
            $table->string('status')->default('in_progress'); // in_progress|completed|needs_review
            $table->string('ip', 64)->nullable();
            $table->boolean('rewarded')->default(false);    // XP فقط یک‌بار
            $table->timestamps();
            $table->index(['smart_exam_id', 'student_id']);
        });

        // پاسخ‌های هر تلاش (سؤال‌به‌سؤال)
        Schema::create('smart_exam_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('smart_exam_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->nullable()->constrained('smart_exam_questions')->nullOnDelete();
            $table->unsignedInteger('q_index');
            $table->json('value')->nullable();
            $table->boolean('correct')->nullable();
            $table->decimal('awarded', 6, 2)->default(0);
            $table->unsignedInteger('time_spent')->nullable();
            $table->timestamps();
        });

        // رسانه‌ی سؤال‌ها
        Schema::create('smart_exam_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind');   // image|audio|video|pdf|doc
            $table->string('path');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });

        // درخواست‌های AI (آمار مصرف/خطا)
        Schema::create('smart_exam_ai_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->string('subject')->nullable();
            $table->unsignedInteger('requested')->default(0);
            $table->unsignedInteger('produced')->default(0);
            $table->boolean('ok')->default(true);
            $table->string('error')->nullable();
            $table->timestamps();
        });

        // پیشنهادهای تمرین/جبرانی
        Schema::create('smart_exam_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('smart_exam_id')->nullable()->constrained('smart_exams')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('topic')->nullable();
            $table->string('kind')->default('practice'); // practice|game
            $table->json('payload')->nullable();
            $table->string('status')->default('open');
            $table->timestamps();
        });

        // پاداش‌ها (XP) — گره‌خورده به تلاش، idempotent
        Schema::create('smart_exam_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('smart_exam_attempts')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->integer('xp')->default(0);
            $table->timestamps();
            $table->unique('attempt_id');
        });
    }

    public function down(): void
    {
        foreach ([
            'smart_exam_rewards', 'smart_exam_recommendations', 'smart_exam_ai_requests',
            'smart_exam_media', 'smart_exam_answers', 'smart_exam_attempts',
            'smart_question_versions', 'smart_question_bank', 'smart_exam_questions',
            'smart_exam_targets', 'smart_exams',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
