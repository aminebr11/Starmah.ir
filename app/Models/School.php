<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    protected $fillable = [
        'name', 'slug', 'city', 'level', 'logo', 'plan', 'plan_id', 'status',
        'seats', 'subscription_ends_at', 'branding', 'settings',
    ];

    protected $casts = [
        'branding' => 'array',
        'settings' => 'array',
        'subscription_ends_at' => 'date',
    ];

    public function planModel(): BelongsTo { return $this->belongsTo(Plan::class, 'plan_id'); }
    public function users(): HasMany { return $this->hasMany(User::class); }
    public function classrooms(): HasMany { return $this->hasMany(Classroom::class); }
    public function students(): HasMany
    {
        return $this->hasMany(User::class)->whereHas('roles', fn ($q) => $q->where('name', 'student'));
    }

    /** آیا اشتراک منقضی شده است؟ (null یعنی بدون انقضا) */
    public function isExpired(): bool
    {
        return $this->subscription_ends_at !== null && $this->subscription_ends_at->isPast();
    }

    /** آیا می‌توان کلاس جدید ساخت؟ (با توجه به محدودیت طرح) */
    public function canAddClassroom(): bool
    {
        $max = $this->planModel?->max_classes;
        return $max === null || $this->classrooms()->count() < $max;
    }

    /** حداکثر دانش‌آموز مجاز در هر کلاس (null = نامحدود) */
    public function maxStudentsPerClass(): ?int
    {
        return $this->planModel?->max_students_per_class;
    }
}
