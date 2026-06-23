<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/** تغییر اجباری رمز در اولین ورود (رمز موقت). */
class ForcePasswordController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Auth/ForcePassword');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        $request->user()->update([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ]);

        return redirect()->route('dashboard')->with('flash', 'رمز با موفقیت تغییر کرد ✅');
    }
}
