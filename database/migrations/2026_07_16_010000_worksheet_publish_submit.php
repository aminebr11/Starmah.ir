<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تکمیلِ بانک کاربرگ‌ها: دسته‌بندیِ مقطع→کلاس→درس→شماره درس، تصویرِ تولیدشده با AI،
 * انتشار برای کلاس + اعلان به دانش‌آموز، و ارسالِ کاربرگِ پرشده توسط دانش‌آموز به معلم.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('worksheets')) {
            Schema::table('worksheets', function (Blueprint $table) {
                if (! Schema::hasColumn('worksheets', 'level')) {
                    $table->string('level')->nullable()->after('scope');
                }
                if (! Schema::hasColumn('worksheets', 'lesson_no')) {
                    $table->string('lesson_no', 40)->nullable()->after('subject');
                }
                if (! Schema::hasColumn('worksheets', 'classroom_id')) {
                    $table->unsignedBigInteger('classroom_id')->nullable()->after('teacher_id');
                }
                if (! Schema::hasColumn('worksheets', 'image_path')) {
                    $table->string('image_path')->nullable()->after('render_html');
                }
                if (! Schema::hasColumn('worksheets', 'is_published')) {
                    $table->boolean('is_published')->default(false)->after('image_path');
                }
                if (! Schema::hasColumn('worksheets', 'published_at')) {
                    $table->timestamp('published_at')->nullable()->after('is_published');
                }
            });
        }

        if (! Schema::hasTable('worksheet_submissions')) {
            Schema::create('worksheet_submissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('worksheet_id')->constrained('worksheets')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
                $table->string('file_path');
                $table->string('note', 300)->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
                $table->unique(['worksheet_id', 'student_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('worksheet_submissions');
        if (Schema::hasTable('worksheets')) {
            Schema::table('worksheets', function (Blueprint $table) {
                foreach (['level', 'lesson_no', 'classroom_id', 'image_path', 'is_published', 'published_at'] as $c) {
                    if (Schema::hasColumn('worksheets', $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }
    }
};
