<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * طرح‌های اشتراک مدارس. ادمین کل می‌تواند طرح‌های مختلف با دسترسی‌های متفاوت بسازد.
 * مقدار null در محدودیت‌ها یعنی «نامحدود».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();                 // trial | pro | ...
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('max_classes')->nullable();            // null = نامحدود
            $table->unsignedInteger('max_students_per_class')->nullable(); // null = نامحدود
            $table->unsignedInteger('duration_days')->nullable();          // null = نامحدود (بدون انقضا)
            $table->unsignedBigInteger('price')->default(0);               // تومان
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('plan')->constrained('plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
        });
        Schema::dropIfExists('plans');
    }
};
