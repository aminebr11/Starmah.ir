<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** قالب/مکانیک بازی (مار و پله، فوتبال، گنج‌یابی، ...) — مستقل از تم. */
class GameTemplate extends Model
{
    protected $fillable = ['key', 'name', 'description', 'icon', 'config', 'board_html', 'board_css', 'is_active', 'sort'];
    protected $casts = ['config' => 'array', 'is_active' => 'boolean'];
}
