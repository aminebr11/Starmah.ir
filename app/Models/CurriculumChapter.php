<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * فصلِ یک کتابِ درسی. school_id خالی = فصلِ سراسری (ادمین کل)،
 * پر = فصلی که یک مدرسه برای خودش اضافه کرده.
 */
class CurriculumChapter extends Model
{
    protected $fillable = [
        'curriculum_book_id', 'school_id', 'level', 'grade', 'subject', 'number', 'title',
        'lessons', 'created_by', 'is_active',
    ];

    protected $casts = ['lessons' => 'array', 'is_active' => 'boolean', 'number' => 'integer'];

    /** فصل‌هایی که یک مدرسه می‌بیند: سراسری + فصل‌های خودِ همان مدرسه. */
    public function scopeVisibleTo(Builder $q, ?int $schoolId): Builder
    {
        return $q->where('is_active', true)
            ->where(fn ($w) => $w->whereNull('school_id')->when($schoolId, fn ($x) => $x->orWhere('school_id', $schoolId)));
    }

    /** «فصل ۲ — کسر» */
    public function label(): string
    {
        return 'فصل ' . \App\Support\Jalali::fa((string) $this->number) . ' — ' . $this->title;
    }
}
