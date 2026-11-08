<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * schools.plan از ابتدا enum بود (trial, basic, pro, premium)؛ طرح‌هایی که ادمین می‌سازد کلیدِ دیگری
 * دارند (مثلاً enterprise) و تأییدِ مدرسه با خطای «Data truncated for column 'plan'» شکست می‌خورد.
 * طرحِ واقعی در plan_id است؛ این ستون فقط کلیدِ طرح را نگه می‌دارد → متنِ آزاد.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('schools') && Schema::hasColumn('schools', 'plan')) {
            Schema::table('schools', function (Blueprint $t) {
                $t->string('plan', 40)->default('trial')->change();
            });
        }
    }

    public function down(): void
    {
        // برگشت به enum ممکن است داده‌ی موجود را خراب کند؛ عمداً کاری نمی‌کند.
    }
};
