<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** کتابِ درسیِ PDF که معلم روی سرور گذاشته؛ در کلاس صفحه‌به‌صفحه باز می‌شود. */
class OnlineBook extends Model
{
    protected $fillable = ['school_id', 'teacher_id', 'title', 'path', 'size', 'pages', 'progress', 'last_page'];

    protected $casts = ['progress' => 'array'];

    public function bookPages(): HasMany { return $this->hasMany(OnlineBookPage::class, 'book_id'); }

    public static function ready(): bool
    {
        return \App\Support\DbSchema::hasTable('online_books') && \App\Support\DbSchema::hasTable('online_book_pages');
    }

    /** آخرین صفحه‌ای که برای این کلاس درس داده شده (کلید: شناسه‌ی کلاس، یا «all»). */
    public function pageFor(?int $classroomId): int
    {
        $p = $this->progress ?? [];

        return max(1, (int) ($p[(string) ($classroomId ?: 'all')] ?? $this->last_page ?? 1));
    }

    public function remember(?int $classroomId, int $page): void
    {
        $p = $this->progress ?? [];
        $p[(string) ($classroomId ?: 'all')] = $page;
        $this->forceFill(['progress' => $p, 'last_page' => $page])->save();
    }
}
