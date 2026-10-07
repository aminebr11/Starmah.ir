<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «نوبتِ امتیازدهی» — هر بار که معلم به یک یا چند دانش‌آموز/تیم امتیاز می‌دهد یک ردیف.
 * ردیف‌های XP و امتیازِ تیمیِ همان نوبت به آن گره می‌خورند تا کلِ نوبت یک‌جا
 * ویرایش، برگردانده یا حذف شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('point_batches')) {
            Schema::create('point_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->nullable()->index();
                $table->foreignId('teacher_id')->index();
                $table->foreignId('classroom_id')->nullable()->index();
                $table->foreignId('class_activity_id')->nullable()->index();
                $table->integer('amount');
                $table->string('reason', 160);
                $table->string('category', 30)->nullable();
                $table->string('team_mode', 10)->default('each');
                $table->string('target_label', 255)->nullable();
                $table->unsignedInteger('students_count')->default(0);
                $table->unsignedInteger('teams_count')->default(0);
                $table->timestamps();
            });
        }
        foreach (['team_points', 'activity_awards'] as $t) {
            if (Schema::hasTable($t) && ! Schema::hasColumn($t, 'batch_id')) {
                Schema::table($t, fn (Blueprint $table) => $table->unsignedBigInteger('batch_id')->nullable()->index());
            }
        }
    }

    public function down(): void
    {
        foreach (['team_points', 'activity_awards'] as $t) {
            if (Schema::hasColumn($t, 'batch_id')) {
                Schema::table($t, fn (Blueprint $table) => $table->dropColumn('batch_id'));
            }
        }
        Schema::dropIfExists('point_batches');
    }
};
