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
        'school_id', 'teacher_id', 'scope', 'title', 'subject', 'grade',
        'theme', 'spec', 'questions', 'render_html',
    ];

    protected $casts = [
        'questions' => 'array',
    ];

    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
}
