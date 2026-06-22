<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * برنامه‌ی درسی — «محتوای خنثی نسبت به تم».
 * سلسله‌مراتب: Subject → Topic → Skill → Question
 * محتوا یک‌بار ساخته می‌شود و موتور تم در زمان نمایش روکش می‌زند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            // null = محتوای سراسری پلتفرم؛ مقداردار = محتوای اختصاصی یک مدرسه
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');                 // ریاضی
            $table->string('slug');
            $table->string('grade');                // «چهارم»
            $table->string('icon', 16)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('name');                 // ضرب
            $table->string('slug');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->string('name');                 // ضرب عددهای یک‌رقمی
            $table->string('slug');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['mcq', 'numeric', 'truefalse', 'fill'])->default('mcq');
            $table->unsignedTinyInteger('difficulty')->default(1); // 1..5
            // قالب با جای‌خالی؛ مثال: «تیم {team} در {n} بازی هر بار {k} {unit} زد»
            $table->text('template');
            $table->json('variables')->nullable();  // محدوده‌ی مقادیر متغیرها {n:[2,9],k:[2,9]}
            $table->json('options')->nullable();     // گزینه‌ها (برای mcq)
            $table->string('answer_expr')->nullable(); // فرمول پاسخ صحیح: «n*k»
            $table->unsignedInteger('xp')->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('topics');
        Schema::dropIfExists('subjects');
    }
};
