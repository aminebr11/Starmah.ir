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

        $notices = Announcement::forUser($user)->with('sender:id,name')->latest()->limit(60)->get()
            ->map(fn ($a) => [
                'id'       => $a->id,
                'title'    => $a->title,
                'body'     => $a->body,
                'link'     => $a->link,
                'sender'   => $a->sender?->name,
                'personal' => $a->audience === 'personal',
                'date'     => Jalali::format($a->created_at),
            ]);

        // پیام‌های شخصیِ خوانده‌نشده را خوانده علامت بزن + فید زنگوله را «دیده‌شد»
        \Illuminate\Support\Facades\DB::table('announcement_recipients')
            ->where('user_id', $user->id)->whereNull('read_at')->update(['read_at' => now()]);
        \App\Support\Notifications::markSeen($user);

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
        ]);
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
