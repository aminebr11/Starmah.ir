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
            'pending' => SchoolRequest::where('status', 'pending')->latest()->get(),
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

    /** تأیید درخواست: ساخت مدرسه + حساب مدیر با طرح و رمز انتخابی ادمین. */
    public function approve(Request $request, SchoolRequest $schoolRequest): RedirectResponse
    {
        if ($schoolRequest->status !== 'pending') {
            return back();
        }

        $data = $request->validate([
            'plan_id'       => ['required', 'exists:plans,id'],
            'password_mode' => ['required', 'in:auto,manual'],
            'password'      => ['nullable', 'required_if:password_mode,manual', 'string', 'min:6', 'max:60'],
        ]);

        $plan = Plan::findOrFail($data['plan_id']);
        $password = $data['password_mode'] === 'manual' ? $data['password'] : Str::random(8);

        $result = DB::transaction(function () use ($schoolRequest, $plan, $password, $data) {
            $school = School::create([
                'name'    => $schoolRequest->school_name,
                'slug'    => Str::slug($schoolRequest->school_name) . '-' . Str::lower(Str::random(4)),
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
                'phone'     => $schoolRequest->manager_phone,
                'email'     => $schoolRequest->manager_email,
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

        return back()->with('flash', [
            'type' => 'credentials',
            'message' => "حساب مدیر «{$result['school']}» با طرح «{$result['plan']}» ساخته شد. موبایل: {$result['phone']} | رمز: {$password}",
        ]);
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
        ]);

        $plan = Plan::findOrFail($data['plan_id']);
        $password = $data['password_mode'] === 'manual' ? $data['password'] : Str::random(8);
        $days = $data['days_override'] ?? $plan->duration_days;

        $result = DB::transaction(function () use ($data, $plan, $password, $days) {
            $school = School::create([
                'name'    => $data['school_name'],
                'slug'    => Str::slug($data['school_name']).'-'.Str::lower(Str::random(4)),
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

        return back()->with('flash', [
            'type' => 'credentials',
            'message' => "مدرسه‌ی «{$result['school']}» با طرح «{$result['plan']}» ساخته شد. موبایل مدیر: {$result['phone']} | رمز: {$password}",
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

    public function reject(SchoolRequest $schoolRequest): RedirectResponse
    {
        $schoolRequest->update(['status' => 'rejected', 'reviewed_by' => auth()->id()]);
        return back()->with('flash', ['type' => 'info', 'message' => 'درخواست رد شد.']);
    }
}
