<?php

use Illuminate\Database\Migrations\Migration;

/**
 * یک‌بار پس از نصب: اشتباه‌های آزمون و بازیِ ۶۰ روزِ اخیر هم «مرورِ اشتباه‌ها» می‌گیرند،
 * تا مرورِ جبرانی از همان روزِ اول برای دانش‌آموزانی که قبلاً اشتباه داشته‌اند کار کند.
 * فقط اضافه می‌کند؛ اگر به هر دلیل شکست بخورد، چیزی را خراب نمی‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        @set_time_limit(300);
        rescue(fn () => app(\App\Services\RemediationService::class)->backfill(60), null, true);
    }

    public function down(): void
    {
        // داده‌ی ساخته‌شده عمداً پاک نمی‌شود
    }
};
