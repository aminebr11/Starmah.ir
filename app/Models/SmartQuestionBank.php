<?php
namespace App\Models;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
class SmartQuestionBank extends Model
{
    use BelongsToSchool;
    protected $table = 'smart_question_bank';
    protected $fillable = [
        'school_id', 'teacher_id', 'scope', 'type', 'prompt', 'choices', 'answer', 'explanation',
        'grade', 'subject', 'book', 'chapter', 'topic', 'goal', 'difficulty', 'points', 'time_limit',
        'tags', 'media_path', 'source', 'approval', 'used_count', 'correct_pct', 'version',
    ];
    protected $casts = ['choices' => 'array', 'answer' => 'array'];
}
