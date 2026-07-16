<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Support\Roles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** اطلاعیه/پیام مدرسه — عمومی (معلم‌ها/دانش‌آموزان/همه/یک پایه) یا شخصی. */
class Announcement extends Model
{
    use BelongsToSchool;

    public const AUDIENCES = ['teachers', 'students', 'all', 'personal'];

    protected $fillable = ['school_id', 'sender_id', 'title', 'body', 'link', 'audience', 'grade'];

    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sender_id'); }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_recipients')->withPivot('read_at')->withTimestamps();
    }

    /** اطلاعیه‌های قابل‌مشاهده برای یک کاربر (بر اساس نقش/پایه/گیرنده‌ی شخصی). */
    public function scopeForUser(Builder $q, User $user): Builder
    {
        $isTeacher = $user->hasRole(Roles::TEACHER);
        $isStudent = $user->hasRole(Roles::STUDENT);
        $grade = $isStudent ? optional($user->classrooms()->first())->grade : null;

        return $q->where(function (Builder $w) use ($user, $isTeacher, $isStudent, $grade) {
            $w->where('audience', 'all');
            if ($isTeacher) {
                $w->orWhere('audience', 'teachers');
            }
            if ($isStudent) {
                $w->orWhere(function (Builder $s) use ($grade) {
                    $s->where('audience', 'students')
                      ->where(fn (Builder $g) => $g->whereNull('grade')->orWhere('grade', $grade));
                });
            }
            // پیام شخصی که این کاربر گیرنده‌اش است
            $w->orWhereHas('recipients', fn (Builder $r) => $r->where('users.id', $user->id));
        });
    }
}
