<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * محتوای کلاس: پشتیبانی از ویدیو + امتیازدهیِ قابلِ اعتماد.
 *
 * چرا لازم شد: امتیازِ پادکست بر پایه‌ی عددِ «ثانیه» محاسبه می‌شد که
 * مستقیم از مرورگر می‌آمد و سرور هیچ بررسی‌ای روی آن نداشت. یعنی
 * دانش‌آموز می‌توانست بدونِ گوش‌دادن عددِ بزرگ بفرستد و امتیازِ کامل بگیرد.
 *
 * راهِ تازه: سرور «نقشه‌ی پوششِ» شنیده‌شده را نگه می‌دارد (هر ۱۰ ثانیه
 * یک خانه) و با ساعتِ خودش سرعتِ پیشرفت را محدود می‌کند. پس نه جلو زدن
 * به‌کار می‌آید و نه فرستادنِ عددِ ساختگی.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_contents', function (Blueprint $table) {
            // مدتِ مدیا بر حسب ثانیه — مرورگر هنگامِ بارگذاری تشخیص می‌دهد
            $table->unsignedInteger('duration_seconds')->nullable()->after('external_url');
            // امتیازِ این محتوا؛ اگر خالی باشد از روی مدت حساب می‌شود
            $table->unsignedSmallInteger('xp_reward')->nullable()->after('duration_seconds');
        });

        Schema::table('content_views', function (Blueprint $table) {
            // نقشه‌ی پوشش: شماره‌ی خانه‌های ۱۰ثانیه‌ایِ واقعاً پخش‌شده
            $table->json('covered')->nullable()->after('seconds');
            // مجموعِ ثانیه‌ی تأییدشده توسطِ سرور (نه عددِ ادعاییِ مرورگر)
            $table->unsignedInteger('verified_seconds')->default(0)->after('covered');
            // دورترین نقطه‌ای که رسیده — برای ادامه‌ی پخش از همان‌جا
            $table->unsignedInteger('max_position')->default(0)->after('verified_seconds');
            // زمانِ آخرین گزارش، برای محدودکردنِ سرعتِ پیشرفت با ساعتِ سرور
            $table->timestamp('last_ping_at')->nullable()->after('max_position');
            // لحظه‌ی تکمیل؛ امتیاز فقط یک‌بار و در همین لحظه داده می‌شود
            $table->timestamp('completed_at')->nullable()->after('last_ping_at');
        });
    }

    public function down(): void
    {
        Schema::table('class_contents', function (Blueprint $table) {
            $table->dropColumn(['duration_seconds', 'xp_reward']);
        });

        Schema::table('content_views', function (Blueprint $table) {
            $table->dropColumn(['covered', 'verified_seconds', 'max_position', 'last_ping_at', 'completed_at']);
        });
    }
};
