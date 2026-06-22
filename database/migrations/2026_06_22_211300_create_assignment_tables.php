<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تکالیف و آزمون‌ها که معلم به کلاس اختصاص می‌دهد + پاسخ دانش‌آموزان.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->enum('type', ['homework', 'quiz', 'exam', 'practice'])->default('practice');
            $table->json('skill_ids')->nullable();  // دامنه‌ی مهارت‌های هدف
            $table->unsignedInteger('question_count')->default(10);
            $table->timestamp('due_at')->nullable();
            $table->json('config')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('max_score')->default(0);
            $table->decimal('accuracy', 5, 2)->default(0);
            $table->json('answers')->nullable();
            $table->enum('status', ['assigned', 'in_progress', 'completed', 'graded'])->default('assigned');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['assignment_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('assignments');
    }
};
