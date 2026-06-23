<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grade extends Model
{
    protected $fillable = ['grade_column_id', 'student_id', 'score', 'text'];

    public function gradeColumn(): BelongsTo { return $this->belongsTo(GradeColumn::class); }
}
