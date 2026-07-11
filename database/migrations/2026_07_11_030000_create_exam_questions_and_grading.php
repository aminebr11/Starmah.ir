<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** بانک سؤالات آزمون + ستون‌های تصحیحِ تشریحی برای پاسخ‌ها. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('mc');       // mc|tf|desc
            $table->string('lesson')->nullable();         // درس
            $table->string('grade')->nullable();          // پایه
            $table->text('prompt');
            $table->json('choices')->nullable();          // برای mc/tf
            $table->timestamps();
            $table->index(['school_id', 'lesson', 'type']);
        });

        Schema::table('assignment_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('assignment_submissions', 'auto_score')) {
                $table->decimal('auto_score', 6, 2)->nullable()->after('max_score');
            }
            if (! Schema::hasColumn('assignment_submissions', 'auto_max')) {
                $table->unsignedInteger('auto_max')->nullable()->after('auto_score');
            }
            if (! Schema::hasColumn('assignment_submissions', 'desc_graded')) {
                $table->boolean('desc_graded')->default(false)->after('auto_max');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_questions');
        Schema::table('assignment_submissions', function (Blueprint $table) {
            foreach (['auto_score', 'auto_max', 'desc_graded'] as $c) {
                if (Schema::hasColumn('assignment_submissions', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
