<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** دفترِ کارهای انجام‌شده از بات (چه کسی، چه کاری، کِی) + اطلاعاتِ «برگردان». */
class BotLog extends Model
{
    protected $fillable = ['user_id', 'school_id', 'platform', 'action', 'summary', 'undo', 'undone_at'];

    protected $casts = ['undo' => 'array', 'undone_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
