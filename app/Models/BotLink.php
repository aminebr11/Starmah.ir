<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** اتصالِ یک گفتگوی بات (بله/تلگرام) به یک حسابِ سایت. */
class BotLink extends Model
{
    protected $fillable = ['user_id', 'school_id', 'platform', 'chat_id', 'username', 'brief', 'notify', 'brief_on', 'notified_at', 'last_seen_at'];

    protected $casts = ['brief' => 'boolean', 'notify' => 'boolean', 'brief_on' => 'date', 'notified_at' => 'datetime', 'last_seen_at' => 'datetime'];

    public const PLATFORMS = ['bale' => 'بله', 'telegram' => 'تلگرام'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public static function ready(): bool
    {
        return \App\Support\DbSchema::hasTable('bot_links') && \App\Support\DbSchema::hasTable('bot_logs');
    }
}
