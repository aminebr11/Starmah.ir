<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * املا و روخوانی:
 * - publish_at: انتشارِ زمان‌بندی‌شده (سرِ ساعت خودش برای کلاس فرستاده می‌شود)
 * - audio_hash: اثرانگشتِ متن و مکث‌ها؛ با ویرایشِ متن، صدای سیستم دوباره ساخته می‌شود
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audio_tasks')) {
            return;
        }
        Schema::table('audio_tasks', function (Blueprint $t) {
            if (! Schema::hasColumn('audio_tasks', 'publish_at')) {
                $t->timestamp('publish_at')->nullable();
            }
            if (! Schema::hasColumn('audio_tasks', 'audio_hash')) {
                $t->string('audio_hash', 64)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('audio_tasks')) {
            Schema::table('audio_tasks', function (Blueprint $t) {
                foreach (['publish_at', 'audio_hash'] as $c) {
                    if (Schema::hasColumn('audio_tasks', $c)) {
                        $t->dropColumn($c);
                    }
                }
            });
        }
    }
};
