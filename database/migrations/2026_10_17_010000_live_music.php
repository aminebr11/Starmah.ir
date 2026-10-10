<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «🎵 آهنگِ مسابقه‌ی زنده»: کتابخانه‌ی آهنگ‌های هر معلم (در سیستم می‌مانند) و آهنگِ انتخابیِ هر مسابقه.
 * بدونِ کلیدِ خارجی تا روی پایگاه‌داده‌ی قدیمیِ هاست هم ساخته شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('live_tracks')) {
            Schema::create('live_tracks', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('school_id')->nullable()->index();
                $t->unsignedBigInteger('teacher_id')->index();
                $t->string('title', 120);
                $t->string('path');
                $t->unsignedInteger('size')->default(0);
                $t->timestamps();
            });
        }
        if (Schema::hasTable('live_contests') && ! Schema::hasColumn('live_contests', 'music_track_id')) {
            Schema::table('live_contests', function (Blueprint $t) {
                $t->unsignedBigInteger('music_track_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('live_contests', 'music_track_id')) {
            Schema::table('live_contests', fn (Blueprint $t) => $t->dropColumn('music_track_id'));
        }
        Schema::dropIfExists('live_tracks');
    }
};
