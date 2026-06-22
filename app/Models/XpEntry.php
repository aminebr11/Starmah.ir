<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** یک ردیف از دفترکل XP (xp_ledger) */
class XpEntry extends Model
{
    protected $table = 'xp_ledger';

    protected $fillable = [
        'student_id', 'season_id', 'amount', 'reason',
        'source_type', 'source_id', 'awarded_by',
    ];

    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }
}
