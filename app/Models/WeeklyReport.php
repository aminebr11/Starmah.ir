<?php

namespace App\Models;

use App\Support\DbSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** گزارشِ هفتگیِ یک دانش‌آموز برای والدین (+ «۱۰ دقیقه با فرزندم»). */
class WeeklyReport extends Model
{
    protected $fillable = ['school_id', 'classroom_id', 'teacher_id', 'student_id', 'week_start', 'data', 'activity', 'teacher_note', 'status', 'sent_at', 'sms_status'];

    protected $casts = ['data' => 'array', 'activity' => 'array', 'week_start' => 'date', 'sent_at' => 'datetime'];

    public static function ready(): bool
    {
        return DbSchema::hasTable('weekly_reports') && DbSchema::hasTable('classroom_prefs');
    }

    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }
}
