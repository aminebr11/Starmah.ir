<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\School;
use App\Models\SchoolRequest;
use App\Models\Theme;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    /** صفحه‌ی انتخاب نوع ثبت‌نام: مدرسه یا دانش‌آموز. */
    public function choice(): Response
    {
        return Inertia::render('Auth/RegisterChoice');
    }

    /* ---------------- ثبت‌نام مدرسه (درخواست) ---------------- */

    public function schoolForm(): Response
    {
        return Inertia::render('Auth/RegisterSchool');
    }

    public function schoolStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'school_name'   => ['required', 'string', 'max:150'],
            'manager_name'  => ['required', 'string', 'max:100'],
            'manager_phone' => ['required', 'string', 'max:20'],
            'manager_email' => ['nullable', 'email'],
            'city'          => ['nullable', 'string', 'max:80'],
            'level'         => ['required', 'in:دبستان,متوسطه اول,متوسطه دوم'],
            'classes_count' => ['required', 'integer', 'min:1', 'max:200'],
            'note'          => ['nullable', 'string', 'max:500'],
        ]);

        SchoolRequest::create($data);

        return redirect()->route('register.thanks');
    }

    public function thanks(): Response
    {
        return Inertia::render('Auth/RegisterThanks');
    }

    /* ---------------- ثبت‌نام دانش‌آموز ---------------- */

    public function studentForm(): Response
    {
        // مدارس فعال به‌همراه معلم‌ها و کلاس‌هایشان (برای انتخاب آبشاری)
        $schools = School::where('status', 'active')->orderBy('name')->get()->map(function ($s) {
            $teachers = Classroom::where('school_id', $s->id)->with('teacher:id,name')->get()
                ->filter(fn ($c) => $c->teacher)
                ->map(fn ($c) => [
                    'classroom_id' => $c->id,
                    'teacher_name' => $c->teacher->name,
                    'class_name'   => $c->name,
                ])->values();

            return ['id' => $s->id, 'name' => $s->name, 'classes' => $teachers];
        })->filter(fn ($s) => $s['classes']->isNotEmpty())->values();

        $themes = Theme::where('is_active', true)->where('key', '!=', 'brand')->orderBy('sort')->get()
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'emoji' => $t->emoji, 'skin' => $t->skin]);

        return Inertia::render('Auth/RegisterStudent', [
            'schools' => $schools,
            'themes'  => $themes,
        ]);
    }

    public function studentStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name'   => ['required', 'string', 'max:60'],
            'last_name'    => ['required', 'string', 'max:60'],
            'gender'       => ['nullable', 'in:پسر,دختر'],
            'national_id'  => ['nullable', 'digits:10'],
            'birth_date'   => ['nullable', 'date'],
            'grade'        => ['nullable', 'string', 'max:40'],
            'phone'        => ['required', 'string', 'max:20'],
            'password'     => ['required', 'string', 'min:6', 'confirmed'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'theme_id'     => ['required', 'exists:themes,id'],
            // اطلاعاتِ سرپرست/والدین
            'father_name'    => ['nullable', 'string', 'max:80'],
            'mother_name'    => ['nullable', 'string', 'max:80'],
            'parent_relation'=> ['nullable', 'in:پدر,مادر,ولی'],
            'parent_phone'   => ['required', 'string', 'max:20'],
            'address'        => ['nullable', 'string', 'max:300'],
            'parent_pin'     => ['nullable', 'digits_between:4,8'],
        ], [
            'password.confirmed' => 'تکرارِ رمزِ عبور با رمز یکی نیست.',
            'parent_phone.required' => 'شماره‌ی موبایلِ والد/سرپرست را وارد کنید.',
            'national_id.digits'    => 'کدِ ملی باید ۱۰ رقم باشد.',
        ]);

        $classroom = Classroom::findOrFail($data['classroom_id']);

        // محدودیت طرح: انقضای اشتراک و سقف دانش‌آموز در هر کلاس
        $school = $classroom->school;
        if ($school && $school->isExpired()) {
            return back()->withErrors(['classroom_id' => 'اشتراک این مدرسه منقضی شده است؛ فعلاً امکان ثبت‌نام نیست.']);
        }
        $maxPerClass = $school?->maxStudentsPerClass();
        if ($maxPerClass !== null && $classroom->students()->count() >= $maxPerClass) {
            return back()->withErrors(['classroom_id' => "ظرفیت این کلاس (حداکثر {$maxPerClass} دانش‌آموز) تکمیل است."]);
        }

        // جلوگیری از موبایل تکراری در همان مدرسه
        $exists = User::where('school_id', $classroom->school_id)->where('phone', $data['phone'])->exists();
        if ($exists) {
            return back()->withErrors(['phone' => 'این شماره قبلاً در این مدرسه ثبت شده است.']);
        }

        $student = User::create([
            'school_id'   => $classroom->school_id,
            'name'        => trim($data['first_name'] . ' ' . $data['last_name']),
            'phone'       => $data['phone'],
            'password'    => Hash::make($data['password']),
            'theme_id'    => $data['theme_id'],
            'national_id' => $data['national_id'] ?? null,
            'birth_date'  => $data['birth_date'] ?? null,
            'grade'       => $data['grade'] ?? null,
            'phone_verified_at' => now(),
            // مشخصاتِ تکمیلی و اطلاعاتِ سرپرست در settings (بدونِ نیاز به ستون‌های تازه)
            'settings'    => [
                'gender'   => $data['gender'] ?? null,
                'guardian' => array_filter([
                    'father_name' => $data['father_name'] ?? null,
                    'mother_name' => $data['mother_name'] ?? null,
                    'relation'    => $data['parent_relation'] ?? null,
                    'phone'       => $data['parent_phone'] ?? null,
                    'address'     => $data['address'] ?? null,
                    // رمزِ «بخشِ والدین» — اگر در فرم داده نشد، خودکار ساخته می‌شود
                    'pin'         => $data['parent_pin'] ?? (string) random_int(10000, 99999),
                ], fn ($v) => $v !== null && $v !== ''),
            ],
        ]);
        $student->assignRole(Roles::STUDENT);
        $classroom->students()->syncWithoutDetaching([$student->id => ['joined_at' => now()]]);

        Auth::login($student);

        return redirect()->route('dashboard');
    }
}
