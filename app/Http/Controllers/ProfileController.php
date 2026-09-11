<?php

namespace App\Http\Controllers;

use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/** پروفایل من — مشاهده و ویرایش اطلاعات کامل، آواتار و رمز (همه‌ی نقش‌ها). */
class ProfileController extends Controller
{
    use \App\Http\Controllers\Concerns\StoresUploads;

    public function edit(Request $request): Response
    {
        $u = $request->user();
        $role = $u->getRoleNames()->first();
        $s = $u->settings ?? [];

        // آمار مختصر بر اساس نقش
        $stats = [];
        if ($role === Roles::STUDENT) {
            $stats = [
                ['ic' => '⭐', 'label' => 'امتیاز', 'value' => $u->totalXp()],
                ['ic' => '🏛️', 'label' => 'کلاس‌ها', 'value' => $u->classrooms()->count()],
            ];
        } elseif ($role === Roles::TEACHER) {
            $stats = [
                ['ic' => '🏛️', 'label' => 'کلاس‌ها', 'value' => $u->teachingClassrooms()->count()],
                ['ic' => '🎓', 'label' => 'دانش‌آموزان', 'value' => $u->teachingClassrooms()->withCount('students')->get()->sum('students_count')],
            ];
        }

        return Inertia::render('Profile/Edit', [
            'profile' => [
                'name'        => $u->name,
                'phone'       => $u->phone,
                'email'       => $u->email,
                'grade'       => $u->grade,
                'national_id' => $u->national_id,
                'avatar'      => $u->avatar ? Storage::url($u->avatar) : null,
                'role'        => $role,
                'school'      => $u->school?->name,
                'birth_date'  => $u->birth_date?->format('Y-m-d'),
                'jbirth'      => $u->birth_date ? \App\Support\Jalali::format($u->birth_date) : null,
                // اطلاعات تکمیلی (در settings)
                'bio'              => $s['bio'] ?? '',
                'address'          => $s['address'] ?? '',
                'guardian_name'    => $s['guardian_name'] ?? '',
                'guardian_phone'   => $s['guardian_phone'] ?? '',
                'specialty'        => $s['specialty'] ?? '',
                'experience_years' => $s['experience_years'] ?? '',
                'education'        => $s['education'] ?? '',
            ],
            'stats' => $stats,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $u = $request->user();
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'email'       => ['nullable', 'email', 'max:120'],
            'national_id' => ['nullable', 'string', 'max:10'],
            // عکس در مرورگر به JPEGِ کوچک تبدیل می‌شود؛ سقفِ قبلیِ ۲ مگابایت
            // عکسِ خامِ دوربینِ گوشی (۳ تا ۸ مگابایت) را رد می‌کرد.
            'avatar'      => ['nullable', 'file', 'max:4096'],
            'birth_date'  => ['nullable', 'date'],
            // اطلاعات تکمیلی
            'bio'              => ['nullable', 'string', 'max:500'],
            'address'          => ['nullable', 'string', 'max:250'],
            'guardian_name'    => ['nullable', 'string', 'max:100'],
            'guardian_phone'   => ['nullable', 'string', 'max:20'],
            'specialty'        => ['nullable', 'string', 'max:100'],
            'experience_years' => ['nullable', 'string', 'max:20'],
            'education'        => ['nullable', 'string', 'max:100'],
        ]);

        // عکس پیش از جایگزینیِ قبلی ذخیره می‌شود تا اگر ذخیره شکست خورد،
        // آواتارِ فعلیِ کاربر از دست نرود. خطا هم دیگر خاموش نیست.
        if ($request->hasFile('avatar')) {
            $newPath = $this->storeImageOrFail($request->file('avatar'), 'avatars');
            $old = $u->avatar;
            $u->avatar = $newPath;
            if ($old) {
                Storage::disk('public')->delete($old);
            }
        }

        $u->name = $data['name'];
        $u->email = $data['email'] ?? null;
        $u->national_id = $data['national_id'] ?? null;
        $u->birth_date = $data['birth_date'] ?? null;

        // اطلاعات تکمیلی در settings ذخیره می‌شود (بدون نیاز به مایگریشن)
        $settings = $u->settings ?? [];
        foreach (['bio', 'address', 'guardian_name', 'guardian_phone', 'specialty', 'experience_years', 'education'] as $key) {
            $settings[$key] = $data[$key] ?? null;
        }
        $u->settings = $settings;
        $u->save();

        return back()->with('flash', 'پروفایل به‌روزرسانی شد ✅');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::min(6)],
        ]);

        $request->user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('flash', 'رمز عبور تغییر کرد ✅');
    }
}
