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

    protected $fillable = [
        'school_id', 'teacher_id', 'classroom_id', 'theme_id', 'title', 'type', 'resource_id', 'subject', 'lesson_no',
        'difficulty', 'question_count', 'xp_reward', 'badge_name', 'badge_icon', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }
    public function completions(): HasMany { return $this->hasMany(MissionCompletion::class); }
}
