<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دسته‌بندیِ درستِ سؤال‌ها: مقطع → پایه → درس → «فصل» → مبحث.
 *
 * تا پیش از این «فصل» یک متنِ آزاد بود («فصل ۲»، «۲»، «کسر») و سؤال‌های یک
 * فصل در بانک زیرِ چند نامِ مختلف پخش می‌شدند. حالا فصل‌ها جدولِ خودشان را
 * دارند (سراسری از طرفِ ادمین کل، یا مخصوصِ مدرسه وقتی معلم فصلی اضافه کند)
 * و همه‌جا با شناسه ارجاع داده می‌شوند؛ عنوانِ متنی هم کنارش نگه داشته
 * می‌شود تا داده‌های قدیمی و گزارش‌ها نشکنند.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('curriculum_chapters')) {
            Schema::create('curriculum_chapters', function (Blueprint $t) {
                $t->id();
                $t->foreignId('curriculum_book_id')->nullable()->constrained('curriculum_books')->nullOnDelete();
                $t->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete(); // NULL = سراسری
                $t->string('level')->nullable();
                $t->string('grade');
                $t->string('subject');
                $t->unsignedSmallInteger('number')->default(1);
                $t->string('title', 160);
                $t->json('lessons')->nullable();          // درس‌ها/مبحث‌های داخلِ فصل
                $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->index(['grade', 'subject', 'number']);
            });
        }

        $add = function (string $table, array $cols) {
            if (! Schema::hasTable($table)) {
                return;
            }
            Schema::table($table, function (Blueprint $t) use ($table, $cols) {
                foreach ($cols as $name => $def) {
                    if (! Schema::hasColumn($table, $name)) {
                        $def($t);
                    }
                }
            });
        };

        $add('smart_question_bank', [
            'chapter_id'  => fn ($t) => $t->unsignedBigInteger('chapter_id')->nullable()->index(),
            'bloom'       => fn ($t) => $t->string('bloom', 20)->nullable(),
            'hint'        => fn ($t) => $t->string('hint', 300)->nullable(),
            'fingerprint' => fn ($t) => $t->string('fingerprint', 40)->nullable()->index(),
        ]);
        $add('smart_exams', [
            'level'      => fn ($t) => $t->string('level')->nullable(),
            'chapter_id' => fn ($t) => $t->unsignedBigInteger('chapter_id')->nullable(),
        ]);
        $add('smart_exam_questions', [
            'bloom' => fn ($t) => $t->string('bloom', 20)->nullable(),
        ]);
        $add('edu_games', [
            'level'      => fn ($t) => $t->string('level')->nullable(),
            'chapter_id' => fn ($t) => $t->unsignedBigInteger('chapter_id')->nullable(),
            'chapter'    => fn ($t) => $t->string('chapter')->nullable(),
            'topic'      => fn ($t) => $t->string('topic')->nullable(),
            'goal'       => fn ($t) => $t->string('goal', 300)->nullable(),
        ]);
        $add('edu_game_questions', [
            'bank_id'    => fn ($t) => $t->unsignedBigInteger('bank_id')->nullable(),
            'difficulty' => fn ($t) => $t->string('difficulty')->default('medium'),
            'bloom'      => fn ($t) => $t->string('bloom', 20)->nullable(),
            'topic'      => fn ($t) => $t->string('topic')->nullable(),
            'source'     => fn ($t) => $t->string('source')->default('manual'),
        ]);
    }

    public function down(): void
    {
        $drop = function (string $table, array $cols) {
            if (! Schema::hasTable($table)) {
                return;
            }
            $cols = array_values(array_filter($cols, fn ($c) => Schema::hasColumn($table, $c)));
            if ($cols) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($cols));
            }
        };
        $drop('edu_game_questions', ['bank_id', 'difficulty', 'bloom', 'topic', 'source']);
        $drop('edu_games', ['level', 'chapter_id', 'chapter', 'topic', 'goal']);
        $drop('smart_exam_questions', ['bloom']);
        $drop('smart_exams', ['level', 'chapter_id']);
        $drop('smart_question_bank', ['chapter_id', 'bloom', 'hint', 'fingerprint']);
        Schema::dropIfExists('curriculum_chapters');
    }
};
