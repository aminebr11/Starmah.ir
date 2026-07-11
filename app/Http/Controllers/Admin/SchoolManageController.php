<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\School;
use App\Models\User;
use App\Support\Levels;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ادمین کل: مشاهده و مدیریت کامل داده‌های یک مدرسه (مدیر، معلم‌ها، کلاس‌ها، دانش‌آموزان).
 * ادمین کل school_id ندارد، پس global scope مدرسه‌محور برایش غیرفعال است و همه را می‌بیند.
 */
class SchoolManageController extends Controller
{
    public function show(Request $request, School $school): Response
    {
        $admins = User::where('school_id', $school->id)
            ->whereHas('roles', fn ($q) => $q->where('name', Roles::SCHOOL_ADMIN))
            ->get(['id', 'name', 'phone', 'national_id'])->values();

        $teachers = User::where('school_id', $school->id)
            ->whereHas('roles', fn ($q) => $q->where('name', Roles::TEACHER))->get()
            ->map(function ($t) {
                $class = Classroom::where('teacher_id', $t->id)->first();
                return [
                    'id' => $t->id, 'name' => $t->name, 'phone' => $t->phone, 'national_id' => $t->national_id,
                    'class_name' => $class?->name, 'grade' => $class?->grade,
                    'students' => $class ? $class->students()->count() : 0,
                ];
            })->values();

        $students = User::where('school_id', $school->id)
            ->whereHas('roles', fn ($q) => $q->where('name', Roles::STUDENT))->get()
            ->map(function ($s) {
                $class = $s->classrooms()->with('teacher:id,name')->first();
                return [
                    'id' => $s->id, 'name' => $s->name, 'phone' => $s->phone, 'national_id' => $s->national_id,
                    'class' => $class?->name, 'teacher' => $class?->teacher?->name, 'xp' => $s->totalXp(),
                ];
            })->sortBy('name', SORT_NATURAL)->values();

        $classes = Classroom::where('school_id', $school->id)->with('teacher:id,name')->get()
            ->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'grade' => $c->grade,
                'teacher' => $c->teacher?->name, 'students' => $c->students()->count(),
            ])->values();

        return Inertia::render('Admin/SchoolManage', [
            'school'   => $school->only('id', 'name', 'city', 'level', 'status'),
            'levels'   => Levels::levels(),
            'grades'   => Levels::grades($school->level),
            'admins'   => $admins,
            'teachers' => $teachers,
            'students' => $students,
            'classes'  => $classes,
        ]);
    }

    /** ویرایش اطلاعات مدرسه توسط ادمین کل — به‌ویژه نوع/مقطع مدرسه. */
    public function update(Request $request, School $school): RedirectResponse
    {
        $data = $request->validate([
            'name'   => ['required', 'string', 'max:120'],
            'city'   => ['nullable', 'string', 'max:80'],
            'level'  => ['required', Rule::in(Levels::levels())],
            'status' => ['required', 'in:pending,active,suspended'],
        ], [], ['level' => 'مقطع مدرسه']);

        $levelChanged = $school->level !== $data['level'];
        $school->update($data);

        $msg = 'اطلاعات مدرسه به‌روزرسانی شد ✅';
        if ($levelChanged) {
            // پایه‌های کلاس‌هایی که با مقطع جدید هم‌خوان نیستند نامعتبر می‌شوند
            $valid = Levels::grades($data['level']);
            $mismatch = Classroom::where('school_id', $school->id)
                ->whereNotNull('grade')->whereNotIn('grade', $valid)->count();
            if ($mismatch > 0) {
                $msg .= " — توجه: {$mismatch} کلاس پایه‌ای خارج از مقطع جدید دارند؛ لطفاً پایه‌ی آن‌ها را اصلاح کنید.";
            }
        }

        return back()->with('flash', $msg);
    }
}
