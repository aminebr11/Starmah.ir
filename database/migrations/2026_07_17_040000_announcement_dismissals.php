<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** اعلان‌هایی که هر کاربر برای خودش پنهان/حذف کرده (بدونِ حذفِ اصلِ اعلان برای دیگران). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('announcement_dismissals')) {
            Schema::create('announcement_dismissals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['user_id', 'announcement_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_dismissals');
    }
};
