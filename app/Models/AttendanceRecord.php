<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** رکورد حضور و غیاب روزانه‌ی یک دانش‌آموز. */
class AttendanceRecord extends Model
{
    use BelongsToSchool;

    public const STATUSES = ['present', 'absent', 'late', 'excused'];

    protected $fillable = ['school_id', 'classroom_id', 'student_id', 'date', 'status', 'note', 'recorded_by'];
    protected $casts = ['date' => 'date'];

    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }
    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }
}
