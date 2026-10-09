<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveContestAnswer extends Model
{
    public $timestamps = false;

    protected $fillable = ['live_contest_id', 'student_id', 'q_index', 'choice', 'correct', 'points', 'ms', 'created_at'];

    protected $casts = ['correct' => 'boolean', 'created_at' => 'datetime'];
}
