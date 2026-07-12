<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class SmartExamQuestion extends Model
{
    protected $fillable = [
        'smart_exam_id', 'bank_id', 'type', 'prompt', 'choices', 'answer', 'explanation',
        'goal', 'difficulty', 'points', 'time_limit', 'topic', 'tags', 'media_path', 'source', 'sort',
    ];
    protected $casts = ['choices' => 'array', 'answer' => 'array'];
    public function exam(): BelongsTo { return $this->belongsTo(SmartExam::class, 'smart_exam_id'); }
}
