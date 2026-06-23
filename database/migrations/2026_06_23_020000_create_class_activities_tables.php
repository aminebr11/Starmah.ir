<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فعالیت‌های کلاسی که معلم ثبت می‌کند (بازی، آزمون، تکلیف، پادکست، آزمون آنلاین...)،
 * هر کدام با امتیازِ دلخواهِ معلم. سپس معلم به دانش‌آموز/تیم امتیاز می‌دهد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['game', 'exam', 'homework', 'podcast', 'online_exam', 'custom'])->default('custom');
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('points')->default(10);     // امتیازی که معلم تعیین می‌کند
            $table->timestamp('scheduled_at')->nullable();
            $table->enum('status', ['active', 'closed'])->default('active');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        // ثبت اینکه کدام دانش‌آموز برای کدام فعالیت چه امتیازی گرفت (جلوگیری از تکرار)
        Schema::create('activity_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->integer('points');
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['class_activity_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_awards');
        Schema::dropIfExists('class_activities');
    }
};
