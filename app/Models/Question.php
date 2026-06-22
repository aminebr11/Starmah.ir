<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    protected $fillable = [
        'skill_id', 'type', 'difficulty', 'template',
        'variables', 'options', 'answer_expr', 'xp', 'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'options'   => 'array',
        'is_active' => 'boolean',
    ];

    public function skill(): BelongsTo { return $this->belongsTo(Skill::class); }
}
