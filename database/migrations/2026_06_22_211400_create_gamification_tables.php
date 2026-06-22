<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * گیمیفیکیشن و پیشرفت: نتایج فعالیت، دفتر XP، تسلط مهارت، نشان‌ها.
 * XP در «جدول دفترکل» (ledger) ثبت می‌شود تا مجموع همیشه قابل بازسازی باشد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('skill_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('theme_id')->nullable();
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('max_score')->default(0);
            $table->decimal('accuracy', 5, 2)->default(0);
            $table->unsignedInteger('time_spent')->nullable(); // ثانیه
            $table->timestamps();
            $table->index(['student_id', 'created_at']);
        });

        Schema::create('xp_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('season_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('amount');                  // می‌تواند منفی هم باشد
            $table->string('reason')->nullable();
            $table->nullableMorphs('source');           // منبع: activity_result / assignment / دستی
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['student_id', 'season_id']);
        });

        Schema::create('student_skill_mastery', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('mastery')->default(0); // 0..100
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_practiced_at')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'skill_id']);
        });

        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('emoji', 16)->nullable();
            $table->string('description')->nullable();
            $table->json('criteria')->nullable();
            $table->timestamps();
        });

        Schema::create('student_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->timestamp('awarded_at')->useCurrent();
            $table->unique(['student_id', 'badge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_badges');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('student_skill_mastery');
        Schema::dropIfExists('xp_ledger');
        Schema::dropIfExists('activity_results');
    }
};
