<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * غنی‌سازیِ طرح‌ها برای «صفحه‌ی قیمت» + سفارش/پرداختِ آنلاین.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (! Schema::hasColumn('plans', 'features')) {
                $table->json('features')->nullable()->after('description');    // فهرست ویژگی‌ها
            }
            if (! Schema::hasColumn('plans', 'highlighted')) {
                $table->boolean('highlighted')->default(false)->after('is_active'); // پیشنهادِ ویژه
            }
            if (! Schema::hasColumn('plans', 'period_label')) {
                $table->string('period_label', 40)->nullable()->after('price');  // مثلاً «سالانه»
            }
        });

        // پلن انتخاب‌شده + وضعیت پرداختِ درخواستِ ثبت‌نام مدرسه
        Schema::table('school_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('school_requests', 'plan_key')) {
                $table->string('plan_key', 40)->nullable()->after('level');
            }
            if (! Schema::hasColumn('school_requests', 'paid')) {
                $table->boolean('paid')->default(false)->after('plan_key');
            }
        });

        if (! Schema::hasTable('payment_transactions')) {
            Schema::create('payment_transactions', function (Blueprint $table) {
                $table->id();
                $table->string('gateway', 40)->default('manual');           // zarinpal | idpay | manual | ...
                $table->unsignedBigInteger('amount');                        // تومان
                $table->string('purpose', 40)->default('school_subscription');
                $table->unsignedBigInteger('plan_id')->nullable();
                $table->unsignedBigInteger('school_request_id')->nullable();
                $table->unsignedBigInteger('school_id')->nullable();
                $table->string('payer_name', 120)->nullable();
                $table->string('payer_phone', 20)->nullable();
                $table->string('authority', 120)->nullable()->index();       // شناسه‌ی درگاه
                $table->string('ref_id', 120)->nullable();                   // کدِ پیگیریِ نهایی
                $table->string('status', 20)->default('pending');            // pending | paid | failed | canceled
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
        Schema::table('school_requests', function (Blueprint $table) {
            if (Schema::hasColumn('school_requests', 'plan_key')) $table->dropColumn('plan_key');
            if (Schema::hasColumn('school_requests', 'paid')) $table->dropColumn('paid');
        });
        Schema::table('plans', function (Blueprint $table) {
            foreach (['features', 'highlighted', 'period_label'] as $c) {
                if (Schema::hasColumn('plans', $c)) $table->dropColumn($c);
            }
        });
    }
};
