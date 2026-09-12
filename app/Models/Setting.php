<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** تنظیمات کلید-مقدارِ سراسری (کلید API هوش مصنوعی و…). */
class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    /**
     * خواندنِ یک تنظیم.
     *
     * نکته‌ی مهم: مقدارِ **ذخیره‌شده** کش می‌شود، نه پیش‌فرض. پیش از این
     * پیش‌فرض هم داخلِ کش می‌رفت، پس اگر جایی از کد پیش‌فرضِ دیگری می‌داد
     * (یا پیش‌فرض در نسخه‌ی تازه عوض می‌شد) همان مقدارِ قدیمیِ کش‌شده
     * برمی‌گشت و تنظیمِ تازه هیچ‌وقت اثر نمی‌کرد.
     */
    public static function get(string $key, $default = null)
    {
        $v = Cache::rememberForever("setting:$key", fn () => static::query()->find($key)?->value ?? self::MISSING);

        return $v === self::MISSING ? $default : $v;
    }

    /** نشانه‌ی «ردیفی در جدول نیست» — تا null بودنِ واقعی با نبودِ ردیف اشتباه نشود. */
    private const MISSING = "\0__setting_missing__";

    public static function put(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting:$key");
    }
}
