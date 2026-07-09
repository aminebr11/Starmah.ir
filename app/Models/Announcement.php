<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** اطلاعیه‌ی مدرسه — از مدیر به معلم‌ها/دانش‌آموزان (کل مدرسه یا یک پایه). */
class Announcement extends Model
{
    use BelongsToSchool;

    public const AUDIENCES = ['teachers', 'students', 'all'];

    protected $fillable = ['school_id', 'sender_id', 'title', 'body', 'audience', 'grade'];

    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sender_id'); }
}
