<?php

namespace App\Models;

use App\Support\Roles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    protected $fillable = [
        'school_id', 'name', 'phone', 'email', 'password', 'avatar',
        'theme_id', 'grade', 'national_id', 'is_active', 'settings',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'settings'          => 'array',
        ];
    }

    // ---------- روابط ----------

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    /** کلاس‌هایی که این کاربر (به‌عنوان دانش‌آموز) عضو آن‌هاست */
    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'classroom_student', 'student_id', 'classroom_id')
            ->withPivot('joined_at');
    }

    /** کلاس‌هایی که این کاربر (به‌عنوان معلم) تدریس می‌کند */
    public function teachingClassrooms(): HasMany
    {
        return $this->hasMany(Classroom::class, 'teacher_id');
    }

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'parent_student', 'parent_id', 'student_id')
            ->withPivot('relation');
    }

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'parent_student', 'student_id', 'parent_id')
            ->withPivot('relation');
    }

    public function xpEntries(): HasMany
    {
        return $this->hasMany(XpEntry::class, 'student_id');
    }

    public function skillMastery(): HasMany
    {
        return $this->hasMany(SkillMastery::class, 'student_id');
    }

    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'student_badges', 'student_id', 'badge_id')
            ->withPivot('awarded_at');
    }

    // ---------- کمک‌کننده‌ها ----------

    /** مجموع XP (در صورت نیاز قابل محدود کردن به یک فصل) */
    public function totalXp(?int $seasonId = null): int
    {
        return (int) $this->xpEntries()
            ->when($seasonId, fn ($q) => $q->where('season_id', $seasonId))
            ->sum('amount');
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Roles::SUPER_ADMIN);
    }

    public function isStudent(): bool
    {
        return $this->hasRole(Roles::STUDENT);
    }
}
