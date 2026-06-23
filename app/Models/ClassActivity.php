<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Support\Jalali;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassActivity extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'classroom_id', 'teacher_id', 'type', 'title',
        'description', 'points', 'scheduled_at', 'status', 'meta',
    ];

    protected $casts = ['scheduled_at' => 'datetime', 'meta' => 'array'];

    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }
    public function awards(): HasMany { return $this->hasMany(ActivityAward::class); }

    public function scheduledJalali(): string
    {
        return $this->scheduled_at ? Jalali::format($this->scheduled_at, true) : '';
    }

    public static function typeLabel(string $t): string
    {
        return [
            'game' => '🎮 بازی', 'exam' => '📝 آزمون', 'homework' => '📚 تکلیف',
            'podcast' => '🎧 پادکست', 'online_exam' => '💻 آزمون آنلاین', 'custom' => '⭐ فعالیت',
        ][$t] ?? $t;
    }
}
