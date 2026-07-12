<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmartExam extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'teacher_id', 'title', 'description', 'grade', 'subject', 'book',
        'chapter', 'topic', 'goal', 'kind', 'status', 'adaptive', 'rules',
        'opens_at', 'closes_at', 'version',
    ];
    protected $casts = ['rules' => 'array', 'adaptive' => 'boolean', 'opens_at' => 'datetime', 'closes_at' => 'datetime'];

    public function questions(): HasMany { return $this->hasMany(SmartExamQuestion::class)->orderBy('sort'); }
    public function targets(): HasMany { return $this->hasMany(SmartExamTarget::class); }
    public function attempts(): HasMany { return $this->hasMany(SmartExamAttempt::class); }
    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }

    public function isLive(): bool
    {
        if ($this->status !== 'published') return false;
        $now = now();
        if ($this->opens_at && $now->lessThan($this->opens_at)) return false;
        if ($this->closes_at && $now->greaterThan($this->closes_at)) return false;
        return true;
    }
}
