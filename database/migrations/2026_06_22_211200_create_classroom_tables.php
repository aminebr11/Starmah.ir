<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * کلاس، عضویت دانش‌آموزان، و فصل‌های رقابتی (Seasons).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');                 // «چهارم الف»
            $table->string('grade')->nullable();
            $table->string('join_code', 12)->unique(); // کد ورود دانش‌آموز
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('classroom_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->unique(['classroom_id', 'student_id']);
        });

        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');                 // «فصل پاییز»
            $table->date('starts_at');
            $table->date('ends_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seasons');
        Schema::dropIfExists('classroom_student');
        Schema::dropIfExists('classrooms');
    }
};
