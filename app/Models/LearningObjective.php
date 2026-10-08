<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** هدفِ درسی (سراسری، بدونِ داده‌ی دانش‌آموز). */
class LearningObjective extends Model
{
    protected $fillable = ['key', 'level', 'grade', 'subject', 'chapter_id', 'chapter', 'lesson_no', 'topic', 'label'];
}
