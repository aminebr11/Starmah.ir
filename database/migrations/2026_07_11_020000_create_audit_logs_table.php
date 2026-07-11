<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** سوابق تغییرات: چه کسی، چه زمانی، چه چیزی را تغییر داد (برای مدیر/ادمین). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('actor_role')->nullable();
            $table->string('action');            // مثال: «ویرایش دانش‌آموز»
            $table->string('summary', 500);      // توضیح خوانا
            $table->timestamps();
            $table->index(['school_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
