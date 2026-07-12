<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SmartExamTarget extends Model
{
    protected $fillable = ['smart_exam_id', 'classroom_id', 'theme_id', 'student_id'];
}
