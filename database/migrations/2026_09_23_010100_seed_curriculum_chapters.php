<?php

use Illuminate\Database\Migrations\Migration;

/** فصل‌های آغازین را روی هاست هم بدونِ نیاز به ترمینال می‌نشاند (تکرارپذیر). */
return new class extends Migration
{
    public function up(): void
    {
        (new \Database\Seeders\CurriculumChapterSeeder())->run();
    }

    public function down(): void
    {
        // فصل‌ها ممکن است ویرایش شده باشند؛ حذفِ خودکار نمی‌کنیم.
    }
};
