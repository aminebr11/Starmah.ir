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
        // همه‌ی تنظیمات با یک کوئری در هر درخواست (جدولِ کوچک) — به‌جای یک کوئریِ کش برای هر کلید؛
        // روی هاست با کشِ «database» هر Cache::get خودش یک کوئری بود.
        $all = str_starts_with($key, 'bday') ? null : self::all_(); // پرچم‌های تولد در حافظه‌ی همگانی نمی‌آیند
        if ($all !== null) {
            return array_key_exists($key, $all) && $all[$key] !== null ? $all[$key] : $default;
        }
        $v = Cache::rememberForever("setting:$key", fn () => static::query()->find($key)?->value ?? self::MISSING);

        return $v === self::MISSING ? $default : $v;
    }

    /** حافظه‌ی همین درخواست (در کانتینر، تا بینِ تست‌ها/درخواست‌ها کهنه نماند؛ در پردازشِ طولانی هر ۳۰ ثانیه تازه). */
    private static function all_(): ?array
    {
        $k = self::class . '.all';
        $box = app()->bound($k) ? app($k) : null;
        if (! $box || $box->at < time() - 30) {
            $rows = rescue(fn () => static::query()->where('key', 'not like', 'bday%')->pluck('value', 'key')->all(), null, false);
            if ($rows === null) {
                return null; // جدول در دسترس نیست → همان راهِ قبلی
            }
            $box = (object) ['at' => time(), 'rows' => $rows];
            app()->instance($k, $box);
        }

        return $box->rows;
    }

    /** نشانه‌ی «ردیفی در جدول نیست» — تا null بودنِ واقعی با نبودِ ردیف اشتباه نشود. */
    private const MISSING = "\0__setting_missing__";

    public static function put(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting:$key");
        $k = self::class . '.all';
        if (app()->bound($k)) {
            app()->forgetInstance($k); // دفعه‌ی بعد دقیقاً همان چیزی که در پایگاه‌داده ذخیره شد خوانده شود
        }
    }
}
