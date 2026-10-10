<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * اعلان‌ها:
 * - announcements.source_type/source_id: هر اعلان به موضوعش (املا، مسابقه، کاربرگ، مأموریت…) گره می‌خورد
 *   تا با حذفِ موضوع، اعلان‌هایش هم کامل پاک شوند.
 * - notification_hides: «حذف» یا «حذفِ همه» از کارتابلِ اعلان‌های هر کاربر (برای همه‌ی انواعِ ردیفِ فید).
 * بدونِ کلیدِ خارجی تا روی پایگاه‌داده‌ی قدیمیِ هاست هم ساخته شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('announcements') && ! Schema::hasColumn('announcements', 'source_type')) {
            Schema::table('announcements', function (Blueprint $t) {
                $t->string('source_type', 80)->nullable();
                $t->unsignedBigInteger('source_id')->nullable();
                $t->index(['source_type', 'source_id']);
            });
        }
        if (! Schema::hasTable('notification_hides')) {
            Schema::create('notification_hides', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id');
                $t->string('key', 60);
                $t->timestamps();
                $t->unique(['user_id', 'key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_hides');
        if (Schema::hasColumn('announcements', 'source_type')) {
            Schema::table('announcements', function (Blueprint $t) {
                $t->dropIndex(['source_type', 'source_id']);
                $t->dropColumn(['source_type', 'source_id']);
            });
        }
    }
};
