<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * امتیازِ دستیِ گروهی (تیمِ تم‌دار) — معلم می‌تواند به کلِ یک تیم امتیاز اضافه/کم کند.
 * مجموعِ تیم = جمعِ XP اعضا + جمعِ همین ردیف‌ها؛ و همین‌ها در «دفترِ امتیازِ گروه» دیده می‌شوند.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('team_points')) {
            Schema::create('team_points', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->nullable()->index();
                $table->unsignedBigInteger('classroom_id')->nullable()->index();
                $table->unsignedBigInteger('theme_id')->index();
                $table->integer('amount'); // مثبت = افزودن، منفی = کسر
                $table->string('reason', 200)->nullable();
                $table->unsignedBigInteger('awarded_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('team_points');
    }
};
