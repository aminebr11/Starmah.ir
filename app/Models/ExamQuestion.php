<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

/** یک سؤال در بانک سؤالات آزمون. */
class ExamQuestion extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'teacher_id', 'type', 'lesson', 'grade', 'prompt', 'choices'];

    protected $casts = ['choices' => 'array'];
}
