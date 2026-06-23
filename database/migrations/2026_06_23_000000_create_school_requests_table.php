<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * درخواست ثبت‌نام مدرسه — مدیر مدرسه پر می‌کند، ادمین کل تأیید می‌کند.
 * پس از تأیید، School + حساب مدیر مدرسه ساخته می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_requests', function (Blueprint $table) {
            $table->id();
            $table->string('school_name');
            $table->string('manager_name');
            $table->string('manager_phone', 20);
            $table->string('manager_email')->nullable();
            $table->string('city')->nullable();
            $table->unsignedInteger('classes_count')->default(1);
            $table->text('note')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_requests');
    }
};
