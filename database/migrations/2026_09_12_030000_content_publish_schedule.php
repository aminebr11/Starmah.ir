<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * زمان‌بندیِ انتشار و نمایش/مخفی‌کردنِ محتوای کلاس.
 *
 * publish_at خالی یعنی «همین حالا منتشر شود» (رفتارِ قبلی، دست‌نخورده).
 * is_visible دکمه‌ی نمایش/مخفیِ روی کارتِ هر پست است.
 * notified_at جلوی اعلانِ تکراری را می‌گیرد وقتی محتوای زمان‌دار سر می‌رسد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_contents', function (Blueprint $table) {
            if (! Schema::hasColumn('class_contents', 'publish_at')) {
                $table->timestamp('publish_at')->nullable()->after('due_at');
            }
            if (! Schema::hasColumn('class_contents', 'is_visible')) {
                $table->boolean('is_visible')->default(true)->after('publish_at');
            }
            if (! Schema::hasColumn('class_contents', 'notified_at')) {
                $table->timestamp('notified_at')->nullable()->after('is_visible');
            }
        });

        // محتوای موجود همین حالا منتشر است؛ اعلانش هم قبلاً رفته
        \Illuminate\Support\Facades\DB::table('class_contents')
            ->whereNull('notified_at')->update(['notified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('class_contents', function (Blueprint $table) {
            foreach (['publish_at', 'is_visible', 'notified_at'] as $c) {
                if (Schema::hasColumn('class_contents', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
