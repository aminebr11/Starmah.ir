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
    /** هدرِ مشترک: مدرسه + تاریخ + لینکِ بازگشت + جهتِ صفحه. */
    private function head(?int $schoolId, Request $request = null): array
    {
        $school = $schoolId ? \App\Models\School::find($schoolId) : null;
        $orient = $request && $request->query('orient') === 'landscape' ? 'landscape' : 'portrait';

        return [
            'school_name' => $school?->name,
            'school_city' => $school?->city,
            'school_logo' => $school?->logo ? Storage::url($school->logo) : null,
            'today'       => Jalali::format(now()),
            'back'        => $request ? ($request->query('back') ?: url()->previous()) : url('/'),
            'orient'      => $orient,
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
            ...$this->head($user->school_id, $request),
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

    /**
     * شناسنامه‌ی دانش‌آموز — همان اطلاعاتی که هنگامِ ثبت‌نام گرفته شده،
     * به‌همراه عکس، در یک برگه‌ی A4 برای پرونده‌ی مدرسه.
     */
    public function studentCard(Request $request, User $user): View
    {
        abort_unless($this->canSeeStudent($request->user(), $user), 403);

        $viewer = $request->user();
        $class = $user->classrooms()->with('teacher:id,name')->first();
        $settings = $user->settings ?? [];
        $g = $settings['guardian'] ?? [];
        $user->loadMissing('theme');

        return view('print.student-card', [
            ...$this->head($user->school_id, $request),
            'title' => 'شناسنامه‌ی دانش‌آموز',
            // رمزِ بخشِ والدین فقط برای مدیر/ادمین چاپ می‌شود، نه معلم و نه خودِ خانواده
            'showPin' => $viewer->hasRole(Roles::SUPER_ADMIN) || $viewer->hasRole(Roles::SCHOOL_ADMIN),
            's' => [
                'name'        => $user->name,
                'avatar'      => $user->avatar ? Storage::disk('public')->url($user->avatar) : null,
                'national_id' => $user->national_id,
                'gender'      => $settings['gender'] ?? null,
                'birth'       => $user->birth_date ? Jalali::format($user->birth_date) : null,
                'grade'       => $user->grade,
                'phone'       => $user->phone,
                'class'       => $class?->name,
                'teacher'     => $class?->teacher?->name,
                'team'        => $user->theme ? "{$user->theme->emoji} {$user->theme->name}" : null,
                'joined'      => Jalali::format($user->created_at),
                'xp'          => $user->totalXp(),
                'father_name' => $g['father_name'] ?? null,
                'mother_name' => $g['mother_name'] ?? null,
                'parent_relation' => $g['relation'] ?? null,
                'guardian_phone'  => $g['phone'] ?? ($settings['guardian_phone'] ?? null),
                'address'     => $g['address'] ?? ($settings['address'] ?? null),
                'parent_pin'  => $g['pin'] ?? null,
            ],
        ]);
    }

    /** گزارشِ کاملِ کلاسِ معلم (جدولِ همه‌ی دانش‌آموزان + تیم‌ها + ترکیب). */
    public function classroom(Request $request, AnalyticsService $analytics): View
    {
        $teacher = $request->user();
        abort_unless($teacher->hasRole(Roles::TEACHER), 403);
        $classroom = Classroom::where('teacher_id', $teacher->id)->firstOrFail();

        return view('print.classroom', [
            ...$this->head($teacher->school_id, $request),
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

        // ستون‌های قابلِ‌انتخاب (گزارش‌ساز) — ترتیب و برچسب
        $allCols = [
            'name' => 'نام و نام‌خانوادگی', 'national_id' => 'کدِ ملی', 'birth' => 'تاریخِ تولد',
            'grade' => 'پایه', 'class' => 'کلاس / معلم', 'team' => 'تیم', 'phone' => 'موبایل',
            'guardian' => 'سرپرست', 'g_phone' => 'موبایلِ سرپرست', 'xp' => 'امتیاز',
        ];
        $picked = collect(explode(',', (string) $request->query('cols')))
            ->map(fn ($c) => trim($c))->filter(fn ($c) => isset($allCols[$c]))->values();
        if ($picked->isEmpty()) {
            $picked = collect(array_keys($allCols)); // پیش‌فرض: همه
        }
        $cols = $picked->mapWithKeys(fn ($c) => [$c => $allCols[$c]])->all();

        return view('print.roster', [
            ...$this->head($admin->school_id, $request),
            'title'    => 'فهرستِ دانش‌آموزان',
            'students' => $students,
            'cols'     => $cols,
        ]);
    }
}
