<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradeColumn extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'classroom_id', 'teacher_id', 'title', 'type', 'max', 'graded_at'];
    protected $casts = ['graded_at' => 'date'];

    public function grades(): HasMany { return $this->hasMany(Grade::class); }
}
