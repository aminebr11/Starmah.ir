<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مأموریت‌های روزانه: منابعِ بیشتر + انتخابِ دقیقِ سؤال + جعبه‌ی گنجِ روزانه.
 *
 * پیش از این معلم فقط می‌توانست بگوید «چند سؤال از این درس» و سؤال‌ها
 * کور انتخاب می‌شدند. حالا می‌تواند دقیقاً همان سؤال‌هایی را که خودش
 * پسندیده سنجاق کند، و مأموریت را از آزمون و ویدیو و جزوه هم بسازد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            // سؤال‌های سنجاق‌شده از بانک؛ خالی یعنی «تصادفی از فیلترها»
            $table->json('question_ids')->nullable()->after('difficulty');
            // یادداشتِ معلم که به دانش‌آموز نمایش داده می‌شود
            $table->string('description', 400)->nullable()->after('title');
            // درصدِ لازم برای «قبولی» در مأموریتِ سؤالی (۰ تا ۱۰۰)
            $table->unsignedTinyInteger('pass_percent')->default(60)->after('question_count');
        });

        // جعبه‌ی گنجِ روزانه: وقتی دانش‌آموز همه‌ی مأموریت‌های امروز را تمام کند
        Schema::create('mission_combos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->date('play_date');
            $table->unsignedSmallInteger('missions_done')->default(0);
            $table->unsignedSmallInteger('xp_awarded')->default(0);
            $table->unsignedSmallInteger('streak')->default(0);
            $table->timestamps();
            $table->unique(['student_id', 'play_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_combos');
        Schema::table('missions', function (Blueprint $table) {
            $table->dropColumn(['question_ids', 'description', 'pass_percent']);
        });
    }
};
