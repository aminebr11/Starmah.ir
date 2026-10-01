<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ثبتِ مصرفِ هوش مصنوعی — یک ردیف برای هر درخواست، با توکن‌های ورودی/خروجی. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usages', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->unsignedBigInteger('school_id')->nullable()->index();
            $t->unsignedBigInteger('teacher_id')->nullable()->index(); // معلمِ مسئول (برای دانش‌آموز: معلمِ کلاسش)
            $t->string('role', 20)->nullable();
            $t->string('provider', 20);
            $t->string('model', 80)->nullable();
            $t->string('feature', 40)->nullable();
            $t->unsignedInteger('input_tokens')->default(0);
            $t->unsignedInteger('output_tokens')->default(0);
            $t->boolean('ok')->default(true);
            $t->unsignedSmallInteger('status')->default(0);
            $t->unsignedInteger('ms')->default(0);
            $t->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usages');
    }
};
