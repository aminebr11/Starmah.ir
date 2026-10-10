<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «🏆 مسابقه‌ی زنده»:
 * - زمانِ پایان: ends_at (تاریخ و ساعتِ دقیق) یا duration_min (چند دقیقه بعد از شروع؛ started_at)
 * - round: شماره‌ی دور (هر «اجرای دوباره» یک دورِ تازه)
 * - live_contest_results: سابقه‌ی ماندگارِ هر دانش‌آموز در هر دور (با «اجرای دوباره» پاک نمی‌شود)
 * بدونِ کلیدِ خارجی تا روی پایگاه‌داده‌ی قدیمیِ هاست هم ساخته شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('live_contests')) {
            Schema::table('live_contests', function (Blueprint $t) {
                if (! Schema::hasColumn('live_contests', 'ends_at')) $t->timestamp('ends_at')->nullable();
                if (! Schema::hasColumn('live_contests', 'duration_min')) $t->unsignedSmallInteger('duration_min')->nullable();
                if (! Schema::hasColumn('live_contests', 'started_at')) $t->timestamp('started_at')->nullable();
                if (! Schema::hasColumn('live_contests', 'round')) $t->unsignedSmallInteger('round')->default(1);
            });
        }
        if (! Schema::hasTable('live_contest_results')) {
            Schema::create('live_contest_results', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('live_contest_id')->index();
                $t->unsignedSmallInteger('round')->default(1);
                $t->unsignedBigInteger('student_id')->index();
                $t->unsignedSmallInteger('rank');
                $t->unsignedSmallInteger('players');
                $t->integer('score')->default(0);
                $t->unsignedSmallInteger('correct')->default(0);
                $t->unsignedSmallInteger('answered')->default(0);
                $t->unsignedSmallInteger('total')->default(0);
                $t->unsignedTinyInteger('accuracy')->default(0);
                $t->unsignedSmallInteger('best_streak')->default(0);
                $t->unsignedInteger('avg_ms')->nullable();
                $t->unsignedSmallInteger('xp')->default(0);
                $t->timestamp('finished_at')->nullable();
                $t->timestamps();
                $t->unique(['live_contest_id', 'round', 'student_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('live_contest_results');
        foreach (['ends_at', 'duration_min', 'started_at', 'round'] as $c) {
            if (Schema::hasColumn('live_contests', $c)) {
                Schema::table('live_contests', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
    }
};
