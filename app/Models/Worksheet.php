<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * کاربرگِ موضوعیِ تولیدشده با هوش مصنوعی — در بانکِ کاربرگ‌ها نگهداری می‌شود.
 * قواعدِ دسترسی مانند بانک سؤالات (teacher|school|global) با WorksheetAccess.
 */
class Worksheet extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'teacher_id', 'classroom_id', 'scope', 'level', 'title', 'subject', 'lesson_no', 'grade',
        'theme', 'spec', 'questions', 'render_html', 'image_path', 'is_published', 'published_at',
    ];

    protected $casts = [
        'questions' => 'array',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }
    public function submissions() { return $this->hasMany(WorksheetSubmission::class); }
}
