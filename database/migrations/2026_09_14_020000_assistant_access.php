<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دسترسیِ دستیارِ هوشمند.
 *
 * ادمینِ کل کلیدِ هوش مصنوعی را می‌گذارد؛ مدیرِ مدرسه تصمیم می‌گیرد
 * دستیار برای معلم‌ها و دانش‌آموزانِ همان مدرسه باز باشد یا نه، و آیا
 * اجازه‌ی استفاده از هوش مصنوعی (هزینه‌دار) را دارند یا فقط پاسخ‌های
 * آماده‌ی سامانه را ببینند.
 *
 * پیش‌فرضِ هر دو «باز» است تا رفتارِ فعلیِ سایت عوض نشود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            foreach ([
                'assistant_students' => true,
                'assistant_teachers' => true,
                'assistant_ai'       => true,
            ] as $col => $default) {
                if (! Schema::hasColumn('schools', $col)) {
                    $table->boolean($col)->default($default);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            foreach (['assistant_students', 'assistant_teachers', 'assistant_ai'] as $c) {
                if (Schema::hasColumn('schools', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
