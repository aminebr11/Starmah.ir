<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ماژول «دنیای بازی‌های آموزشی» — جدا از تم‌ها (themes).
 * - game_templates: مکانیک بازی (مار و پله، فوتبال، گنج‌یابی، ...)
 * - edu_games: بازیِ ساخته‌شده توسط معلم (قالب + تم + درس/پایه + قوانین)
 * - edu_game_questions: سؤال‌های بازی (چندنوعی + رسانه)
 * - edu_game_targets: گروه‌ها/دانش‌آموزانِ مجاز
 * - edu_game_attempts: تلاش/پیشرفت دانش‌آموز (قابل ادامه)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();          // snake | football | treasure | ...
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('icon', 16)->nullable();
            $table->json('config')->nullable();        // تنظیمات پیش‌فرضِ مکانیک
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('edu_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('template_key')->index();
            $table->foreignId('theme_id')->nullable()->constrained('themes')->nullOnDelete();
            $table->string('title');
            $table->string('description', 500)->nullable();
            $table->string('subject')->nullable();
            $table->string('grade')->nullable();
            $table->string('difficulty')->default('medium'); // easy|medium|hard
            $table->string('cover_path')->nullable();
            $table->string('status')->default('draft');       // draft|published|archived|disabled
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('close_at')->nullable();
            $table->json('rules')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['school_id', 'status']);
        });

        Schema::create('edu_game_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edu_game_id')->constrained('edu_games')->cascadeOnDelete();
            $table->string('type')->default('mc');   // mc|tf|blank|short|multi|order|image|...
            $table->text('prompt');
            $table->string('media_path')->nullable();
            $table->json('choices')->nullable();     // [{value, correct, image?}]
            $table->json('answer')->nullable();      // پاسخِ متنی/چندگانه
            $table->string('hint1')->nullable();
            $table->string('hint2')->nullable();
            $table->text('explanation')->nullable(); // توضیح آموزشی پس از پاسخ
            $table->unsignedInteger('points')->default(10);
            $table->unsignedInteger('penalty')->default(0);
            $table->unsignedInteger('time_limit')->nullable();
            $table->string('tags')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('edu_game_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edu_game_id')->constrained('edu_games')->cascadeOnDelete();
            $table->foreignId('theme_id')->nullable()->constrained('themes')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('edu_game_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edu_game_id')->constrained('edu_games')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('max_score')->default(0);
            $table->json('progress')->nullable();     // {step, answers, correct}
            $table->string('status')->default('in_progress'); // in_progress|completed
            $table->unsignedInteger('hints_used')->default(0);
            $table->unsignedInteger('duration_sec')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['edu_game_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_game_attempts');
        Schema::dropIfExists('edu_game_targets');
        Schema::dropIfExists('edu_game_questions');
        Schema::dropIfExists('edu_games');
        Schema::dropIfExists('game_templates');
    }
};
