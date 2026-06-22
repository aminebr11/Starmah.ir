<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** پل والدین: پیام دوسویه‌ی معلم/والد حول یک دانش‌آموز */
class Message extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'sender_id', 'recipient_id', 'student_id',
        'kind', 'body', 'read_at',
    ];

    protected $casts = ['read_at' => 'datetime'];

    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sender_id'); }
    public function recipient(): BelongsTo { return $this->belongsTo(User::class, 'recipient_id'); }
}
