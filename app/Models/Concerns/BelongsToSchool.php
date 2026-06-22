<?php

namespace App\Models\Concerns;

use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Model;

/**
 * چندمستأجری در سطح اپلیکیشن (single-database).
 *
 * هر مدلی که این trait را داشته باشد به‌صورت خودکار فقط ردیف‌های
 * مدرسه‌ی کاربر فعلی را می‌بیند. سوپرادمین (بدون school_id) همه را می‌بیند.
 *
 * در زمان ساخت ردیف جدید نیز school_id به‌صورت خودکار پر می‌شود.
 */
trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope(new class implements Scope {
            public function apply(Builder $builder, Model $model): void
            {
                $user = auth()->user();
                if ($user && $user->school_id) {
                    $builder->where($model->getTable() . '.school_id', $user->school_id);
                }
            }
        });

        static::creating(function (Model $model) {
            if (! $model->school_id && ($user = auth()->user()) && $user->school_id) {
                $model->school_id = $user->school_id;
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
