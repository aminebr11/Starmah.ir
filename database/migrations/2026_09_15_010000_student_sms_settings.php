<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دسترسیِ پیامکِ هر دانش‌آموز — تنظیمِ معلمِ همان کلاس.
 *
 * تا امروز فقط مدیرِ مدرسه می‌توانست بگوید «کدام اعلان‌ها پیامک شوند» و
 * این تصمیم برای کلِ مدرسه یکسان بود. اما معلم است که می‌داند خانواده‌ی
 * کدام دانش‌آموز پیامک می‌خواهد، کدام شماره فعال است و برای کدام
 * دانش‌آموز باید فقط به ولی خبر داد.
 *
 *   enabled    → اصلاً پیامکِ خودکار برای این دانش‌آموز برود یا نه
 *   to_parent  → به شماره‌ی ولی
 *   to_student → به شماره‌ی خودِ دانش‌آموز
 *   events     → null یعنی «هرچه مدرسه گفت»؛ آرایه یعنی فقط همین رویدادها
 *   phone_override → اگر خانواده شماره‌ی دیگری برای پیامک داده باشد
 *
 * نبودِ ردیف = رفتارِ پیش‌فرضِ مدرسه (سازگار با نصب‌های موجود).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_sms_settings')) {
            return;
        }

        Schema::create('student_sms_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->index();
            $table->foreignId('student_id')->index();
            $table->foreignId('updated_by')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('to_parent')->default(true);
            $table->boolean('to_student')->default(false);
            $table->json('events')->nullable();
            $table->string('phone_override', 20)->nullable();
            $table->string('note', 200)->nullable();
            $table->timestamps();

            $table->unique('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_sms_settings');
    }
};
