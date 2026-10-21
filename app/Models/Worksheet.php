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
        'theme', 'mode', 'spec', 'questions', 'render_html', 'image_path', 'file_path', 'files', 'is_published', 'published_at',
    ];

    protected $casts = [
        'questions' => 'array',
        'files' => 'array',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }
    public function submissions() { return $this->hasMany(WorksheetSubmission::class); }

    /** همه‌ی فایل‌های کاربرگِ بارگذاری‌شده، به ترتیبِ صفحه (کاربرگ‌های قدیمی: همان یک فایل). */
    public function pagePaths(): array
    {
        $files = array_values(array_filter((array) ($this->files ?? []), 'is_string'));

        return $files ?: ($this->file_path ? [$this->file_path] : []);
    }

    /** داده‌ی صفحه‌ها برای نمایش: [{n, url, pdf, image, ext}] */
    public function pagesData(): array
    {
        return array_map(function ($p, $n) {
            $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));

            return [
                'n' => $n + 1,
                'url' => route('worksheet.sheet', [$this->id, 'file', $n], false),
                'pdf' => $ext === 'pdf',
                'image' => in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true),
                'ext' => $ext,
            ];
        }, $this->pagePaths(), array_keys($this->pagePaths()));
    }
}
