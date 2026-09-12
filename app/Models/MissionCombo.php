<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * «جعبه‌ی گنجِ روزانه» — پاداشی که دانش‌آموز با تمام‌کردنِ همه‌ی
 * مأموریت‌های یک روز می‌گیرد. برای هر (دانش‌آموز، روز) فقط یک‌بار.
 */
class MissionCombo extends Model
{
    protected $fillable = ['student_id', 'play_date', 'missions_done', 'xp_awarded', 'streak'];

    protected $casts = ['play_date' => 'date'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
