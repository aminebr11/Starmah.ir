<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineRecord extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'student_id', 'classroom_id', 'recorded_by',
        'type', 'points', 'note',
    ];

    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }
}
