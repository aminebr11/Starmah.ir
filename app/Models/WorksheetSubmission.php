<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** کاربرگِ پرشده که دانش‌آموز برای معلم می‌فرستد (عکس/فایل). */
class WorksheetSubmission extends Model
{
    protected $fillable = ['worksheet_id', 'student_id', 'file_path', 'note', 'submitted_at'];

    protected $casts = ['submitted_at' => 'datetime'];

    public function worksheet(): BelongsTo { return $this->belongsTo(Worksheet::class); }
    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }
}
