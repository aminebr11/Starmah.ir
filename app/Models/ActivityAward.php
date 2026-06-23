<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityAward extends Model
{
    protected $fillable = ['class_activity_id', 'student_id', 'points', 'awarded_by'];

    public function activity(): BelongsTo { return $this->belongsTo(ClassActivity::class, 'class_activity_id'); }
    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }
}
