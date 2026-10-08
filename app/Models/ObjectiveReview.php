<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** زمان‌بندیِ مرورِ فاصله‌دار (جعبه‌ی لایتنر) برای یک دانش‌آموز و یک هدفِ درسی. */
class ObjectiveReview extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'student_id', 'objective_id', 'box', 'due_at', 'attempts', 'correct', 'last_seen_at', 'best_level',
    ];

    protected $casts = ['due_at' => 'date', 'last_seen_at' => 'datetime'];

    public function objective(): BelongsTo { return $this->belongsTo(LearningObjective::class); }
}
