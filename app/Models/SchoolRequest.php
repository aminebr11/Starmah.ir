<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolRequest extends Model
{
    protected $fillable = [
        'school_name', 'manager_name', 'manager_phone', 'manager_email',
        'city', 'classes_count', 'note', 'status', 'school_id', 'reviewed_by',
    ];

    public function school(): BelongsTo { return $this->belongsTo(School::class); }
}
