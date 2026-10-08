<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** انجامِ «مرورِ امروز» — یک‌بار در روز. */
class ReviewCompletion extends Model
{
    protected $fillable = ['student_id', 'play_date', 'score', 'total', 'xp_awarded'];

    protected $casts = ['play_date' => 'date'];
}
