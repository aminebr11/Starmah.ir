<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ParentNote;
use App\Services\AnalyticsService;
use App\Support\Jalali;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «بخشِ والدین» داخلِ پرتالِ دانش‌آموز — با رمزِ مخصوصِ والدین قفل است.
 * بعد از بازشدن: گزارشِ محرمانه‌ی فرزند + پیام‌های محرمانه‌ی معلم/مدیر + پاسخِ والد.
 */
class FamilyController extends Controller
{
    private function sessionKey(int $studentId): string
    {
        return "family_unlocked_{$studentId}";
    }

    private function pin(Request $request): ?string
    {
        $pin = data_get($request->user()->settings, 'guardian.pin');

        return $pin !== null && $pin !== '' ? (string) $pin : null;
    }

    public function index(Request $request, AnalyticsService $analytics): Response
    {
        $user = $request->user();
        $unlocked = (bool) $request->session()->get($this->sessionKey($user->id), false);
        $hasPin = $this->pin($request) !== null;

        $payload = ['unlocked' => $unlocked, 'hasPin' => $hasPin];

        if ($unlocked) {
            // ورودِ والد → پیام‌های کادرِ مدرسه خوانده‌شده علامت می‌خورند
            ParentNote::where('student_id', $user->id)->where('from_parent', false)
                ->whereNull('read_at')->update(['read_at' => now()]);

            $payload['report'] = $analytics->childReport($user);
            $payload['notes'] = ParentNote::where('student_id', $user->id)
                ->with('sender:id,name')->latest()->limit(50)->get()
                ->map(fn ($n) => [
                    'id' => $n->id,
                    'from_parent' => $n->from_parent,
                    'sender' => $n->from_parent ? 'والدِ دانش‌آموز' : ($n->sender?->name ?? 'مدرسه'),
                    'title' => $n->title,
                    'body' => $n->body,
                    'date' => Jalali::format($n->created_at, true),
                ])->values();
        }

        return Inertia::render('Student/Family', $payload);
    }

    /** بازکردنِ قفل با رمزِ والدین (با محدودیتِ تلاش). */
    public function unlock(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->validate(['pin' => ['required', 'string', 'max:20']]);

        $key = 'family-pin:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['pin' => 'تلاشِ زیاد — چند دقیقه صبر کنید.']);
        }

        $pin = $this->pin($request);
        if ($pin === null || ! hash_equals($pin, trim((string) $request->pin))) {
            RateLimiter::hit($key, 300);

            return back()->withErrors(['pin' => 'رمزِ والدین درست نیست.']);
        }

        RateLimiter::clear($key);
        $request->session()->put($this->sessionKey($user->id), true);

        return redirect()->route('family');
    }

    /** خروج از بخشِ والدین (قفلِ دوباره). */
    public function lock(Request $request): RedirectResponse
    {
        $request->session()->forget($this->sessionKey($request->user()->id));

        return redirect()->route('dashboard');
    }

    /** پاسخِ والد به مدرسه (فقط وقتی قفل باز است). */
    public function reply(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($request->session()->get($this->sessionKey($user->id), false), 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        ParentNote::create([
            'school_id' => $user->school_id,
            'student_id' => $user->id,
            'sender_id' => null,
            'from_parent' => true,
            'body' => $data['body'],
        ]);

        return back()->with('flash', 'پیامِ شما برای مدرسه ارسال شد ✅');
    }
}
