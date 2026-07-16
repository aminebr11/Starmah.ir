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

        $component = $user->hasRole(Roles::TEACHER) ? 'Teacher/Notices' : 'Student/Notices';

        return Inertia::render($component, ['notices' => $notices]);
    }
}
