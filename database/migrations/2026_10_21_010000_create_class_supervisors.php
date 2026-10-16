<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * نقشِ «ناظر»: مدیرِ مدرسه یک حسابِ معلم را ناظرِ چند کلاسِ انتخابی می‌کند.
 * هر ردیف = این کاربر ناظرِ این کلاس است. ناظم‌هایی که پیش از این «همه‌ی کلاس‌ها»
 * را داشتند (online_hosts.supervisor) ناظرِ همه‌ی کلاس‌های فعلیِ مدرسه می‌شوند.
 * بدونِ کلیدِ خارجی تا روی پایگاه‌داده‌ی قدیمیِ هاست هم ساخته شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('class_supervisors')) {
            return;
        }
        Schema::create('class_supervisors', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('school_id')->index();
            $t->unsignedBigInteger('user_id')->index();
            $t->unsignedBigInteger('classroom_id')->index();
            $t->unsignedBigInteger('granted_by')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'classroom_id']);
        });

        if (Schema::hasTable('online_hosts') && Schema::hasColumn('online_hosts', 'supervisor') && Schema::hasTable('classrooms')) {
            $now = now();
            foreach (DB::table('online_hosts')->where('supervisor', true)->get(['user_id', 'school_id', 'granted_by']) as $h) {
                $rows = DB::table('classrooms')->where('school_id', $h->school_id)->where('teacher_id', '!=', $h->user_id)->pluck('id')
                    ->map(fn ($cid) => ['school_id' => $h->school_id, 'user_id' => $h->user_id, 'classroom_id' => $cid,
                        'granted_by' => $h->granted_by, 'created_at' => $now, 'updated_at' => $now])->all();
                if ($rows) {
                    DB::table('class_supervisors')->insertOrIgnore($rows);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('class_supervisors');
    }
};
