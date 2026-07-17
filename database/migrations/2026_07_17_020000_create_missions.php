<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مأموریت‌های روزانه‌ی معلم‌محور: معلم تعریف می‌کند سؤال‌ها از کدام درس/شماره‌درسِ بانکِ سؤالش
 * بیایند، چند سؤال، چه امتیازی و کدام نشان. دانش‌آموز هر روز می‌تواند هر مأموریت را یک‌بار انجام دهد.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('missions')) {
            Schema::create('missions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->nullable()->index();
                $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('classroom_id')->nullable()->index();
                $table->string('title');
                $table->string('subject')->nullable();
                $table->string('lesson_no', 40)->nullable();
                $table->string('difficulty', 20)->nullable();
                $table->unsignedSmallInteger('question_count')->default(5);
                $table->unsignedInteger('xp_reward')->default(20);
                $table->string('badge_name')->nullable();
                $table->string('badge_icon', 16)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mission_completions')) {
            Schema::create('mission_completions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mission_id')->constrained('missions')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
                $table->date('play_date');
                $table->unsignedSmallInteger('score')->default(0);
                $table->unsignedSmallInteger('total')->default(0);
                $table->unsignedInteger('xp_awarded')->default(0);
                $table->timestamps();
                $table->unique(['mission_id', 'student_id', 'play_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_completions');
        Schema::dropIfExists('missions');
    }
};
