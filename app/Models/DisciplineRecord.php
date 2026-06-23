<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Support\Jalali;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineRecord extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'student_id', 'classroom_id', 'topic_id', 'recorded_by',
        'type', 'title', 'points', 'note',
    ];

    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }
    public function topic(): BelongsTo { return $this->belongsTo(DisciplineTopic::class, 'topic_id'); }

    public function jalaliDate(): string
    {
        return Jalali::format($this->created_at, true);
    }
}
