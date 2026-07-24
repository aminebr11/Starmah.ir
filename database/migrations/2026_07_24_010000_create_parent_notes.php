<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * پیام‌های محرمانه‌ی «بخشِ والدین» — معلم/مدیر برای والدِ یک دانش‌آموز می‌فرستد،
 * والد از داخلِ پرتالِ دانش‌آموز (پس از واردکردنِ رمزِ والدین) می‌خواند و پاسخ می‌دهد.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('parent_notes')) {
            Schema::create('parent_notes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->nullable()->index();
                $table->unsignedBigInteger('student_id')->index();
                $table->unsignedBigInteger('sender_id')->nullable(); // معلم/مدیر؛ null = پاسخِ والد
                $table->boolean('from_parent')->default(false);
                $table->string('title', 150)->nullable();
                $table->text('body');
                $table->timestamp('read_at')->nullable(); // خوانده‌شدن توسطِ سمتِ مقابل
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_notes');
    }
};
