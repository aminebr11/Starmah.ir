<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سامانه‌ی جامعِ پیامکِ مدرسه.
 *
 * سه لایه‌ی دسترسی:
 *   ۱) ادمینِ کل درگاه را تنظیم می‌کند (کلید و خطِ ارسال در Setting).
 *   ۲) ادمینِ کل برای هر مدرسه دسترسی و سهمیه‌ی ماهانه می‌دهد.
 *   ۳) مدیرِ مدرسه به هر معلم اجازه و سهمیه می‌دهد.
 *
 * شمارشِ مصرف از روی جدولِ sms_messages انجام می‌شود، نه یک شمارنده‌ی
 * جداگانه؛ این‌طور گزارش و سهمیه هرگز از هم دور نمی‌افتند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (! Schema::hasColumn('schools', 'sms_enabled')) {
                $table->boolean('sms_enabled')->default(false)->after('settings');
            }
            if (! Schema::hasColumn('schools', 'sms_quota')) {
                // سهمیه‌ی ماهانه؛ null یعنی بدونِ سقف
                $table->unsignedInteger('sms_quota')->nullable()->after('sms_enabled');
            }
            if (! Schema::hasColumn('schools', 'sms_sender')) {
                $table->string('sms_sender', 40)->nullable()->after('sms_quota');
            }
            if (! Schema::hasColumn('schools', 'sms_events')) {
                // کدام اعلان‌ها پیامک شوند و برای چه کسی (JSON)
                $table->json('sms_events')->nullable()->after('sms_sender');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'sms_allowed')) {
                $table->boolean('sms_allowed')->default(false)->after('is_active');
            }
            if (! Schema::hasColumn('users', 'sms_quota')) {
                $table->unsignedInteger('sms_quota')->nullable()->after('sms_allowed');
            }
            if (! Schema::hasColumn('users', 'parent_phone')) {
                // شماره‌ی ولی — اگر حسابِ والد جدا نداشته باشیم
                $table->string('parent_phone', 20)->nullable()->after('phone');
            }
        });

        if (Schema::hasTable('sms_messages')) {
            return;   // ساخته‌شده با SQL دستی — دوباره نسازیم
        }

        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phone', 20);
            $table->text('body');
            // manual = ارسالِ دستی؛ بقیه برچسبِ رویدادِ اعلان است
            $table->string('kind', 40)->default('manual');
            $table->string('status', 20)->default('queued');   // queued|sent|failed
            $table->string('provider_id', 80)->nullable();
            $table->string('error', 255)->nullable();
            $table->unsignedSmallInteger('segments')->default(1);
            $table->timestamps();
            $table->index(['school_id', 'created_at']);
            $table->index(['sender_id', 'created_at']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
        Schema::table('users', function (Blueprint $table) {
            foreach (['sms_allowed', 'sms_quota', 'parent_phone'] as $c) {
                if (Schema::hasColumn('users', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
        Schema::table('schools', function (Blueprint $table) {
            foreach (['sms_enabled', 'sms_quota', 'sms_sender', 'sms_events'] as $c) {
                if (Schema::hasColumn('schools', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
