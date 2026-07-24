<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Support\Jalali;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * خروجی‌های چاپیِ رسمی (A4) — کارنامه‌ی دانش‌آموز، گزارشِ کلاس، فهرستِ مدرسه.
 * صفحه‌ی تمیزِ قابلِ‌چاپ که با «چاپ»ِ مرورگر مستقیم PDF هم می‌شود.
 */
class PrintController extends Controller
{
    /** هدرِ مشترک: مدرسه + تاریخ. */
    private function head(?int $schoolId): array
    {
        $school = $schoolId ? \App\Models\School::find($schoolId) : null;

        return [
            'school_name' => $school?->name,
            'school_city' => $school?->city,
            'school_logo' => $school?->logo ? Storage::url($school->logo) : null,
            'today'       => Jalali::format(now()),
        ];
    }

    /** آیا کاربرِ جاری اجازه‌ی دیدنِ کارنامه‌ی این دانش‌آموز را دارد؟ */
    private function canSeeStudent(User $viewer, User $student): bool
    {
        if ($viewer->id === $student->id) return true;
        if ($viewer->hasRole(Roles::SUPER_ADMIN)) return true;
        if ($viewer->hasRole(Roles::SCHOOL_ADMIN) && $viewer->school_id === $student->school_id) return true;
        if ($viewer->hasRole(Roles::TEACHER)) {
            return $student->classrooms()->where('teacher_id', $viewer->id)->exists();
        }
        if ($viewer->hasRole(Roles::PARENT)) {
            return $viewer->children()->where('users.id', $student->id)->exists();
        }

        return false;
    }

    /** کارنامه‌ی کاملِ یک دانش‌آموز (مشخصات + سرپرست + تحلیل + امضا). */
    public function student(Request $request, User $user, AnalyticsService $analytics): View
    {
        abort_unless($this->canSeeStudent($request->user(), $user), 403);

        $report = $analytics->childReport($user);
        $guardian = data_get($user->settings, 'guardian', []);

        return view('print.student', [
            ...$this->head($user->school_id),
            'title'   => 'کارنامه‌ی جامعِ دانش‌آموز',
            'student' => [
                'name'        => $user->name,
                'national_id' => $user->national_id,
                'birth'       => $user->birth_date ? Jalali::format($user->birth_date) : null,
                'grade'       => $user->grade,
                'gender'      => data_get($user->settings, 'gender'),
                'phone'       => $user->phone,
                'father'      => $guardian['father_name'] ?? null,
                'mother'      => $guardian['mother_name'] ?? null,
                'g_phone'     => $guardian['phone'] ?? null,
                'address'     => $guardian['address'] ?? null,
            ],
            'r' => $report,
        ]);
    }

    /** گزارشِ کاملِ کلاسِ معلم (جدولِ همه‌ی دانش‌آموزان + تیم‌ها + ترکیب). */
    public function classroom(Request $request, AnalyticsService $analytics): View
    {
        $teacher = $request->user();
        abort_unless($teacher->hasRole(Roles::TEACHER), 403);
        $classroom = Classroom::where('teacher_id', $teacher->id)->firstOrFail();

        return view('print.classroom', [
            ...$this->head($teacher->school_id),
            'title'     => 'گزارشِ عملکردِ کلاس',
            'classname' => $classroom->name,
            'teacher'   => $teacher->name,
            'report'    => $analytics->classroomReport($classroom),
        ]);
    }

    /** فهرستِ کاملِ دانش‌آموزانِ مدرسه با مشخصات و سرپرست (مدیرِ مدرسه). */
    public function roster(Request $request): View
    {
        $admin = $request->user();
        abort_unless($admin->hasRole(Roles::SCHOOL_ADMIN) || $admin->hasRole(Roles::SUPER_ADMIN), 403);

        $students = User::role(Roles::STUDENT)->where('school_id', $admin->school_id)
            ->with('theme:id,name,emoji')->orderBy('name')->get()
            ->map(function ($s) {
                $class = $s->classrooms()->with('teacher:id,name')->first();
                $g = data_get($s->settings, 'guardian', []);
                // سازگاری با داده‌های قدیمی که guardian_name مستقیم در settings بود
                $legacy = $s->settings ?? [];

                return [
                    'name'        => $s->name,
                    'national_id' => $s->national_id,
                    'birth'       => $s->birth_date ? Jalali::format($s->birth_date) : null,
                    'grade'       => $s->grade,
                    'phone'       => $s->phone,
                    'class'       => $class?->name,
                    'teacher'     => $class?->teacher?->name,
                    'team'        => $s->theme ? "{$s->theme->emoji} {$s->theme->name}" : null,
                    'guardian'    => $g['father_name'] ?? $g['mother_name'] ?? ($legacy['guardian_name'] ?? null),
                    'g_phone'     => $g['phone'] ?? ($legacy['guardian_phone'] ?? null),
                    'xp'          => $s->totalXp(),
                ];
            });

        return view('print.roster', [
            ...$this->head($admin->school_id),
            'title'    => 'فهرستِ کاملِ دانش‌آموزان',
            'students' => $students,
        ]);
    }
}
