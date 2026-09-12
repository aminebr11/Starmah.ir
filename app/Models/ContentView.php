<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** بازدید/گوش‌دادنِ دانش‌آموز به محتوای کلاس. */
class ContentView extends Model
{
    protected $fillable = [
        'class_content_id', 'student_id', 'seconds', 'viewed', 'xp_awarded',
        'covered', 'verified_seconds', 'max_position', 'last_ping_at', 'completed_at',
    ];

    protected $casts = [
        'viewed' => 'boolean',
        'covered' => 'array',
        'last_ping_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function content(): BelongsTo { return $this->belongsTo(ClassContent::class, 'class_content_id'); }
    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }
}
