<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 🤖 باتِ معلم (بله و تلگرام):
 *  bot_links — اتصالِ یک گفتگوی بات به یک حسابِ سایت (هر حساب می‌تواند هر دو پیام‌رسان را وصل کند)
 *  bot_logs  — دفترِ کارهای انجام‌شده از بات (برای مدیر و «برگردان»)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bot_links')) {
            Schema::create('bot_links', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->index();
                $t->unsignedBigInteger('school_id')->nullable()->index();
                $t->string('platform', 12);                 // bale | telegram
                $t->string('chat_id', 40);
                $t->string('username', 80)->nullable();
                $t->boolean('brief')->default(true);          // گزارشِ صبحگاهی
                $t->boolean('notify')->default(true);         // اعلان‌های تازه
                $t->date('brief_on')->nullable();             // آخرین روزی که گزارشِ صبح رفت
                $t->timestamp('notified_at')->nullable();
                $t->timestamp('last_seen_at')->nullable();
                $t->timestamps();
                $t->unique(['platform', 'chat_id']);
            });
        }
        if (! Schema::hasTable('bot_logs')) {
            Schema::create('bot_logs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->index();
                $t->unsignedBigInteger('school_id')->nullable()->index();
                $t->string('platform', 12);
                $t->string('action', 40);
                $t->string('summary', 255)->nullable();
                $t->json('undo')->nullable();
                $t->timestamp('undone_at')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_logs');
        Schema::dropIfExists('bot_links');
    }
};
