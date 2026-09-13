<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تنظیمِ پیامکِ یک دانش‌آموز — که معلمِ کلاس آن را می‌گذارد.
 *
 * نبودِ ردیف یعنی «پیش‌فرضِ مدرسه»؛ پس این جدول فقط استثناها را نگه
 * می‌دارد و مدرسه‌های موجود بدونِ هیچ تغییری مثلِ قبل کار می‌کنند.
 */
class StudentSmsSetting extends Model
{
    protected $fillable = [
        'school_id', 'student_id', 'updated_by',
        'enabled', 'to_parent', 'to_student', 'events', 'phone_override', 'note',
    ];

    protected $casts = [
        'enabled'    => 'boolean',
        'to_parent'  => 'boolean',
        'to_student' => 'boolean',
        'events'     => 'array',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * آیا این رویداد برای این دانش‌آموز اجازه دارد؟
     *
     * events = null یعنی «هر رویدادی که مدرسه روشن کرده»؛ آرایه یعنی
     * فقط همان‌ها. این‌طور معلم می‌تواند برای یک خانواده فقط «غیبت» را
     * باز بگذارد و بقیه را ببندد.
     */
    public function allowsEvent(string $key): bool
    {
        if (! $this->enabled) {
            return false;
        }

        return $this->events === null || in_array($key, $this->events, true);
    }
}
