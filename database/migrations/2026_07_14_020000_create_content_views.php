<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ثبتِ بازدید/گوش‌دادنِ دانش‌آموز به محتوای کلاس (پادکست/عکس/جزوه).
 * seconds: بیشترین ثانیه‌ای که گوش داده؛ xp_awarded: XPِ پرداخت‌شده تا کنون (فقط یک‌بار، متناسب با ثانیه).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('content_views')) {
            return;
        }
        Schema::create('content_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_content_id')->constrained('class_contents')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('seconds')->default(0);   // بیشترین ثانیه‌ی گوش‌داده‌شده
            $table->boolean('viewed')->default(true);
            $table->unsignedInteger('xp_awarded')->default(0);
            $table->timestamps();
            $table->unique(['class_content_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_views');
    }
};
