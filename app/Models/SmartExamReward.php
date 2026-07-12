<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SmartExamReward extends Model
{
    protected $fillable = ['attempt_id', 'student_id', 'xp'];
}
