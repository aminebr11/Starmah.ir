<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolRequest;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
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
            'schools' => School::withCount(['users', 'classrooms'])->latest()->get()
                ->map(fn ($s) => [
                    'id' => $s->id, 'name' => $s->name, 'city' => $s->city,
                    'plan' => $s->plan, 'status' => $s->status,
                    'users' => $s->users_count, 'classrooms' => $s->classrooms_count,
                ]),
        ]);
    }

    /** تأیید درخواست: ساخت مدرسه + حساب مدیر مدرسه با رمز تولیدشده. */
    public function approve(SchoolRequest $schoolRequest): RedirectResponse
    {
        if ($schoolRequest->status !== 'pending') {
            return back();
        }

        $result = DB::transaction(function () use ($schoolRequest) {
            $school = School::create([
                'name'   => $schoolRequest->school_name,
                'slug'   => Str::slug($schoolRequest->school_name) . '-' . Str::lower(Str::random(4)),
                'city'   => $schoolRequest->city,
                'plan'   => 'trial',
                'status' => 'active',
                'seats'  => $schoolRequest->classes_count * 35,
                'subscription_ends_at' => now()->addMonth(),
            ]);

            // حساب مدیر مدرسه — رمز موقت تولید می‌شود
            $password = Str::random(8);
            $manager = User::create([
                'school_id' => $school->id,
                'name'      => $schoolRequest->manager_name,
                'phone'     => $schoolRequest->manager_phone,
                'email'     => $schoolRequest->manager_email,
                'password'  => Hash::make($password),
                'phone_verified_at' => now(),
            ]);
            $manager->assignRole(Roles::SCHOOL_ADMIN);

            $schoolRequest->update([
                'status' => 'approved',
                'school_id' => $school->id,
                'reviewed_by' => auth()->id(),
            ]);

            return ['phone' => $manager->phone, 'password' => $password, 'school' => $school->name];
        });

        // اعتبار ورود مدیر را یک‌بار به ادمین نشان بده تا به مدرسه بدهد
        return back()->with('flash', [
            'type' => 'credentials',
            'message' => "حساب مدیر «{$result['school']}» ساخته شد. موبایل: {$result['phone']} | رمز موقت: {$result['password']}",
        ]);
    }

    public function reject(SchoolRequest $schoolRequest): RedirectResponse
    {
        $schoolRequest->update(['status' => 'rejected', 'reviewed_by' => auth()->id()]);
        return back()->with('flash', ['type' => 'info', 'message' => 'درخواست رد شد.']);
    }
}
