<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * طرح اشتراک مدرسه. null در محدودیت‌ها = نامحدود.
 */
class Plan extends Model
{
    protected $fillable = [
        'key', 'name', 'description', 'max_classes', 'max_students_per_class',
        'duration_days', 'price', 'is_active', 'sort',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function schools(): HasMany { return $this->hasMany(School::class); }

    public function isUnlimitedClasses(): bool { return $this->max_classes === null; }
    public function isUnlimitedStudents(): bool { return $this->max_students_per_class === null; }
}
