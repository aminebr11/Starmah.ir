<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * بازیابیِ رمز با «کدِ پیامکی» — روشِ دومِ فراموشیِ رمز (در کنارِ ایمیل).
 * وابسته به «سامانه‌ی پیامکِ» تنظیم‌شده در تنظیماتِ ادمین.
 */
class PhoneResetController extends Controller
{
    public function create(Request $request, SmsService $sms): Response
    {
        return Inertia::render('Auth/PhoneReset', [
            'smsEnabled' => $sms->enabled(),
            'sent'       => (bool) $request->session()->get('otp_phone'),
            'phone'      => $request->session()->get('otp_phone'),
        ]);
    }

    /** گامِ ۱: ارسالِ کد به موبایل. */
    public function send(Request $request, SmsService $sms): RedirectResponse
    {
        $request->validate(['phone' => ['required', 'string', 'max:20']]);
        $phone = preg_replace('/\D/', '', $request->phone);

        $key = 'otp-send:'.$phone;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->withErrors(['phone' => 'درخواستِ زیاد — چند دقیقه صبر کنید.']);
        }
        RateLimiter::hit($key, 120);

        // برای جلوگیری از افشای وجودِ حساب، پیام یکسان است؛ فقط اگر کاربر باشد کد می‌فرستیم
        $user = User::where('phone', $phone)->first();
        if ($user) {
            $code = (string) random_int(100000, 999999);
            Cache::put('otp:'.$phone, $code, now()->addMinutes(5));
            $sms->send($phone, "ستاره ماه: کدِ بازیابیِ رمزِ شما: {$code}\nتا ۵ دقیقه معتبر است.");
        }

        $request->session()->put('otp_phone', $phone);

        return back()->with('flash', 'اگر این شماره در سامانه ثبت باشد، کدِ بازیابی برایش پیامک شد.');
    }

    /** گامِ ۲: تأییدِ کد و تنظیمِ رمزِ جدید. */
    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone'    => ['required', 'string', 'max:20'],
            'code'     => ['required', 'string', 'max:6'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], ['password.confirmed' => 'تکرارِ رمز یکی نیست.']);
        $phone = preg_replace('/\D/', '', $data['phone']);

        $key = 'otp-verify:'.$phone;
        if (RateLimiter::tooManyAttempts($key, 6)) {
            return back()->withErrors(['code' => 'تلاشِ زیاد — بعداً دوباره امتحان کنید.']);
        }

        $expected = Cache::get('otp:'.$phone);
        if (! $expected || ! hash_equals($expected, trim($data['code']))) {
            RateLimiter::hit($key, 300);

            return back()->withErrors(['code' => 'کد نادرست یا منقضی است.']);
        }

        $user = User::where('phone', $phone)->first();
        if (! $user) {
            return back()->withErrors(['phone' => 'کاربری با این شماره یافت نشد.']);
        }

        $user->update(['password' => Hash::make($data['password']), 'must_change_password' => false]);
        Cache::forget('otp:'.$phone);
        RateLimiter::clear($key);
        $request->session()->forget('otp_phone');

        return redirect()->route('login')->with('status', 'رمزِ شما با موفقیت تغییر کرد؛ اکنون وارد شوید.');
    }
}
