<?php

namespace App\Models;

use App\Support\Roles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** یک ردیف از سوابق تغییرات. */
class AuditLog extends Model
{
    protected $fillable = ['school_id', 'actor_id', 'actor_name', 'actor_role', 'action', 'summary'];

    private const ROLE_FA = [
        Roles::SUPER_ADMIN => 'ادمین کل', Roles::SCHOOL_ADMIN => 'مدیر مدرسه',
        Roles::TEACHER => 'معلم', Roles::STUDENT => 'دانش‌آموز',
    ];

    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }

    /** ثبتِ یک تغییر توسط کاربر. */
    public static function record(?User $actor, string $action, string $summary): void
    {
        $role = $actor?->roles->pluck('name')->first();
        static::create([
            'school_id'   => $actor?->school_id,
            'actor_id'    => $actor?->id,
            'actor_name'  => $actor?->name,
            'actor_role'  => self::ROLE_FA[$role] ?? 'کاربر',
            'action'      => $action,
            'summary'     => $summary,
        ]);
    }
}
