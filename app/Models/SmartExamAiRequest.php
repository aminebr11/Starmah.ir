<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SmartExamAiRequest extends Model
{
    protected $fillable = ['school_id', 'teacher_id', 'provider', 'model', 'subject', 'requested', 'produced', 'ok', 'error'];
    protected $casts = ['ok' => 'boolean'];
}
