<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * مجوزِ دسترسیِ یک مدرسه به بانکِ سؤالِ سراسری، محدود به مقطع/کلاس/درس (هرکدام NULL یعنی «همه»).
 */
class BankShare extends Model
{
    protected $fillable = ['school_id', 'level', 'grade', 'subject'];

    public function school(): BelongsTo { return $this->belongsTo(School::class); }
}
