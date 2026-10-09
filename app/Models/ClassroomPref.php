<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** تنظیماتِ هر کلاس (کلید → مقدارِ JSON): «گزارشِ هفتگی»، «سلامتِ دیجیتال» و … */
class ClassroomPref extends Model
{
    protected $fillable = ['classroom_id', 'key', 'value'];

    protected $casts = ['value' => 'array'];

    public static function ready(): bool
    {
        return \App\Support\DbSchema::hasTable('classroom_prefs');
    }

    public static function get(?int $classroomId, string $key, array $defaults = []): array
    {
        if (! $classroomId || ! self::ready()) {
            return $defaults;
        }
        $v = static::where('classroom_id', $classroomId)->where('key', $key)->value('value');
        $v = is_string($v) ? (json_decode($v, true) ?: []) : (array) $v;

        return array_replace($defaults, $v);
    }

    public static function put(int $classroomId, string $key, array $value): void
    {
        static::updateOrCreate(['classroom_id' => $classroomId, 'key' => $key], ['value' => $value]);
    }
}
