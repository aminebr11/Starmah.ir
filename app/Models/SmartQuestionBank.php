<?php
namespace App\Models;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class SmartQuestionBank extends Model
{
    use BelongsToSchool;
    protected $table = 'smart_question_bank';
    protected $fillable = [
        'school_id', 'teacher_id', 'scope', 'level', 'type', 'prompt', 'choices', 'answer', 'explanation',
        'grade', 'subject', 'lesson_no', 'book', 'chapter', 'topic', 'goal', 'difficulty', 'points', 'time_limit',
        'tags', 'media_path', 'source', 'approval', 'used_count', 'correct_pct', 'version',
    ];
    protected $casts = ['choices' => 'array', 'answer' => 'array'];

    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }

    public function school(): BelongsTo { return $this->belongsTo(School::class); }
}
