<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دسته‌بندیِ بانک سؤالات بر مبنای مقطع→کلاس→درس + اشتراک‌گذاریِ دقیق برای مدارس.
 * - ستونِ level (مقطع) به smart_question_bank اضافه می‌شود.
 * - جدولِ bank_shares: هر ردیف یعنی «این مدرسه به سؤال‌های سراسریِ این مقطع/کلاس/درس دسترسی دارد».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('smart_question_bank') && ! Schema::hasColumn('smart_question_bank', 'level')) {
            Schema::table('smart_question_bank', function (Blueprint $table) {
                $table->string('level')->nullable()->after('scope'); // مقطع
                $table->index(['level', 'grade', 'subject']);
            });
        }

        if (! Schema::hasTable('bank_shares')) {
            Schema::create('bank_shares', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained()->cascadeOnDelete();
                $table->string('level')->nullable();   // مقطع — NULL یعنی همه‌ی مقاطع
                $table->string('grade')->nullable();    // کلاس — NULL یعنی همه‌ی کلاس‌های آن مقطع
                $table->string('subject')->nullable();  // درس/کتاب — NULL یعنی همه‌ی دروس
                $table->timestamps();
                $table->index(['school_id', 'level', 'grade', 'subject']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_shares');
        if (Schema::hasColumn('smart_question_bank', 'level')) {
            Schema::table('smart_question_bank', function (Blueprint $table) {
                $table->dropColumn('level');
            });
        }
    }
};
