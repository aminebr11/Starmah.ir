<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دروس/کتاب‌های هر پایه (مدیریت سراسری توسط ادمین کل) + مقطع مدرسه + پایه‌ی کلاس.
 * این لیست ملاک دروسِ کلاس‌ها می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_books', function (Blueprint $table) {
            $table->id();
            $table->string('level');        // دبستان | متوسطه اول | متوسطه دوم
            $table->string('grade');        // چهارم ...
            $table->string('name');         // ریاضی، فارسی، علوم ...
            $table->string('icon', 16)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['level', 'grade']);
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->string('level')->nullable()->after('city');
        });

        Schema::table('school_requests', function (Blueprint $table) {
            $table->string('level')->nullable()->after('city');
        });

        // کلاس‌ها ستون grade دارند؛ در صورت نبود، اضافه می‌شود
        if (! Schema::hasColumn('classrooms', 'grade')) {
            Schema::table('classrooms', function (Blueprint $table) {
                $table->string('grade')->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('level');
        });
        Schema::dropIfExists('curriculum_books');
    }
};
