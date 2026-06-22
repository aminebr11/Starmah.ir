<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * انضباط (دفتر ستاره/تذکر)، رابطه‌ی والد-دانش‌آموز، و پیام‌رسانی دوسویه‌ی خانه و مدرسه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['star', 'warning', 'note'])->default('star');
            $table->integer('points')->default(0);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'created_at']);
        });

        // والدین به دانش‌آموزان (چند به چند: یک والد چند فرزند، یک دانش‌آموز پدر و مادر)
        Schema::create('parent_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('relation')->nullable(); // پدر / مادر
            $table->unique(['parent_id', 'student_id']);
        });

        // پل والدین: گفت‌وگوی دوسویه‌ی معلم و والد حول یک دانش‌آموز
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete(); // موضوع گفت‌وگو
            $table->enum('kind', ['message', 'encouragement', 'announcement'])->default('message');
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['recipient_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('parent_student');
        Schema::dropIfExists('discipline_records');
    }
};
