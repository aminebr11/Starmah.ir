<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** حضورِ یک نفر در یک کلاسِ آنلاین (برای فهرستِ زنده، دسترسی‌ها و گزارشِ حضور). */
class OnlineParticipant extends Model
{
    /** کسی که این‌قدر همگام نشده، «آنلاین» حساب نمی‌شود. */
    public const ONLINE_SEC = 20;

    protected $fillable = ['room_id', 'user_id', 'role', 'joined_at', 'last_seen_at', 'left_at', 'seconds', 'joins', 'hand_at',
        'can_speak', 'can_video', 'can_draw', 'kicked', 'stars', 'xp', 'media_state'];

    protected $casts = ['joined_at' => 'datetime', 'last_seen_at' => 'datetime', 'left_at' => 'datetime', 'hand_at' => 'datetime',
        'can_speak' => 'boolean', 'can_video' => 'boolean', 'can_draw' => 'boolean', 'kicked' => 'boolean'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function room(): BelongsTo { return $this->belongsTo(OnlineRoom::class, 'room_id'); }

    public function online(): bool
    {
        return ! $this->left_at && $this->last_seen_at && $this->last_seen_at->gt(now()->subSeconds(self::ONLINE_SEC));
    }
}
