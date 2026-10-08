<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

/** یک پاسخِ دانش‌آموز به یک سؤال — شاهدِ تسلط. */
class PracticeAnswer extends Model
{
    use BelongsToSchool;

    public const UPDATED_AT = null;

    protected $fillable = [
        'school_id', 'student_id', 'objective_id', 'bank_id', 'source', 'source_id',
        'correct', 'first_try', 'hinted',
    ];

    protected $casts = ['correct' => 'boolean', 'first_try' => 'boolean', 'hinted' => 'boolean'];
}
