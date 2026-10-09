<?php

namespace App\Models;

use App\Support\DbSchema;
use Illuminate\Database\Eloquent\Model;

/** زمانِ فعالِ روزانه‌ی دانش‌آموز (سلامتِ دیجیتال). */
class ScreenTime extends Model
{
    protected $fillable = ['student_id', 'day', 'seconds', 'session_seconds', 'last_beat_at', 'resets'];

    protected $casts = ['day' => 'date', 'last_beat_at' => 'datetime', 'seconds' => 'integer', 'session_seconds' => 'integer', 'resets' => 'integer'];

    public static function ready(): bool
    {
        return DbSchema::hasTable('screen_times');
    }
}
