<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduGameQuestion extends Model
{
    protected $fillable = [
        'edu_game_id', 'type', 'prompt', 'media_path', 'choices', 'answer',
        'hint1', 'hint2', 'explanation', 'points', 'penalty', 'time_limit', 'tags', 'sort',
    ];
    protected $casts = ['choices' => 'array', 'answer' => 'array'];

    public function game(): BelongsTo { return $this->belongsTo(EduGame::class, 'edu_game_id'); }
}
