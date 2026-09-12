<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** مأموریتِ روزانه‌ی تعریف‌شده توسط معلم (سؤال‌ها از بانکِ سؤالِ معلم). */
class Mission extends Model
{
    use BelongsToSchool;

    /** انواعِ مأموریت — هرکدام به یک بخشِ موجودِ سامانه وصل است. */
    public const TYPES = ['quiz', 'podcast', 'video', 'material', 'worksheet', 'game', 'exam'];

    /** انواعی که به یک «فعالیت» وصل‌اند و جایزه پس از انجامِ واقعی داده می‌شود. */
    public const ACTIVITY_TYPES = ['podcast', 'video', 'material', 'worksheet', 'game', 'exam'];

    protected $fillable = [
        'school_id', 'teacher_id', 'classroom_id', 'theme_id', 'title', 'description', 'type', 'resource_id',
        'subject', 'lesson_no', 'difficulty', 'question_ids', 'question_count', 'pass_percent',
        'xp_reward', 'badge_name', 'badge_icon', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean', 'question_ids' => 'array'];

    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }
    public function completions(): HasMany { return $this->hasMany(MissionCompletion::class); }
}
