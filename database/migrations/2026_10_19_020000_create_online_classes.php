<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «🎥 کلاسِ آنلاین» (شبیهِ اسکای‌روم):
 * - online_schools: دسترسیِ هر مدرسه (ادمینِ کل) و سقفِ نفرات
 * - online_hosts: معلم‌های مجاز و ناظرها (مدیرِ مدرسه)
 * - online_rooms / online_participants: جلسه‌ها و حضور (برای گزارش و امتیاز)
 * - online_signals: پیام‌های اتصالِ WebRTC (پس از تحویل پاک می‌شوند)
 * - online_messages، online_board_pages، online_polls، online_poll_answers
 * بدونِ کلیدِ خارجی تا روی پایگاه‌داده‌ی قدیمیِ هاست هم ساخته شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('online_schools')) {
            Schema::create('online_schools', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('school_id')->unique();
                $t->boolean('enabled')->default(false);
                $t->unsignedSmallInteger('max_participants')->default(60);
                $t->unsignedBigInteger('granted_by')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('online_hosts')) {
            Schema::create('online_hosts', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('school_id')->index();
                $t->unsignedBigInteger('user_id')->unique();
                $t->boolean('can_host')->default(false);
                $t->boolean('supervisor')->default(false);
                $t->unsignedBigInteger('granted_by')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('online_rooms')) {
            Schema::create('online_rooms', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('school_id')->index();
                $t->unsignedBigInteger('teacher_id')->index();
                $t->unsignedBigInteger('classroom_id')->nullable()->index();
                $t->string('title', 150);
                $t->string('status', 12)->default('scheduled')->index(); // scheduled | live | ended
                $t->timestamp('starts_at')->nullable();
                $t->unsignedSmallInteger('duration_min')->nullable();
                $t->timestamp('started_at')->nullable();
                $t->timestamp('ended_at')->nullable();
                $t->timestamp('host_seen_at')->nullable();
                $t->string('media', 12)->default('builtin');
                $t->text('settings')->nullable();     // JSON: chat، قفل، تایمر، صفحه‌ی جاری…
                $t->unsignedInteger('version')->default(1);
                $t->string('recording_path')->nullable();
                $t->unsignedBigInteger('recording_size')->nullable();
                $t->boolean('recording_shared')->default(true);
                $t->boolean('xp_done')->default(false);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('online_participants')) {
            Schema::create('online_participants', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('room_id');
                $t->unsignedBigInteger('user_id')->index();
                $t->string('role', 10)->default('student'); // host | student | observer
                $t->timestamp('joined_at')->nullable();
                $t->timestamp('last_seen_at')->nullable();
                $t->timestamp('left_at')->nullable();
                $t->unsignedInteger('seconds')->default(0);
                $t->unsignedSmallInteger('joins')->default(0);
                $t->timestamp('hand_at')->nullable();
                $t->boolean('can_speak')->default(false);
                $t->boolean('can_video')->default(false);
                $t->boolean('can_draw')->default(false);
                $t->boolean('kicked')->default(false);
                $t->unsignedSmallInteger('stars')->default(0);
                $t->integer('xp')->default(0);
                $t->string('media_state', 40)->nullable(); // آماده‌ی اتصال، وضعیتِ میکروفون…
                $t->timestamps();
                $t->unique(['room_id', 'user_id']);
            });
        }
        if (! Schema::hasTable('online_signals')) {
            Schema::create('online_signals', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('room_id');
                $t->unsignedBigInteger('from_id');
                $t->unsignedBigInteger('to_id');
                $t->string('kind', 12);
                $t->longText('payload')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->index(['room_id', 'to_id']);
            });
        }
        if (! Schema::hasTable('online_messages')) {
            Schema::create('online_messages', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('room_id')->index();
                $t->unsignedBigInteger('user_id');
                $t->string('kind', 10)->default('chat'); // chat | system
                $t->text('body');
                $t->boolean('hidden')->default(false);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('online_board_pages')) {
            Schema::create('online_board_pages', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('room_id');
                $t->unsignedSmallInteger('page');
                $t->string('bg', 255)->nullable();     // blank | grid | lines | dots | نشانیِ تصویر
                $t->longText('ops')->nullable();       // JSON خطوط و شکل‌ها
                $t->unsignedInteger('version')->default(1);
                $t->timestamps();
                $t->unique(['room_id', 'page']);
            });
        }
        if (! Schema::hasTable('online_polls')) {
            Schema::create('online_polls', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('room_id')->index();
                $t->string('question', 300);
                $t->text('options');                   // JSON
                $t->tinyInteger('correct')->nullable();
                $t->unsignedSmallInteger('xp')->default(0);
                $t->string('status', 10)->default('open'); // open | closed | shown
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('online_poll_answers')) {
            Schema::create('online_poll_answers', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('poll_id');
                $t->unsignedBigInteger('user_id');
                $t->unsignedTinyInteger('choice');
                $t->timestamps();
                $t->unique(['poll_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        foreach (['online_poll_answers', 'online_polls', 'online_board_pages', 'online_messages', 'online_signals',
            'online_participants', 'online_rooms', 'online_hosts', 'online_schools'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
