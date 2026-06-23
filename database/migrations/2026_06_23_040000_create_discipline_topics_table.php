<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * موضوعات انضباطی (قابل ساخت توسط معلم/مدرسه) با امتیاز مثبت (تشویق) یا منفی (تخلف).
 * معلم موضوع را انتخاب و به یک/چند دانش‌آموز اعمال می‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('kind', ['positive', 'negative'])->default('negative');
            $table->integer('points')->default(-5);   // منفی برای تخلف، مثبت برای تشویق
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // اتصال رکورد انضباطی به موضوع + ذخیره‌ی عنوان (snapshot)
        Schema::table('discipline_records', function (Blueprint $table) {
            $table->foreignId('topic_id')->nullable()->after('classroom_id')->constrained('discipline_topics')->nullOnDelete();
            $table->string('title')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('discipline_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('topic_id');
            $table->dropColumn('title');
        });
        Schema::dropIfExists('discipline_topics');
    }
};
