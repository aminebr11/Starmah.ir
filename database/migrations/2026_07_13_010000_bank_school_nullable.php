<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** school_id بانک سؤالات را nullable می‌کند تا ادمین کل بتواند سؤالِ سراسری (بدون مدرسه) بسازد. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('smart_question_bank')) {
            return;
        }
        Schema::table('smart_question_bank', function (Blueprint $table) {
            $table->unsignedBigInteger('school_id')->nullable()->change();
        });
    }

    public function down(): void
    {
    }
};
