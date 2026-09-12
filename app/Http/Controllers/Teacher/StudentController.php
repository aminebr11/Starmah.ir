<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Theme;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * ساختِ دانش‌آموزِ تازه توسطِ معلم.
 *
 * ── چرا لازم شد ───────────────────────────────────────────────────────
 * مدیرِ مدرسه دکمه‌ی «معلمِ جدید» داشت، ولی معلم هیچ راهی برای افزودنِ
 * دانش‌آموز نداشت؛ تنها راه این بود که خودِ دانش‌آموز از صفحه‌ی ثبت‌نام
 * وارد شود. برای کلاسی که دانش‌آموزش گوشی ندارد یا هنوز ثبت‌نام نکرده،
 * این یعنی بن‌بست.
 *
 * همان فیلدهای فرمِ ثبت‌نام اینجا هم گرفته می‌شود تا پرونده از همان روزِ
 * اول کامل باشد؛ فقط «نام» و «موبایل» اجباری‌اند تا کارِ معلم سریع بماند.
 * رمز اگر داده نشود خودکار ساخته و به معلم نشان داده می‌شود.
 */
class StudentController extends Controller
{
    use \App\Http\Controllers\Concerns\StoresUploads;

    public function store(Request $request): RedirectResponse
    {
        $teacher = $request->user();
        $classroom = Classroom::where('teacher_id', $teacher->id)->firstOrFail();

        $data = $request->validate([
            'first_name'   => ['required', 'string', 'max:60'],
            'last_name'    => ['required', 'string', 'max:60'],
            'phone'        => ['required', 'string', 'max:20'],
            'password'     => ['nullable', 'string', 'min:6'],
            'gender'       => ['nullable', 'in:پسر,دختر'],
            'national_id'  => ['nullable', 'digits:10'],
            'birth_date'   => ['nullable', 'date'],
            'grade'        => ['nullable', 'string', 'max:40'],
            'theme_id'     => ['nullable', 'exists:themes,id'],
            'father_name'    => ['nullable', 'string', 'max:80'],
            'mother_name'    => ['nullable', 'string', 'max:80'],
            'parent_relation' => ['nullable', 'in:پدر,مادر,ولی'],
            'parent_phone'   => ['nullable', 'string', 'max:20'],
            'address'        => ['nullable', 'string', 'max:300'],
            'parent_pin'     => ['nullable', 'digits_between:4,8'],
            'avatar'         => ['nullable', 'file', 'max:4096'],
        ], [
            'national_id.digits' => 'کدِ ملی باید ۱۰ رقم باشد.',
            'avatar.max'         => 'حجمِ عکس بیش از حد است. دوباره عکس بگیرید تا خودکار فشرده شود.',
        ]);

        // همان محدودیت‌هایی که در ثبت‌نامِ عمومی هست — وگرنه معلم می‌توانست از سقفِ طرح رد شود
        $school = $classroom->school;
        if ($school && $school->isExpired()) {
            return back()->withErrors(['phone' => 'اشتراکِ مدرسه منقضی شده است؛ فعلاً امکانِ افزودنِ دانش‌آموز نیست.']);
        }
        $maxPerClass = $school?->maxStudentsPerClass();
        if ($maxPerClass !== null && $classroom->students()->count() >= $maxPerClass) {
            return back()->withErrors(['phone' => "ظرفیتِ این کلاس (حداکثر {$maxPerClass} دانش‌آموز) تکمیل است."]);
        }
        if (User::where('school_id', $classroom->school_id)->where('phone', $data['phone'])->exists()) {
            return back()->withErrors(['phone' => 'این شماره قبلاً در این مدرسه ثبت شده است.']);
        }

        $password = $data['password'] ?: (string) random_int(100000, 999999);
        $pin = $data['parent_pin'] ?: (string) random_int(10000, 99999);
        $name = trim($data['first_name'] . ' ' . $data['last_name']);

        $student = DB::transaction(function () use ($data, $classroom, $password, $pin, $name) {
            $student = User::create([
                'school_id'   => $classroom->school_id,
                'name'        => $name,
                'phone'       => $data['phone'],
                'password'    => Hash::make($password),
                'theme_id'    => $data['theme_id'] ?? null,
                'national_id' => $data['national_id'] ?? null,
                'birth_date'  => $data['birth_date'] ?? null,
                'grade'       => $data['grade'] ?? $classroom->grade,
                'phone_verified_at' => now(),
                // دانش‌آموز در نخستین ورود رمزِ خودش را می‌گذارد
                'must_change_password' => true,
                'settings'    => [
                    'gender'   => $data['gender'] ?? null,
                    'guardian' => array_filter([
                        'father_name' => $data['father_name'] ?? null,
                        'mother_name' => $data['mother_name'] ?? null,
                        'relation'    => $data['parent_relation'] ?? null,
                        'phone'       => $data['parent_phone'] ?? null,
                        'address'     => $data['address'] ?? null,
                        'pin'         => $pin,
                    ], fn ($v) => $v !== null && $v !== ''),
                ],
            ]);
            $student->assignRole(Roles::STUDENT);
            $classroom->students()->syncWithoutDetaching([$student->id => ['joined_at' => now()]]);

            return $student;
        });

        if ($request->hasFile('avatar')) {
            $student->avatar = $this->storeImageOrFail($request->file('avatar'), 'avatars');
            $student->save();
        }

        AuditLog::record($teacher, 'ثبتِ دانش‌آموز', "«{$name}» به کلاسِ «{$classroom->name}» اضافه شد");

        return back()->with('flash', [
            'type' => 'credentials',
            'message' => "دانش‌آموز «{$name}» ساخته شد. موبایل: {$data['phone']} | رمزِ ورود: {$password} | رمزِ بخشِ والدین: {$pin}",
        ]);
    }

    /** تیم‌های قابلِ انتخاب هنگامِ ساختِ دانش‌آموز. */
    public static function themes()
    {
        return Theme::where('is_active', true)->where('key', '!=', 'brand')
            ->orderBy('sort')->get(['id', 'name', 'emoji'])
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'emoji' => $t->emoji])->values();
    }

    /** پایه‌های مجازِ مدرسه (برای منویِ «پایه»). */
    public static function grades(?User $teacher): array
    {
        return \App\Support\Levels::grades($teacher?->school?->level) ?: \App\Support\Levels::allGrades();
    }
}
