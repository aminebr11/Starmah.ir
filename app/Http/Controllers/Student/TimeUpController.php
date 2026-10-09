<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\WellbeingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

/** «⏰ وقتِ استراحت»: بعد از سقفِ زمان؛ بازکردن فقط با رمزِ والدین. */
class TimeUpController extends Controller
{
    public function show(Request $request, WellbeingService $wb): Response|RedirectResponse
    {
        $user = $request->user();
        $status = $wb->status($user);
        if (! $status['locked']) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Student/TimeUp', [
            'status' => $status,
            'hasPin' => filled(data_get($user->settings, 'guardian.pin')),
        ]);
    }

    /** والدین: رمز → صفر کردنِ زمانِ امروز و (اختیاری) سقفِ کمتر. */
    public function unlock(Request $request, WellbeingService $wb): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'pin' => ['required', 'string', 'max:20'],
            'daily' => ['nullable', 'integer', 'min:0', 'max:600'],
            'session' => ['nullable', 'integer', 'min:0', 'max:300'],
        ], ['pin.required' => 'رمزِ والدین را وارد کنید.']);

        $key = 'family-pin:' . $user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['pin' => 'تلاشِ زیاد — چند دقیقه صبر کنید.']);
        }
        $pin = (string) data_get($user->settings, 'guardian.pin', '');
        if ($pin === '' || ! hash_equals($pin, trim($data['pin']))) {
            RateLimiter::hit($key, 300);

            return back()->withErrors(['pin' => 'رمزِ والدین درست نیست.']);
        }
        RateLimiter::clear($key);

        if ($request->has('daily') || $request->has('session')) {
            $wb->setParentLimits($user, (int) ($data['daily'] ?? 0), (int) ($data['session'] ?? 0));
        }
        $wb->reset($user);

        return redirect()->route('dashboard')->with('flash', '🔓 والدین زمان را از نو شروع کردند. خوش بگذرد و یادت نرود بینِ کار استراحت کنی! 🌿');
    }

    /** از داخلِ «بخشِ والدین» (قفلِ والدین باز است): تنظیمِ سقف یا صفر کردن. */
    public function family(Request $request, WellbeingService $wb): RedirectResponse
    {
        $user = $request->user();
        abort_unless($request->session()->get("family_unlocked_{$user->id}", false), 403);
        $data = $request->validate([
            'daily' => ['nullable', 'integer', 'min:0', 'max:600'],
            'session' => ['nullable', 'integer', 'min:0', 'max:300'],
            'reset' => ['nullable', 'boolean'],
        ]);
        $wb->setParentLimits($user, (int) ($data['daily'] ?? 0), (int) ($data['session'] ?? 0));
        if (! empty($data['reset'])) {
            $wb->reset($user);
        }

        return back()->with('flash', '✅ تنظیماتِ زمانِ استفاده ذخیره شد.');
    }
}
