<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\User;
use App\Support\Levels;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * ویرایش/حذف کاربران و کلاس‌ها با کنترل دسترسی نقش‌محور:
 *  - ادمین کل: همه‌ی مدارس
 *  - مدیر مدرسه: فقط مدرسه‌ی خودش (معلم/دانش‌آموز/کلاس)
 *  - معلم: فقط دانش‌آموزانِ کلاس خودش
 */
class ManagementController extends Controller
{
    /** آیا کاربر جاری مجاز به مدیریت این کاربرِ هدف است؟ */
    private function canManageUser(User $actor, User $target): bool
    {
        if ($actor->hasRole(Roles::SUPER_ADMIN)) {
            return true;
        }
        if ($actor->hasRole(Roles::SCHOOL_ADMIN)) {
            return $target->school_id === $actor->school_id && ! $target->hasRole(Roles::SUPER_ADMIN);
        }
        if ($actor->hasRole(Roles::TEACHER)) {
            // فقط دانش‌آموزانِ کلاسِ خودِ معلم
            if (! $target->hasRole(Roles::STUDENT)) {
                return false;
            }
            return Classroom::where('teacher_id', $actor->id)
                ->whereHas('students', fn ($q) => $q->where('users.id', $target->id))->exists();
        }
        return false;
    }

    private function canManageClassroom(User $actor, Classroom $classroom): bool
    {
        if ($actor->hasRole(Roles::SUPER_ADMIN)) {
            return true;
        }
        if ($actor->hasRole(Roles::SCHOOL_ADMIN)) {
            return $classroom->school_id === $actor->school_id;
        }
        return false;
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($this->canManageUser($actor, $user), 403);

        $isTeacherActor = $actor->hasRole(Roles::TEACHER);
        $level = $user->school?->level;

        $rules = [
            'name'        => ['required', 'string', 'max:100'],
            'phone'       => ['required', 'string', 'max:20', Rule::unique('users', 'phone')->where('school_id', $user->school_id)->ignore($user->id)],
            'national_id' => ['nullable', 'string', 'max:20'],
            'password'    => ['nullable', 'string', 'min:6'],
        ];
        // مدیر/ادمین می‌تواند پایه و نام کلاسِ معلم را هم اصلاح کند
        if (! $isTeacherActor && $user->hasRole(Roles::TEACHER)) {
            $rules['grade'] = ['nullable', 'string', Rule::in(Levels::grades($level) ?: Levels::allGrades())];
            $rules['class_name'] = ['nullable', 'string', 'max:60'];
        }
        $data = $request->validate($rules, [], ['grade' => 'پایه']);

        $user->fill(['name' => $data['name'], 'phone' => $data['phone'], 'national_id' => $data['national_id'] ?? $user->national_id]);
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
            $user->must_change_password = true;
        }
        $user->save();

        // به‌روزرسانی کلاسِ معلم
        if (! $isTeacherActor && $user->hasRole(Roles::TEACHER) && (isset($data['grade']) || isset($data['class_name']))) {
            $class = Classroom::where('teacher_id', $user->id)->first();
            if ($class) {
                $class->update(array_filter([
                    'name'  => $data['class_name'] ?? null,
                    'grade' => $data['grade'] ?? null,
                ], fn ($v) => $v !== null && $v !== ''));
            }
        }

        return back()->with('flash', "اطلاعات «{$user->name}» به‌روزرسانی شد ✅");
    }

    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($this->canManageUser($actor, $user), 403);
        abort_if($user->id === $actor->id, 403, 'حذف حساب خودتان ممکن نیست.');

        $name = $user->name;

        // اگر معلم است، کلاس‌هایش هم حذف می‌شوند (دانش‌آموزان از کلاس آزاد می‌شوند)
        if ($user->hasRole(Roles::TEACHER)) {
            Classroom::where('teacher_id', $user->id)->get()->each->delete();
        }
        $user->delete();

        return back()->with('flash', "«{$name}» حذف شد 🗑️");
    }

    public function updateClassroom(Request $request, Classroom $classroom): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($this->canManageClassroom($actor, $classroom), 403);
        $level = $classroom->school?->level;

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:60'],
            'grade' => ['required', 'string', Rule::in(Levels::grades($level) ?: Levels::allGrades())],
        ], [], ['grade' => 'پایه']);

        $classroom->update(['name' => $data['name'], 'grade' => $data['grade']]);

        return back()->with('flash', 'کلاس به‌روزرسانی شد ✅');
    }

    public function destroyClassroom(Request $request, Classroom $classroom): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($this->canManageClassroom($actor, $classroom), 403);
        $classroom->delete();

        return back()->with('flash', 'کلاس حذف شد 🗑️');
    }
}
