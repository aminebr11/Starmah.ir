<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * محتوای کلاس (مطابق وبسایت قبلی): جزوه/فایل، پادکست صوتی، گالری تصاویر، تکلیف.
 * معلم بارگذاری می‌کند و دانش‌آموزان کلاس آن را می‌بینند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->index();          // material | podcast | gallery | homework
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();       // مسیر فایل بارگذاری‌شده (دیسک public)
            $table->string('external_url')->nullable();     // یا لینک بیرونی
            $table->timestamp('due_at')->nullable();        // برای تکالیف
            $table->timestamps();

            $table->index(['school_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_contents');
    }
};
