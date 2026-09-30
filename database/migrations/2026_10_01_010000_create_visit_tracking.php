<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ثبتِ بازدید: یک ردیف برای هر بازدیدکننده در هر روز (نه هر کلیک) تا جدول سبک بماند،
 * به‌علاوه‌ی خلاصه‌ی روزانه‌ی بخش‌ها و ساعت‌ها برای نمودارها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $t) {
            $t->id();
            $t->date('date');
            $t->string('visitor', 32);                 // u{id} برای کاربر، g{hash} برای مهمان
            $t->foreignId('user_id')->nullable()->index();
            $t->unsignedBigInteger('school_id')->nullable()->index();
            $t->string('role', 20)->nullable();
            $t->unsignedInteger('hits')->default(0);   // صفحه‌های دیده‌شده
            $t->unsignedInteger('seconds')->default(0); // زمانِ فعالِ تقریبی
            $t->string('device', 10)->nullable();      // app | mobile | desktop
            $t->string('last_route', 80)->nullable();
            $t->dateTime('first_at');
            $t->dateTime('last_at')->index();
            $t->unique(['date', 'visitor']);
            $t->index(['date', 'school_id']);
        });

        Schema::create('visit_pages', function (Blueprint $t) {
            $t->id();
            $t->date('date');
            $t->string('route', 80);
            $t->string('role', 20)->default('guest');
            $t->unsignedBigInteger('school_id')->default(0);
            $t->unsignedInteger('hits')->default(0);
            $t->unique(['date', 'route', 'role', 'school_id']);
        });

        Schema::create('visit_hours', function (Blueprint $t) {
            $t->id();
            $t->date('date');
            $t->unsignedTinyInteger('hour');
            $t->unsignedBigInteger('school_id')->default(0);
            $t->unsignedInteger('hits')->default(0);
            $t->unique(['date', 'hour', 'school_id']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->dateTime('last_seen_at')->nullable()->index();
            $t->unsignedInteger('login_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
        Schema::dropIfExists('visit_pages');
        Schema::dropIfExists('visit_hours');
        Schema::table('users', function (Blueprint $t) {
            $t->dropIndex(['last_seen_at']);
            $t->dropColumn(['last_seen_at', 'login_count']);
        });
    }
};
