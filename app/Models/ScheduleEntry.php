<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class ScheduleEntry extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'classroom_id', 'day_of_week', 'specific_date', 'period', 'title', 'time_range', 'note', 'start_time', 'end_time', 'kind'];

    protected $casts = ['specific_date' => 'date'];
}
