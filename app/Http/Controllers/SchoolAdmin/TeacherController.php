<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\CurriculumBook;
use App\Models\User;
use App\Support\Levels;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** مدیر مدرسه: ساخت و مدیریت معلم‌ها و کلاس‌ها (فقط در مدرسه‌ی خودش). */
class TeacherController extends Controller
{
    public function index(Request $request): Response
    {
        $schoolId = $request->user()->school_id;
        $school = $request->user()->school;
        $level = $school?->level;

        $teachers = User::role(Roles::TEACHER)->where('school_id', $schoolId)
            ->withCount('teachingClassrooms')->get()
            ->map(function ($t) {
                $class = Classroom::where('teacher_id', $t->id)->first();
                return [
                    'id' => $t->id, 'name' => $t->name, 'avatar' => $t->avatar_url, 'phone' => $t->phone,
                    'class_name' => $class?->name, 'grade' => $class?->grade, 'join_code' => $class?->join_code,
                    'students' => $class ? $class->students()->count() : 0,
                ];
            });

        // دروس هر پایه‌ی مقطعِ این مدرسه (باز شدن دروس هنگام انتخاب پایه)
        $booksByGrade = CurriculumBook::where('level', $level)->where('is_active', true)
            ->orderBy('sort')->get()
            ->groupBy('grade')
            ->map(fn ($g) => $g->map(fn ($b) => ['name' => $b->name, 'icon' => $b->icon])->values())
            ->toArray();

        return Inertia::render('SchoolAdmin/Teachers', [
            'teachers'     => $teachers,
            'school'       => $school?->only('name', 'plan', 'seats', 'level'),
            'grades'       => Levels::grades($level),
            'booksByGrade' => $booksByGrade,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $level = $request->user()->school?->level;
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'phone'      => ['required', 'string', 'max:20'],
            'class_name' => ['required', 'string', 'max:60'],
            'grade'      => ['required', 'string', \Illuminate\Validation\Rule::in(Levels::grades($level) ?: Levels::allGrades())],
            'password'   => ['nullable', 'string', 'min:6'],
        ], [], ['grade' => 'پایه']);

        $schoolId = $request->user()->school_id;
        $school = $request->user()->school;

        // محدودیت طرح: انقضا و سقف تعداد کلاس
        if ($school && $school->isExpired()) {
            return back()->withErrors(['class_name' => 'اشتراک مدرسه منقضی شده است؛ برای ادامه طرح را تمدید کنید.']);
        }
        if ($school && ! $school->canAddClassroom()) {
            $max = $school->planModel?->max_classes;
            return back()->withErrors(['class_name' => "طرح فعلی حداکثر {$max} کلاس را اجازه می‌دهد. برای کلاس بیشتر، از ادمین ارتقای طرح بخواهید."]);
        }

        if (User::where('school_id', $schoolId)->where('phone', $data['phone'])->exists()) {
            return back()->withErrors(['phone' => 'این شماره قبلاً در مدرسه ثبت شده است.']);
        }

        $password = $data['password'] ?: Str::random(8);

        DB::transaction(function () use ($data, $schoolId, $password) {
            $teacher = User::create([
                'school_id' => $schoolId,
                'name'      => $data['name'],
                'phone'     => $data['phone'],
                'password'  => Hash::make($password),
                'phone_verified_at' => now(),
                'must_change_password' => true,
            ]);
            $teacher->assignRole(Roles::TEACHER);

            Classroom::create([
                'school_id'  => $schoolId,
                'teacher_id' => $teacher->id,
                'name'       => $data['class_name'],
                'grade'      => $data['grade'] ?? null,
                'join_code'  => Str::upper(Str::random(6)),
            ]);
        });

        return back()->with('flash', [
            'type' => 'credentials',
            'message' => "معلم «{$data['name']}» ساخته شد. موبایل: {$data['phone']} | رمز: {$password}",
        ]);
    }
}
