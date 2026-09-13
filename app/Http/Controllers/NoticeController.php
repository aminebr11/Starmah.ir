<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Support\Jalali;
use App\Support\Roles;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** کارتابل اعلان‌ها/پیام‌ها برای معلم و دانش‌آموز (اطلاعیه‌های عمومی + پیام‌های شخصی). */
class NoticeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        // همان فیدِ یکپارچه‌ی زنگوله — نه فقط جدولِ اطلاعیه‌ها.
        // پیش از این صفحه‌ی اعلان‌ها فقط Announcement را می‌خواند، پس پیامِ
        // صندوق، پاسخِ والدین، موردِ انضباطی و یادآورِ مأموریت اینجا اصلاً
        // دیده نمی‌شدند.
        $notices = \App\Support\Notifications::all($user, 80);

        // دیگر همه را خودکار «خوانده» نمی‌کنیم؛ کاربر خودش علامت می‌زند.

        // هر نقش باید صفحه و منویِ خودش را بگیرد.
        // پیش از این هر کسی جز معلم صفحه‌ی دانش‌آموز می‌گرفت، و منویِ
        // دانش‌آموز برای مدیرِ مدرسه و ادمینِ کل ۱۱ لینکِ ۴۰۳ تولید می‌کرد.
        $role = match (true) {
            $user->hasRole(Roles::TEACHER)      => Roles::TEACHER,
            $user->hasRole(Roles::STUDENT)      => Roles::STUDENT,
            $user->hasRole(Roles::SCHOOL_ADMIN) => Roles::SCHOOL_ADMIN,
            $user->hasRole(Roles::SUPER_ADMIN)  => Roles::SUPER_ADMIN,
            default                             => 'parent',
        };

        $component = match ($role) {
            Roles::TEACHER => 'Teacher/Notices',
            Roles::STUDENT => 'Student/Notices',
            default        => 'Notices',   // ادمین کل، مدیرِ مدرسه و والد
        };

        return Inertia::render($component, [
            'notices' => $notices,
            'role'    => $role,
            'unread'  => collect($notices)->where('read', false)->count(),
        ]);
    }

    /** «مطالعه شد» برای یک ردیفِ فید. */
    public function read(Request $request, string $key): \Illuminate\Http\RedirectResponse
    {
        \App\Support\Notifications::markRead($request->user(), $key);

        return back(303);
    }

    /** «همه را خواندم». */
    public function readAll(Request $request): \Illuminate\Http\RedirectResponse
    {
        \App\Support\Notifications::markAllRead($request->user());

        return back(303)->with('flash', 'همه‌ی اعلان‌ها «مطالعه‌شده» علامت خوردند ✅');
    }

    /** حذف/پنهان‌کردنِ یک اعلان فقط برای همین کاربر. */
    public function dismiss(Request $request, Announcement $announcement): \Illuminate\Http\RedirectResponse
    {
        \Illuminate\Support\Facades\DB::table('announcement_dismissals')->updateOrInsert(
            ['user_id' => $request->user()->id, 'announcement_id' => $announcement->id],
            ['updated_at' => now(), 'created_at' => now()],
        );
        return back()->with('flash', 'اعلان حذف شد');
    }

    /** حذف/پنهان‌کردنِ همه‌ی اعلان‌های فعلیِ کاربر. */
    public function clear(Request $request): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        $ids = Announcement::forUser($user)->pluck('id');
        $now = now();
        $rows = $ids->map(fn ($id) => [
            'user_id' => $user->id, 'announcement_id' => $id, 'created_at' => $now, 'updated_at' => $now,
        ])->all();
        if ($rows) {
            \Illuminate\Support\Facades\DB::table('announcement_dismissals')->insertOrIgnore($rows);
        }
        return back()->with('flash', 'همه‌ی اعلان‌ها حذف شدند');
    }
}
