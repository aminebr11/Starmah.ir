<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** یک صفحه‌ی تخته‌ی کلاسِ آنلاین: زمینه (ساده، شطرنجی، خط‌دار، اسلاید) و خط‌ها/شکل‌ها. */
class OnlineBoardPage extends Model
{
    protected $fillable = ['room_id', 'page', 'bg', 'ops', 'version'];
}
