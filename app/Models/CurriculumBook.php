<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** درس/کتاب یک پایه (سراسری — مدیریت توسط ادمین کل). */
class CurriculumBook extends Model
{
    protected $fillable = ['level', 'grade', 'name', 'icon', 'sort', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
}
