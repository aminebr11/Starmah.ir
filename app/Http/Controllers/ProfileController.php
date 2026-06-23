<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/** پروفایل من — مشاهده و ویرایش اطلاعات، آواتار و رمز (همه‌ی نقش‌ها). */
class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $u = $request->user();
        return Inertia::render('Profile/Edit', [
            'profile' => [
                'name'   => $u->name,
                'phone'  => $u->phone,
                'email'  => $u->email,
                'grade'  => $u->grade,
                'avatar' => $u->avatar ? Storage::url($u->avatar) : null,
                'role'   => $u->getRoleNames()->first(),
                'school' => $u->school?->name,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $u = $request->user();
        $data = $request->validate([
            'name'   => ['required', 'string', 'max:100'],
            'email'  => ['nullable', 'email', 'max:120'],
            'avatar' => ['nullable', 'image', 'max:2048'], // حداکثر ۲ مگابایت
        ]);

        if ($request->hasFile('avatar')) {
            if ($u->avatar) {
                Storage::disk('public')->delete($u->avatar);
            }
            $u->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        $u->name = $data['name'];
        $u->email = $data['email'] ?? null;
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
