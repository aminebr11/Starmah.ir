<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\User;
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

        $teachers = User::role(Roles::TEACHER)->where('school_id', $schoolId)
            ->withCount('teachingClassrooms')->get()
            ->map(function ($t) {
                $class = Classroom::where('teacher_id', $t->id)->first();
                return [
                    'id' => $t->id, 'name' => $t->name, 'phone' => $t->phone,
                    'class_name' => $class?->name, 'join_code' => $class?->join_code,
                    'students' => $class ? $class->students()->count() : 0,
                ];
            });

        return Inertia::render('SchoolAdmin/Teachers', [
            'teachers' => $teachers,
            'school'   => $request->user()->school?->only('name', 'plan', 'seats'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'phone'      => ['required', 'string', 'max:20'],
            'class_name' => ['required', 'string', 'max:60'],
            'password'   => ['nullable', 'string', 'min:6'],
        ]);

        $schoolId = $request->user()->school_id;

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
                'join_code'  => Str::upper(Str::random(6)),
            ]);
        });

        return back()->with('flash', [
            'type' => 'credentials',
            'message' => "معلم «{$data['name']}» ساخته شد. موبایل: {$data['phone']} | رمز: {$password}",
        ]);
    }
}
