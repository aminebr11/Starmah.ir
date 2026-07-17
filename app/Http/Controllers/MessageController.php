<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Message;
use App\Models\User;
use App\Support\Jalali;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * صندوقِ پیامِ دوسویه — «ارتباط با معلم/مدیر».
 * دانش‌آموز/والد ↔ معلم و مدیرِ مدرسه؛ معلم ↔ دانش‌آموز/والد و مدیر؛ مدیر ↔ همه.
 * سوابقِ کامل، ویرایشِ پیامِ خود، تاریخِ شمسی.
 */
class MessageController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $contacts = $this->contacts($user);
        $contactIds = array_column($contacts, 'id');

        $withId = (int) $request->query('with');
        $thread = [];
        $active = null;
        if ($withId && in_array($withId, $contactIds, true)) {
            $active = collect($contacts)->firstWhere('id', $withId);
            // پیام‌های دریافتیِ خوانده‌نشده از این طرف → خوانده
            Message::where('sender_id', $withId)->where('recipient_id', $user->id)
                ->whereNull('read_at')->update(['read_at' => now()]);

            $thread = Message::with('sender:id,name')
                ->where(fn ($q) => $q
                    ->where(fn ($a) => $a->where('sender_id', $user->id)->where('recipient_id', $withId))
                    ->orWhere(fn ($b) => $b->where('sender_id', $withId)->where('recipient_id', $user->id)))
                ->orderBy('id')->get()
                ->map(fn ($m) => [
                    'id' => $m->id, 'body' => $m->body,
                    'mine' => $m->sender_id === $user->id,
                    'from' => $m->sender?->name,
                    'date' => Jalali::format($m->created_at, true),
                    'edited' => (bool) $m->edited_at,
                ])->values();
        }

        return Inertia::render('Shared/Messages', [
            'contacts' => $contacts,
            'thread'   => $thread,
            'activeId' => $active['id'] ?? null,
            'activeName' => $active['name'] ?? null,
            'me'       => ['id' => $user->id, 'role' => $this->roleLabel($user)],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
            'body'         => ['required', 'string', 'max:2000'],
        ]);
        // فقط به مخاطبانِ مجاز
        abort_unless(in_array((int) $data['recipient_id'], array_column($this->contacts($user), 'id'), true), 403);

        Message::create([
            'school_id'    => $user->school_id,
            'sender_id'    => $user->id,
            'recipient_id' => $data['recipient_id'],
            'kind'         => 'message',
            'body'         => $data['body'],
        ]);

        return back(303);
    }

    /** ویرایشِ پیامِ خودِ کاربر. */
    public function update(Request $request, Message $message): RedirectResponse
    {
        abort_unless($message->sender_id === $request->user()->id, 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $message->update(['body' => $data['body'], 'edited_at' => now()]);

        return back(303);
    }

    /** حذفِ پیامِ خودِ کاربر. */
    public function destroy(Request $request, Message $message): RedirectResponse
    {
        abort_unless($message->sender_id === $request->user()->id, 403);
        $message->delete();

        return back(303);
    }

    /** فهرستِ مخاطبانِ مجاز بر اساسِ نقش + شمارشِ پیامِ نخوانده. */
    private function contacts(User $user): array
    {
        $ids = collect();

        if ($user->hasRole(Roles::SCHOOL_ADMIN)) {
            $ids = User::where('school_id', $user->school_id)->where('id', '!=', $user->id)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', [Roles::TEACHER, Roles::PARENT, Roles::STUDENT]))
                ->pluck('id');
        } elseif ($user->hasRole(Roles::TEACHER)) {
            $studentIds = User::whereHas('classrooms', fn ($q) => $q->where('teacher_id', $user->id))->pluck('id');
            $parentIds = DB::table('parent_student')->whereIn('student_id', $studentIds)->pluck('parent_id');
            $adminIds = $this->schoolAdminIds($user);
            $ids = $studentIds->merge($parentIds)->merge($adminIds)->unique();
        } elseif ($user->hasRole(Roles::STUDENT)) {
            $teacherIds = $user->classrooms()->pluck('teacher_id')->filter();
            $ids = $teacherIds->merge($this->schoolAdminIds($user))->unique();
        } elseif ($user->hasRole(Roles::PARENT)) {
            $childIds = $user->children()->pluck('users.id');
            $teacherIds = Classroom::whereHas('students', fn ($q) => $q->whereIn('users.id', $childIds))->pluck('teacher_id')->filter();
            $ids = $teacherIds->merge($this->schoolAdminIds($user))->unique();
        }

        $ids = $ids->filter()->unique()->values();
        if ($ids->isEmpty()) return [];

        // شمارشِ نخوانده از هر طرف
        $unread = Message::where('recipient_id', $user->id)->whereNull('read_at')
            ->whereIn('sender_id', $ids)->select('sender_id', DB::raw('count(*) as c'))
            ->groupBy('sender_id')->pluck('c', 'sender_id');
        // آخرین پیام برای مرتب‌سازی
        $lastAt = Message::where(fn ($q) => $q->whereIn('sender_id', $ids)->orWhereIn('recipient_id', $ids))
            ->where(fn ($q) => $q->where('sender_id', $user->id)->orWhere('recipient_id', $user->id))
            ->get(['sender_id', 'recipient_id', 'id'])
            ->groupBy(fn ($m) => $m->sender_id === $user->id ? $m->recipient_id : $m->sender_id)
            ->map(fn ($g) => $g->max('id'));

        return User::whereIn('id', $ids)->with('roles:id,name')->get(['id', 'name'])
            ->map(fn ($u) => [
                'id' => $u->id, 'name' => $u->name, 'role' => $this->roleLabel($u),
                'unread' => (int) ($unread[$u->id] ?? 0),
                'last' => (int) ($lastAt[$u->id] ?? 0),
            ])
            ->sortByDesc(fn ($c) => [$c['unread'] > 0 ? 1 : 0, $c['last']])
            ->values()->all();
    }

    private function schoolAdminIds(User $user): \Illuminate\Support\Collection
    {
        if (! $user->school_id) return collect();
        return User::where('school_id', $user->school_id)
            ->whereHas('roles', fn ($q) => $q->where('name', Roles::SCHOOL_ADMIN))
            ->pluck('id');
    }

    private function roleLabel(User $u): string
    {
        return match (true) {
            $u->hasRole(Roles::SCHOOL_ADMIN) => 'مدیرِ مدرسه',
            $u->hasRole(Roles::TEACHER) => 'معلم',
            $u->hasRole(Roles::PARENT) => 'والد',
            $u->hasRole(Roles::STUDENT) => 'دانش‌آموز',
            default => 'کاربر',
        };
    }
}
