<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * یک «دنیای علاقه». ساختار سه‌لایه در ستون‌های skin/narrative/content_pools.
 */
class Theme extends Model
{
    protected $fillable = [
        'key', 'name', 'emoji', 'skin', 'narrative',
        'content_pools', 'is_active', 'is_premium', 'sort',
    ];

    protected $casts = [
        'skin'          => 'array',
        'narrative'     => 'array',
        'content_pools' => 'array',
        'is_active'     => 'boolean',
        'is_premium'    => 'boolean',
    ];

    /** واژه‌ی روایت را با کلید برمی‌گرداند (مثلاً xp_unit → «گل») */
    public function word(string $key, string $default = ''): string
    {
        return data_get($this->narrative, $key, $default);
    }
}
