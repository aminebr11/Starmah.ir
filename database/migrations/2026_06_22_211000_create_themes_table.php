<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * موتور تم — قلب تمایز محصول.
 * هر تم سه لایه دارد که جدا از محتوای آموزشی نگه‌داری می‌شوند:
 *   skin      → توکن‌های بصری (رنگ، فونت، ماسکوت، صدا)   [لایه ۱]
 *   narrative → واژگان گیمیفیکیشن (گل/نیترو، لیگ/گرنپری)  [لایه ۲]
 *   content   → لیست‌های جایگزینی برای روکش داستانی سؤال   [لایه ۳]
 * افزودن یک دنیای جدید = فقط یک ردیف در این جدول.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();      // football, cars, space...
            $table->string('name');               // «فوتبال»
            $table->string('emoji', 16)->nullable();
            $table->json('skin');                 // لایه ۱: CSS tokens
            $table->json('narrative');            // لایه ۲: واژگان و عبارات
            $table->json('content_pools')->nullable(); // لایه ۳: لیست اسم‌ها برای قالب‌ها
            $table->boolean('is_active')->default(true);
            $table->boolean('is_premium')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('themes');
    }
};
