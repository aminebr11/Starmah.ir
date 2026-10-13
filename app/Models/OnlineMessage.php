<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** پیامِ گفت‌وگوی کلاسِ آنلاین. */
class OnlineMessage extends Model
{
    protected $fillable = ['room_id', 'user_id', 'kind', 'body', 'hidden'];

    protected $casts = ['hidden' => 'boolean'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
