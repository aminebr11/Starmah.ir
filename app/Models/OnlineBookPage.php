<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** یک صفحه از کتاب: تصویرِ ساخته‌شده و نوشته‌های معلم روی آن. */
class OnlineBookPage extends Model
{
    protected $fillable = ['book_id', 'page', 'bg', 'ops', 'version'];
}
