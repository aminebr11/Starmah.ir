<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «مطالعه شد» برای هر ردیفِ فیدِ اعلان.
 *
 * تا امروز فقط اطلاعیه‌های شخصی ستونِ read_at داشتند و بقیه‌ی ردیف‌های فید
 * (موردِ انضباطی، پیام، یادداشتِ والدین، مأموریت…) با یک مهرِ زمانیِ کلی
 * «دیده شد» می‌شدند: باز کردنِ صفحه‌ی اعلان‌ها همه را یک‌جا پاک می‌کرد و
 * کاربر نمی‌توانست یکی را خوانده و بقیه را نخوانده نگه دارد.
 *
 * کلید همان شناسه‌ی ردیفِ فید است (a12، d3، msg7، pn4، m-today…)، پس هر
 * منبعی بدونِ ستونِ تازه قابلِ علامت‌زدن است.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->timestamp('read_at');
            $table->timestamps();
            $table->unique(['user_id', 'key']);
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_reads');
    }
};
