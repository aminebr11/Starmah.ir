<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * برنامه‌ی هفتگی کلاس — معلم پر می‌کند، دانش‌آموز در صفحه‌اش می‌بیند.
 * day_of_week: 0=شنبه ... 6=جمعه (مطابق تقویم ایران)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');     // 0..6
            $table->unsignedTinyInteger('period')->default(1); // زنگ چندم
            $table->string('title');                         // درس/موضوع
            $table->string('time_range')->nullable();        // «۸:۰۰ - ۹:۰۰»
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_entries');
    }
};
