<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** تراکنشِ پرداختِ اشتراکِ مدرسه. */
class PaymentTransaction extends Model
{
    protected $fillable = [
        'gateway', 'amount', 'purpose', 'plan_id', 'school_request_id', 'school_id',
        'payer_name', 'payer_phone', 'authority', 'ref_id', 'status', 'meta',
    ];

    protected $casts = ['meta' => 'array'];
}
