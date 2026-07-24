<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** پیامِ محرمانه‌ی بخشِ والدین (معلم/مدیر ↔ والدِ دانش‌آموز). */
class ParentNote extends Model
{
    protected $fillable = ['school_id', 'student_id', 'sender_id', 'from_parent', 'title', 'body', 'read_at'];

    protected $casts = ['from_parent' => 'boolean', 'read_at' => 'datetime'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
