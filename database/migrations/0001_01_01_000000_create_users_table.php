<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // مستأجر: هر کاربر متعلق به یک مدرسه است (سوپرادمین = null)
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->string('name');
            // در ایران ورود با موبایل مرسوم است؛ ایمیل اختیاری
            $table->string('phone', 20)->nullable()->index();
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('avatar')->nullable();
            // تم انتخابی دانش‌آموز (دنیای علاقه)
            $table->foreignId('theme_id')->nullable();
            $table->string('grade')->nullable();          // پایه‌ی تحصیلی
            $table->string('national_id', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->json('settings')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->unique(['school_id', 'phone']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
