<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * شماره‌ی ولی در فرم‌های ثبت‌نام فقط در settings.guardian.phone ذخیره می‌شد،
 * در حالی که پیامک‌های خودکار (نمره، غیبت، تکلیف…) ستونِ parent_phone را
 * می‌خواندند؛ نتیجه این بود که پیامک هیچ‌وقت به این خانواده‌ها نمی‌رسید.
 * این مهاجرت شماره‌های موجود را به ستونِ درست منتقل می‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'parent_phone')) {
            return;
        }
        DB::table('users')->whereNull('parent_phone')->whereNotNull('settings')->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $r) {
                    $s = json_decode((string) $r->settings, true);
                    $p = is_array($s) ? trim((string) ($s['guardian']['phone'] ?? '')) : '';
                    if ($p !== '') {
                        DB::table('users')->where('id', $r->id)->update(['parent_phone' => mb_substr($p, 0, 20)]);
                    }
                }
            });
    }

    public function down(): void
    {
    }
};
