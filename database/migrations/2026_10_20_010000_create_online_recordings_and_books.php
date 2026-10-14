<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * کلاسِ آنلاین:
 * - online_recordings: هر ضبط یک فیلمِ جدا (پیش از این هر کلاس فقط یک فیلم داشت و ضبطِ دوم اولی را پاک می‌کرد)
 * - online_books / online_book_pages: کتابِ درسیِ PDF روی سرور؛ نوشته‌های معلم روی هر صفحه‌ی خودِ کتاب
 *   می‌ماند و «تا کجا درس داده‌ام» برای هر کلاس نگه داشته می‌شود.
 * بدونِ کلیدِ خارجی تا روی پایگاه‌داده‌ی قدیمیِ هاست هم ساخته شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('online_recordings')) {
            Schema::create('online_recordings', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('room_id')->index();
                $t->unsignedBigInteger('school_id')->nullable()->index();
                $t->unsignedBigInteger('teacher_id')->nullable()->index();
                $t->string('path', 255);
                $t->unsignedBigInteger('size')->default(0);
                $t->unsignedInteger('duration_ms')->nullable();
                $t->timestamps();
            });
            // فیلم‌های قبلی (یکی برای هر کلاس) هم در فهرست بیایند
            if (Schema::hasTable('online_rooms')) {
                foreach (DB::table('online_rooms')->whereNotNull('recording_path')->get() as $r) {
                    DB::table('online_recordings')->insert([
                        'room_id' => $r->id, 'school_id' => $r->school_id, 'teacher_id' => $r->teacher_id,
                        'path' => $r->recording_path, 'size' => (int) ($r->recording_size ?? 0),
                        'created_at' => $r->started_at ?? $r->created_at, 'updated_at' => $r->ended_at ?? $r->updated_at,
                    ]);
                }
            }
        }
        if (! Schema::hasTable('online_books')) {
            Schema::create('online_books', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('school_id')->nullable()->index();
                $t->unsignedBigInteger('teacher_id')->index();
                $t->string('title', 150);
                $t->string('path', 255);
                $t->unsignedBigInteger('size')->default(0);
                $t->unsignedSmallInteger('pages')->default(0);
                $t->text('progress')->nullable();          // JSON: «تا کجا درس داده‌ام» برای هر کلاس
                $t->unsignedSmallInteger('last_page')->default(1);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('online_book_pages')) {
            Schema::create('online_book_pages', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('book_id');
                $t->unsignedSmallInteger('page');
                $t->string('bg', 255)->nullable();       // تصویرِ ساخته‌شده‌ی همین صفحه
                $t->longText('ops')->nullable();         // نوشته‌های معلم روی صفحه
                $t->unsignedInteger('version')->default(1);
                $t->timestamps();
                $t->unique(['book_id', 'page']);
            });
        }
    }

    public function down(): void
    {
        foreach (['online_book_pages', 'online_books', 'online_recordings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
