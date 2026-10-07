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

    public function teamPoints(): HasMany { return $this->hasMany(TeamPoint::class, 'batch_id'); }
    public function activity(): BelongsTo { return $this->belongsTo(ClassActivity::class, 'class_activity_id'); }
}
