<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بانک کاربرگ‌ها — کاربرگِ موضوعیِ تولیدشده با هوش مصنوعی (شکل و طرحِ جذاب در قالب تصویر HTML/SVG).
 * قواعدِ دسترسی مانند بانک سؤالات: teacher|school|global. school_id nullable برای کاربرگِ سراسریِ ادمین کل.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('worksheets')) {
            return;
        }
        Schema::create('worksheets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->nullable();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope')->default('school'); // teacher|school|global
            $table->string('title', 180);
            $table->string('subject')->nullable();
            $table->string('grade')->nullable();
            $table->string('theme')->default('stars'); // stars|pitch|blocks|speed|classic
            $table->text('spec')->nullable();          // مشخصاتِ کاربرگ که معلم داده
            $table->json('questions')->nullable();      // سؤال‌های پیشنهادیِ هوش مصنوعی
            $table->longText('render_html')->nullable(); // تصویرِ HTML/SVGِ کاربرگ (قابل چاپ/دانلود)
            $table->timestamps();
            $table->index(['school_id', 'subject', 'grade']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worksheets');
    }
};
