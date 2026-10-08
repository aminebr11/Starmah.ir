<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * زمان‌بندیِ «مرورِ اشتباه‌ها» که معلم برای هر کلاس اعلام می‌کند
 * (نوبتِ اول چه وقت، چند نوبت، فاصله‌ی نوبت‌ها، اگر رد شد کِی دوباره …).
 * هر مرور هنگامِ ساخته‌شدن نسخه‌ای از همین زمان‌بندی را در ستونِ plan نگه می‌دارد.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('remediation_plans')) {
            Schema::create('remediation_plans', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('classroom_id')->unique();
                $t->unsignedBigInteger('teacher_id')->nullable()->index();
                $t->json('settings');
                $t->timestamps();
            });
        }
        if (Schema::hasTable('remediations') && ! Schema::hasColumn('remediations', 'plan')) {
            Schema::table('remediations', fn (Blueprint $t) => $t->json('plan')->nullable());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('remediation_plans');
        if (Schema::hasColumn('remediations', 'plan')) {
            Schema::table('remediations', fn (Blueprint $t) => $t->dropColumn('plan'));
        }
    }
};
