<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دفتر کلاسی (دفتر نمره) — ستون‌های نمره + نمره‌ی هر دانش‌آموز.
 * هر ستون می‌تواند عددی یا توصیفی باشد (مطابق سایت قبلی).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');                         // «آزمون ریاضی مهر»
            $table->enum('type', ['numeric', 'descriptive'])->default('numeric');
            $table->decimal('max', 5, 2)->default(20);       // بارم (برای عددی)
            $table->date('graded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_column_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('score', 5, 2)->nullable();      // نمره‌ی عددی
            $table->string('text')->nullable();              // نمره‌ی توصیفی
            $table->timestamps();
            $table->unique(['grade_column_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
        Schema::dropIfExists('grade_columns');
    }
};
