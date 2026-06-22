<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مدرسه = مستأجر (Tenant) در معماری چندمستأجری.
 * هر مدرسه اشتراک، پلن و برندینگ خودش را دارد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('city')->nullable();
            $table->string('logo')->nullable();
            $table->enum('plan', ['trial', 'basic', 'pro', 'premium'])->default('trial');
            $table->enum('status', ['active', 'suspended', 'expired'])->default('active');
            $table->unsignedInteger('seats')->default(30); // سقف تعداد دانش‌آموز طبق پلن
            $table->date('subscription_ends_at')->nullable();
            $table->json('branding')->nullable(); // رنگ/لوگو اختصاصی (white-label)
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
