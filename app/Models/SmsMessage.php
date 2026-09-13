<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** یک پیامکِ ارسال‌شده — هم گزارشِ مصرف از اینجاست، هم سابقه. */
class SmsMessage extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'sender_id', 'recipient_id', 'phone', 'body',
        'kind', 'status', 'provider_id', 'error', 'segments',
    ];

    protected $casts = ['segments' => 'integer'];

    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sender_id'); }
    public function recipient(): BelongsTo { return $this->belongsTo(User::class, 'recipient_id'); }
    public function school(): BelongsTo { return $this->belongsTo(School::class); }
}
