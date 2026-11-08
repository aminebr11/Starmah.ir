<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\School;
use App\Models\SchoolRequest;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** ادمین کل: مدیریت و تأیید درخواست مدارس. */
class SchoolApprovalController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Schools', [
            'pending' => $this->pendingWithChecks(),
            'smsOn' => app(\App\Services\SmsService::class)->enabled(),
            'plans' => Plan::where('is_active', true)->orderBy('sort')->get()
                ->map(fn ($p) => [
                    'id' => $p->id, 'key' => $p->key, 'name' => $p->name,
                    'max_classes' => $p->max_classes, 'max_students_per_class' => $p->max_students_per_class,
                    'duration_days' => $p->duration_days,
                ]),
            'schools' => School::with('planModel:id,name')->withCount(['users', 'classrooms'])->latest()->get()
                ->map(fn ($s) => [
                    'id' => $s->id, 'name' => $s->name, 'city' => $s->city,
                    'plan' => $s->planModel?->name ?? $s->plan, 'plan_id' => $s->plan_id, 'status' => $s->status,
                    'users' => $s->users_count, 'classrooms' => $s->classrooms_count,
                    'expires' => $s->subscription_ends_at?->format('Y/m/d'),
                ]),
        ]);
    }

    /**
     * درخواست‌های در انتظار + هشدارِ تکراری: اگر شماره یا ایمیلِ مدیر از قبل حساب دارد یا درخواستِ
     * دیگری با همان شماره هست، پیش از تأیید دیده شود (پیش از این تأییدِ دوم «خطای ۵۰۰» می‌داد).
     */
    private function pendingWithChecks(): \Illuminate\Support\Collection
    {
        $pending = SchoolRequest::where('status', 'pending')->latest()->get();
        $phones = $pending->pluck('manager_phone')->filter()->map(fn ($p) => $this->cleanPhone($p))->unique()->values();
        $users = User::with('school:id,name')->whereIn('phone', $phones)->get()->keyBy(fn ($u) => $this->cleanPhone($u->phone));
        $emails = User::whereIn('email', $pending->pluck('manager_email')->filter())->pluck('email')->flip();
        $count = $pending->countBy(fn ($r) => $this->cleanPhone($r->manager_phone));

        return $pending->map(function ($r) use ($users, $emails, $count) {
            $u = $users->get($this->cleanPhone($r->manager_phone));

            return $r->toArray() + [
                'taken' => $u ? ['name' => $u->name, 'school' => $u->school?->name, 'role' => $this->roleLabel($u)] : null,
                'email_taken' => $r->manager_email && isset($emails[$r->manager_email]),
                'dupes' => max(0, ($count[$this->cleanPhone($r->manager_phone)] ?? 1) - 1),
            ];
        })->values();
    }

    private function cleanPhone(?string $p): string
    {
        $p = strtr(trim((string) $p), ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        $p = preg_replace('/\D+/', '', $p) ?? '';
        if (str_starts_with($p, '98') && strlen($p) === 12) {
            $p = '0' . substr($p, 2);
        } elseif (strlen($p) === 10 && str_starts_with($p, '9')) {
            $p = '0' . $p;
        }

        return $p;
    }

    private function roleLabel(User $u): string
    {
        return match (true) {
            $u->hasRole(Roles::SUPER_ADMIN) => 'ادمینِ کل',
            $u->hasRole(Roles::SCHOOL_ADMIN) => 'مدیرِ مدرسه',
            $u->hasRole(Roles::TEACHER) => 'معلم',
            $u->hasRole(Roles::STUDENT) => 'دانش‌آموز',
            default => 'کاربر',
        };
    }

    /** پیامکِ اطلاعاتِ ورود به مدیرِ تازه (اگر سامانه‌ی پیامک فعال باشد). */
    private function smsCredentials(string $phone, string $school, string $password, bool $mustChange): ?array
    {
        if (! app(\App\Services\SmsService::class)->enabled()) {
            return null; // سامانه‌ی پیامک خاموش است → اطلاعات فقط در صفحه نشان داده می‌شود
        }
        $text = "ستاره ماه 🌙\nدرخواستِ مدرسه‌ی «{$school}» تأیید شد ✅\n"
            . 'ورود: ' . url('/login') . "\nنام کاربری: {$phone}\nرمز: {$password}"
            . ($mustChange ? "\nپس از ورود، رمز را عوض کنید." : '');

        return rescue(fn () => app(\App\Services\SmsService::class)->send($phone, $text), ['ok' => false, 'message' => 'ارسالِ پیامک ناموفق بود.'], true);
    }

    /** تأیید درخواست: ساخت مدرسه + حساب مدیر با طرح و رمز انتخابی ادمین (+ پیامکِ اطلاعاتِ ورود). */
    public function approve(Request $request, SchoolRequest $schoolRequest): RedirectResponse
    {
        if ($schoolRequest->status !== 'pending') {
            return back()->with('flash', ['type' => 'info', 'message' => 'این درخواست قبلاً بررسی شده است.']);
        }

        $key = 'req_' . $schoolRequest->id;
        $data = $request->validate([
            'plan_id'       => ['required', 'exists:plans,id'],
            'password_mode' => ['required', 'in:auto,manual'],
            'password'      => ['nullable', 'required_if:password_mode,manual', 'string', 'min:6', 'max:60'],
            // اگر شماره‌ی درخواست قبلاً حساب دارد، ادمین می‌تواند شماره‌ی دیگری بدهد
            'manager_phone' => ['nullable', 'string', 'max:20'],
            'send_sms'      => ['nullable', 'boolean'],
        ], [
            'password.required_if' => 'رمزِ دستی را بنویسید.',
            'password.min' => 'رمز دستِ‌کم ۶ کاراکتر باشد.',
        ]);

        $phone = $this->cleanPhone($data['manager_phone'] ?? null) ?: $this->cleanPhone($schoolRequest->manager_phone);
        if (! preg_match('/^09\d{9}$/', $phone)) {
            return back()->withErrors([$key => 'شماره‌ی موبایلِ مدیر معتبر نیست (مثلاً ۰۹۱۲۱۲۳۴۵۶۷).']);
        }
        // هر شماره فقط یک حساب: ورود با شماره است و حسابِ دوم هرگز وارد نمی‌شد
        if ($taken = User::with('school:id,name')->where('phone', $phone)->first()) {
            return back()->withErrors([$key => "شماره‌ی {$phone} قبلاً برای «{$taken->name}» (" . $this->roleLabel($taken)
                . ($taken->school ? ' — ' . $taken->school->name : '') . ') ثبت شده است؛ احتمالاً درخواستِ تکراری است. '
                . 'یا درخواست را رد کنید، یا شماره‌ی دیگری برای مدیر بنویسید.']);
        }
        // ایمیلِ تکراری دیگر خطای ۵۰۰ نمی‌دهد: حساب بدونِ ایمیل ساخته می‌شود
        $email = $schoolRequest->manager_email && ! User::where('email', $schoolRequest->manager_email)->exists() ? $schoolRequest->manager_email : null;

        $plan = Plan::findOrFail($data['plan_id']);
        $password = $data['password_mode'] === 'manual' ? $data['password'] : $this->easyPassword();

        try {
            $result = DB::transaction(function () use ($schoolRequest, $plan, $password, $data, $phone, $email) {
                $school = School::create([
                    'name'    => $schoolRequest->school_name,
                    'slug'    => (Str::slug($schoolRequest->school_name) ?: 'school') . '-' . Str::lower(Str::random(6)),
                    'city'    => $schoolRequest->city,
                    'level'   => $schoolRequest->level,
                    'plan'    => $plan->key,
                    'plan_id' => $plan->id,
                    'status'  => 'active',
                    'seats'   => ($plan->max_classes ?? $schoolRequest->classes_count) * ($plan->max_students_per_class ?? 35),
                    'subscription_ends_at' => $plan->duration_days ? now()->addDays($plan->duration_days) : null,
                ]);

                $manager = User::create([
                    'school_id' => $school->id,
                    'name'      => $schoolRequest->manager_name,
                    'phone'     => $phone,
                    'email'     => $email,
                    'password'  => Hash::make($password),
                    'phone_verified_at' => now(),
                    // رمز دستی: اجبار به تغییر ندارد؛ رمز خودکار: اجباری است
                    'must_change_password' => $data['password_mode'] === 'auto',
                ]);
                $manager->assignRole(Roles::SCHOOL_ADMIN);

                $schoolRequest->update([
                    'status' => 'approved', 'school_id' => $school->id, 'reviewed_by' => auth()->id(),
                ]);

                return ['phone' => $manager->phone, 'school' => $school->name, 'plan' => $plan->name];
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([$key => 'ساختِ حساب انجام نشد: ' . \App\Http\Controllers\Concerns\FriendlySaveErrors::explainError($e)]);
        }

        $sms = $request->boolean('send_sms', true)
            ? $this->smsCredentials($result['phone'], $result['school'], $password, $data['password_mode'] === 'auto')
            : null;

        return back()->with('flash', [
            'type' => 'credentials',
            'message' => "حساب مدیر «{$result['school']}» با طرح «{$result['plan']}» ساخته شد. نام کاربری (موبایل): {$result['phone']} | رمز: {$password}"
                . ($email === null && $schoolRequest->manager_email ? ' — ایمیلِ درخواست قبلاً ثبت شده بود؛ حساب بدونِ ایمیل ساخته شد.' : ''),
            'sms' => $sms === null ? 'off' : (! empty($sms['ok']) ? 'sent' : 'failed'),
            'sms_note' => $sms && empty($sms['ok']) ? ($sms['message'] ?? '') : null,
        ]);
    }

    /** رمزِ خودکارِ خوانا (بدونِ حروفِ شبیه‌به‌هم مثلِ l/1/O/0) — برای پیامک و تایپ روی گوشی. */
    private function easyPassword(): string
    {
        $a = 'abcdefghjkmnpqrstuvwxyz';
        $d = '23456789';
        $p = '';
        for ($i = 0; $i < 4; $i++) {
            $p .= $a[random_int(0, strlen($a) - 1)];
        }
        for ($i = 0; $i < 4; $i++) {
            $p .= $d[random_int(0, strlen($d) - 1)];
        }

        return $p;
    }

    /** ساختِ مستقیمِ مدرسه توسطِ ادمین (بدونِ درخواست) با انتخابِ طرح/تریال. */
    public function storeDirect(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'school_name'   => ['required', 'string', 'max:150'],
            'city'          => ['nullable', 'string', 'max:80'],
            'level'         => ['required', 'in:دبستان,متوسطه اول,متوسطه دوم'],
            'manager_name'  => ['required', 'string', 'max:100'],
            'manager_phone' => ['required', 'string', 'max:20'],
            'manager_email' => ['nullable', 'email'],
            'plan_id'       => ['required', 'exists:plans,id'],
            'days_override' => ['nullable', 'integer', 'min:1', 'max:36500'],
            'password_mode' => ['required', 'in:auto,manual'],
            'password'      => ['nullable', 'required_if:password_mode,manual', 'string', 'min:6', 'max:60'],
            'send_sms'      => ['nullable', 'boolean'],
        ]);

        $phone = $this->cleanPhone($data['manager_phone']);
        if (! preg_match('/^09\d{9}$/', $phone)) {
            return back()->withErrors(['manager_phone' => 'شماره‌ی موبایل معتبر نیست (مثلاً ۰۹۱۲۱۲۳۴۵۶۷).']);
        }
        if ($taken = User::with('school:id,name')->where('phone', $phone)->first()) {
            return back()->withErrors(['manager_phone' => "این شماره قبلاً برای «{$taken->name}» (" . $this->roleLabel($taken) . ($taken->school ? ' — ' . $taken->school->name : '') . ') ثبت شده است.']);
        }
        if (! empty($data['manager_email']) && User::where('email', $data['manager_email'])->exists()) {
            return back()->withErrors(['manager_email' => 'این ایمیل قبلاً برای کاربرِ دیگری ثبت شده است؛ خالی بگذارید یا ایمیلِ دیگری بنویسید.']);
        }
        $data['manager_phone'] = $phone;

        $plan = Plan::findOrFail($data['plan_id']);
        $password = $data['password_mode'] === 'manual' ? $data['password'] : $this->easyPassword();
        $days = $data['days_override'] ?? $plan->duration_days;

        $result = DB::transaction(function () use ($data, $plan, $password, $days) {
            $school = School::create([
                'name'    => $data['school_name'],
                'slug'    => (Str::slug($data['school_name']) ?: 'school').'-'.Str::lower(Str::random(6)),
                'city'    => $data['city'] ?? null,
                'level'   => $data['level'],
                'plan'    => $plan->key,
                'plan_id' => $plan->id,
                'status'  => 'active',
                'seats'   => ($plan->max_classes ?? 100) * ($plan->max_students_per_class ?? 35),
                'subscription_ends_at' => $days ? now()->addDays($days) : null,
            ]);

            $manager = User::create([
                'school_id' => $school->id,
                'name'      => $data['manager_name'],
                'phone'     => $data['manager_phone'],
                'email'     => $data['manager_email'] ?? null,
                'password'  => Hash::make($password),
                'phone_verified_at' => now(),
                'must_change_password' => $data['password_mode'] === 'auto',
            ]);
            $manager->assignRole(Roles::SCHOOL_ADMIN);

            return ['phone' => $manager->phone, 'school' => $school->name, 'plan' => $plan->name];
        });

        $sms = $request->boolean('send_sms', true)
            ? $this->smsCredentials($result['phone'], $result['school'], $password, $data['password_mode'] === 'auto')
            : null;

        return back()->with('flash', [
            'type' => 'credentials',
            'message' => "مدرسه‌ی «{$result['school']}» با طرح «{$result['plan']}» ساخته شد. نام کاربری (موبایل): {$result['phone']} | رمز: {$password}",
            'sms' => $sms === null ? 'off' : (! empty($sms['ok']) ? 'sent' : 'failed'),
            'sms_note' => $sms && empty($sms['ok']) ? ($sms['message'] ?? '') : null,
        ]);
    }

    /** تغییر طرح یک مدرسه‌ی موجود (ارتقا/تنزل). */
    public function updatePlan(Request $request, School $school): RedirectResponse
    {
        $data = $request->validate(['plan_id' => ['required', 'exists:plans,id']]);
        $plan = Plan::findOrFail($data['plan_id']);

        $school->update([
            'plan'    => $plan->key,
            'plan_id' => $plan->id,
            'seats'   => ($plan->max_classes ?? 100) * ($plan->max_students_per_class ?? 35),
            'subscription_ends_at' => $plan->duration_days ? now()->addDays($plan->duration_days) : null,
        ]);

        return back()->with('flash', "طرح مدرسه‌ی «{$school->name}» به «{$plan->name}» تغییر کرد ✅");
    }

    /** ردِ درخواست (+ پیامک به درخواست‌کننده با دلیلِ اختیاری). */
    public function reject(Request $request, SchoolRequest $schoolRequest): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200'], 'send_sms' => ['nullable', 'boolean']]);
        if ($schoolRequest->status !== 'pending') {
            return back()->with('flash', ['type' => 'info', 'message' => 'این درخواست قبلاً بررسی شده است.']);
        }
        $schoolRequest->update(['status' => 'rejected', 'reviewed_by' => auth()->id()]);

        $sms = null;
        if ($request->boolean('send_sms', true) && $schoolRequest->manager_phone && app(\App\Services\SmsService::class)->enabled()) {
            $reason = trim((string) ($data['reason'] ?? ''));
            $text = "ستاره ماه 🌙\nدرخواستِ ثبتِ مدرسه‌ی «{$schoolRequest->school_name}» بررسی شد و فعلاً تأیید نشد."
                . ($reason !== '' ? "\nدلیل: {$reason}" : '') . "\nبرای پیگیری با پشتیبانی تماس بگیرید.";
            $sms = rescue(fn () => app(\App\Services\SmsService::class)->send($this->cleanPhone($schoolRequest->manager_phone), $text), ['ok' => false], true);
        }

        return back()->with('flash', ['type' => 'info', 'message' => 'درخواستِ «' . $schoolRequest->school_name . '» رد شد.'
            . ($sms === null ? '' : (! empty($sms['ok']) ? ' 📩 پیامک برای درخواست‌کننده رفت.' : ' (پیامک ارسال نشد: ' . ($sms['message'] ?? 'سامانه‌ی پیامک فعال نیست') . ')'))]);
    }
}
