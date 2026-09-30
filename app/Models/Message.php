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
        'kind', 'body', 'read_at', 'edited_at',
    ];

    protected $casts = ['read_at' => 'datetime', 'edited_at' => 'datetime'];

    protected static function booted(): void
    {
        // پیامِ تازه برای ادمینِ کل ← پیامک به موبایلِ خودش
        static::created(function (Message $m) {
            $to = $m->recipient;
            if ($to && $to->hasRole(\App\Support\Roles::SUPER_ADMIN)) {
                $from = $m->sender?->name ?: 'یک کاربر';
                \App\Support\AdminAlert::send('message', "💬 پیامِ تازه از {$from}:\n" . mb_substr((string) $m->body, 0, 160));
            }
        });
    }

    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sender_id'); }
    public function recipient(): BelongsTo { return $this->belongsTo(User::class, 'recipient_id'); }
}
