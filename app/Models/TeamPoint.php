<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** امتیازِ دستیِ گروهی که معلم به کلِ یک تیم می‌دهد یا کم می‌کند. */
class TeamPoint extends Model
{
    protected $fillable = ['school_id', 'classroom_id', 'theme_id', 'amount', 'reason', 'awarded_by'];

    protected $casts = ['amount' => 'integer'];

    public function theme(): BelongsTo { return $this->belongsTo(Theme::class); }
    public function awardedBy(): BelongsTo { return $this->belongsTo(User::class, 'awarded_by'); }
}
