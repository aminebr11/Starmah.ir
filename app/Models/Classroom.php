<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Classroom extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'teacher_id', 'name', 'grade', 'join_code', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'classroom_student', 'classroom_id', 'student_id')
            ->withPivot('joined_at');
    }

    /**
     * درس‌های این کلاس از بانک درس‌های همان مقطع/پایه (منبع واحد برای همه‌ی بخش‌ها).
     * @return array<int, array{name:string, icon:?string}>
     */
    public function subjectNames(): array
    {
        $level = $this->school?->level;
        if (! $this->grade || ! $level) {
            return [];
        }
        return CurriculumBook::where('level', $level)->where('grade', $this->grade)->where('is_active', true)
            ->orderBy('sort')->get()
            ->map(fn ($b) => ['name' => $b->name, 'icon' => $b->icon])->values()->all();
    }
}
