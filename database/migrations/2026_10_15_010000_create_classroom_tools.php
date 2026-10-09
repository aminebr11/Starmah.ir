<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ابزارهای تازه‌ی کلاس:
 * - audio_tasks / audio_submissions: «املا و روخوانیِ صوتی» (صدای معلم یا متن → صدا؛ عکسِ املا یا صدای روخوانی؛ تصحیح و نمره)
 * - live_contests (+ players, answers): «مسابقه‌ی زنده» روی تخته‌ی هوشمند
 * - weekly_reports: گزارشِ هفتگیِ هر دانش‌آموز برای والدین
 * - classroom_prefs: تنظیماتِ هر کلاس (گزارشِ هفتگی، سلامتِ دیجیتال)
 * - screen_times: زمانِ استفاده‌ی روزانه‌ی دانش‌آموز (سقفِ زمان و استراحت)
 * بدونِ کلیدِ خارجی (فقط ایندکس) تا روی پایگاه‌داده‌ی قدیمیِ هاست هم ساخته شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audio_tasks')) {
            Schema::create('audio_tasks', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('school_id')->nullable()->index();
                $t->unsignedBigInteger('teacher_id')->index();
                $t->unsignedBigInteger('classroom_id')->nullable()->index();
                $t->string('kind', 12);                       // dictation | reading
                $t->string('title', 150);
                $t->string('subject', 60)->nullable();
                $t->string('grade', 40)->nullable();
                $t->text('text')->nullable();                 // متنِ املا (هرگز به دانش‌آموز داده نمی‌شود)
                $t->json('sentences')->nullable();            // [{text, audio}]
                $t->string('source', 10)->default('tts');     // voice | upload | tts
                $t->string('audio_path')->nullable();         // صدای کاملِ معلم
                $t->string('pace', 10)->default('normal');
                $t->string('score_type', 12)->default('descriptive'); // descriptive | numeric
                $t->decimal('penalty', 4, 2)->default(0.5);  // کسرِ هر غلط در نمره‌ی عددی
                $t->unsignedBigInteger('grade_column_id')->nullable();
                $t->timestamp('due_at')->nullable();
                $t->boolean('is_published')->default(false);
                $t->timestamp('published_at')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('audio_submissions')) {
            Schema::create('audio_submissions', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('audio_task_id')->index();
                $t->unsignedBigInteger('student_id')->index();
                $t->string('file_path')->nullable();
                $t->string('file_kind', 10)->nullable();      // image | audio | pdf
                $t->string('note', 300)->nullable();
                $t->unsignedInteger('plays')->default(0);
                $t->timestamp('submitted_at')->nullable();
                $t->boolean('submit_xp')->default(false);
                $t->unsignedSmallInteger('mistakes')->nullable();
                $t->string('grade', 40)->nullable();
                $t->decimal('score', 5, 2)->nullable();
                $t->string('feedback', 500)->nullable();
                $t->string('marked_path')->nullable();
                $t->timestamp('graded_at')->nullable();
                $t->unsignedBigInteger('graded_by')->nullable();
                $t->timestamps();
                $t->unique(['audio_task_id', 'student_id']);
            });
        }

        if (! Schema::hasTable('live_contests')) {
            Schema::create('live_contests', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('school_id')->nullable()->index();
                $t->unsignedBigInteger('teacher_id')->index();
                $t->unsignedBigInteger('classroom_id')->nullable()->index();
                $t->string('title', 150);
                $t->string('code', 8)->index();
                $t->string('mode', 10)->default('manual');    // manual | auto
                $t->timestamp('starts_at')->nullable();
                $t->string('phase', 10)->default('draft');    // draft | lobby | question | reveal | end
                $t->integer('current')->default(-1);
                $t->unsignedBigInteger('phase_at')->nullable(); // میلی‌ثانیه
                $t->unsignedSmallInteger('seconds')->default(20);
                $t->json('questions');
                $t->boolean('rewarded')->default(false);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('live_contest_players')) {
            Schema::create('live_contest_players', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('live_contest_id')->index();
                $t->unsignedBigInteger('student_id')->index();
                $t->integer('score')->default(0);
                $t->unsignedSmallInteger('correct')->default(0);
                $t->unsignedSmallInteger('streak')->default(0);
                $t->timestamp('last_seen_at')->nullable();
                $t->timestamps();
                $t->unique(['live_contest_id', 'student_id']);
            });
        }
        if (! Schema::hasTable('live_contest_answers')) {
            Schema::create('live_contest_answers', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('live_contest_id')->index();
                $t->unsignedBigInteger('student_id');
                $t->unsignedSmallInteger('q_index');
                $t->smallInteger('choice');
                $t->boolean('correct');
                $t->unsignedInteger('points')->default(0);
                $t->unsignedInteger('ms')->default(0);
                $t->timestamp('created_at')->nullable();
                $t->unique(['live_contest_id', 'student_id', 'q_index']);
            });
        }

        if (! Schema::hasTable('weekly_reports')) {
            Schema::create('weekly_reports', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('school_id')->nullable()->index();
                $t->unsignedBigInteger('classroom_id')->index();
                $t->unsignedBigInteger('teacher_id')->nullable();
                $t->unsignedBigInteger('student_id')->index();
                $t->date('week_start');
                $t->json('data');
                $t->json('activity')->nullable();
                $t->string('teacher_note', 600)->nullable();
                $t->string('status', 10)->default('draft');   // draft | approved | sent | skipped
                $t->timestamp('sent_at')->nullable();
                $t->string('sms_status', 30)->nullable();
                $t->timestamps();
                $t->unique(['student_id', 'week_start']);
            });
        }
        if (! Schema::hasTable('classroom_prefs')) {
            Schema::create('classroom_prefs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('classroom_id');
                $t->string('key', 40);
                $t->json('value');
                $t->timestamps();
                $t->unique(['classroom_id', 'key']);
            });
        }
        if (! Schema::hasTable('screen_times')) {
            Schema::create('screen_times', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('student_id');
                $t->date('day');
                $t->unsignedInteger('seconds')->default(0);
                $t->unsignedInteger('session_seconds')->default(0);
                $t->timestamp('last_beat_at')->nullable();
                $t->unsignedInteger('resets')->default(0);
                $t->timestamps();
                $t->unique(['student_id', 'day']);
            });
        }
    }

    public function down(): void
    {
        foreach (['screen_times', 'classroom_prefs', 'weekly_reports', 'live_contest_answers', 'live_contest_players', 'live_contests', 'audio_submissions', 'audio_tasks'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
