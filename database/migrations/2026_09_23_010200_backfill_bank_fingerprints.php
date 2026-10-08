<?php

use App\Support\Curriculum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** اثرانگشت و مقطعِ سؤال‌های قدیمیِ بانک — تا تشخیصِ تکراری روی داده‌های قبلی هم کار کند. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('smart_question_bank')->whereNull('fingerprint')->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $r) {
                    DB::table('smart_question_bank')->where('id', $r->id)->update([
                        'fingerprint' => Curriculum::fingerprint($r->prompt),
                        'level' => $r->level ?: Curriculum::levelOf($r->grade),
                    ]);
                }
            });
    }

    public function down(): void
    {
    }
};
