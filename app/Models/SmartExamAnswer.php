<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SmartExamAnswer extends Model
{
    protected $fillable = ['attempt_id', 'question_id', 'q_index', 'value', 'correct', 'awarded', 'time_spent'];
    protected $casts = ['value' => 'array', 'correct' => 'boolean'];
}
