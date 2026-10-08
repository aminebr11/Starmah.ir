<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** یک نوبتِ امتیازدهیِ معلم به یک یا چند دانش‌آموز و/یا تیم. */
class PointBatch extends Model
{
    protected $fillable = ['school_id', 'teacher_id', 'classroom_id', 'class_activity_id', 'amount', 'reason', 'category',
        'team_mode', 'target_label', 'students_count', 'teams_count'];

    protected $casts = ['amount' => 'integer', 'students_count' => 'integer', 'teams_count' => 'integer'];

    public function entries(): HasMany
    {
        return $this->hasMany(XpEntry::class, 'source_id')->where('source_type', self::class);
    }

    private static ?bool $ready = null;

    /**
     * جدول‌ها/ستون‌های «نوبتِ امتیاز» آماده‌اند؟ اگر مایگریشنِ به‌روزرسانی روی هاست
     * اجرا نشده باشد، همان یک مایگریشن (افزایشی و تکرارپذیر) خودکار اجرا می‌شود تا
     * «مرکزِ امتیاز» به‌جای خطای ۵۰۰ کار کند. اگر هاست اجازه ندهد، false برمی‌گردد.
     */
    public static function ready(): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }
        $check = fn () => \App\Support\DbSchema::hasTable('point_batches')
            && \App\Support\DbSchema::hasColumn('team_points', 'batch_id')
            && \App\Support\DbSchema::hasColumn('activity_awards', 'batch_id');
        if (rescue($check, false, false)) {
            return self::$ready = true;
        }
        rescue(fn () => \Illuminate\Support\Facades\Artisan::call('migrate', [
            '--force' => true,
            '--path' => 'database/migrations/2026_10_08_010000_create_point_batches.php',
        ]), null, true);

        return self::$ready = (bool) rescue($check, false, false);
    }

    public function teamPoints(): HasMany { return $this->hasMany(TeamPoint::class, 'batch_id'); }
    public function activity(): BelongsTo { return $this->belongsTo(ClassActivity::class, 'class_activity_id'); }
}
