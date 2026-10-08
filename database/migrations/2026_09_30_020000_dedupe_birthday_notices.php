<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * پاک‌کردنِ اعلان‌های تکراریِ «تولد دانش‌آموز».
 * نسخه‌ی قبلی با بارگذاریِ همزمانِ داشبوردها برای یک تولد چند اعلانِ
 * یکسان می‌ساخت؛ از هر گروهِ یکسان (همان متن در همان روز) فقط اولی می‌ماند.
 */
return new class extends Migration {
    public function up(): void
    {
        $rows = DB::table('announcements')->where('title', '🎂 تولد دانش‌آموز')
            ->orderBy('id')->get(['id', 'body', 'created_at']);
        $seen = [];
        $drop = [];
        foreach ($rows as $r) {
            $k = md5($r->body . '|' . substr((string) $r->created_at, 0, 10));
            if (isset($seen[$k])) {
                $drop[] = $r->id;
            } else {
                $seen[$k] = true;
            }
        }
        foreach (array_chunk($drop, 200) as $ids) {
            DB::table('announcement_recipients')->whereIn('announcement_id', $ids)->delete();
            DB::table('announcements')->whereIn('id', $ids)->delete();
        }
    }

    public function down(): void {}
};
