<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\SmsMessage;
use App\Models\User;
use App\Support\Jalali;
use App\Support\Roles;
use App\Support\SmsGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * سامانه‌ی پیامکِ مدرسه — مشترکِ مدیرِ مدرسه و معلم.
 *
 * تفاوتِ دو نقش فقط در «دامنه‌ی گیرنده» و «اجازه‌ی تنظیمات» است:
 *   مدیرِ مدرسه → همه‌ی کلاس‌ها و معلم‌ها + ماتریسِ رویدادها + اجازه‌ی معلم‌ها
 *   معلم        → فقط کلاس‌های خودش، و فقط اگر مدیر اجازه داده باشد
 */
class SmsController extends Controller
{
    private function isAdmin(User $u): bool
    {
        return $u->hasRole(Roles::SCHOOL_ADMIN) || $u->hasRole(Roles::SUPER_ADMIN);
    }

    /** کلاس‌هایی که این کاربر می‌تواند به آن‌ها پیامک بدهد. */
    private function classrooms(User $u)
    {
        $q = Classroom::query();
        if (! $this->isAdmin($u)) {
            $q->where('teacher_id', $u->id);
        }

        return $q->withCount('students')->orderBy('name')->get()
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'students' => $c->students_count]);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $admin = $this->isAdmin($user);
        $school = $user->school;
        $can = SmsGateway::can($user);

        $payload = [
            'role'       => $admin ? 'school_admin' : 'teacher',
            'can'        => $can,
            'classrooms' => $this->classrooms($user),
            'quota'      => [
                'window'          => SmsGateway::WINDOW_DAYS,
                'school_quota'    => $school?->sms_quota,
                'school_used'     => $school ? SmsGateway::usedBySchool($school->id) : 0,
                'school_left'     => SmsGateway::remainingForSchool($school),
                'my_quota'        => $user->sms_quota,
                'my_used'         => SmsGateway::usedByUser($user->id),
                'my_left'         => SmsGateway::remainingForUser($user),
            ],
            'log'    => $this->log($user, $admin),
            'events' => SmsGateway::EVENTS,
            'audiences' => SmsGateway::AUDIENCES,
        ];

        if ($admin) {
            $payload['teachers'] = User::role(Roles::TEACHER)->where('school_id', $user->school_id)
                ->orderBy('name')->get(['id', 'name', 'phone', 'sms_allowed', 'sms_quota'])
                ->map(fn ($t) => [
                    'id' => $t->id, 'name' => $t->name, 'phone' => $t->phone,
                    'allowed' => (bool) $t->sms_allowed, 'quota' => $t->sms_quota,
                    'used' => SmsGateway::usedByUser($t->id),
                ]);
            $payload['eventConfig'] = SmsGateway::events($school);
            $payload['sender'] = $school?->sms_sender;
        }

        return Inertia::render($admin ? 'SchoolAdmin/Sms' : 'Teacher/Sms', $payload);
    }

    /** سابقه — مدیر همه‌ی مدرسه را می‌بیند، معلم فقط ارسال‌های خودش را. */
    private function log(User $user, bool $admin): array
    {
        return SmsMessage::with(['sender:id,name', 'recipient:id,name'])
            ->when(! $admin, fn ($q) => $q->where('sender_id', $user->id))
            ->latest()->limit(50)->get()
            ->map(fn ($m) => [
                'id'       => $m->id,
                'sender'   => $m->sender?->name ?? 'سامانه',
                'to'       => $m->recipient?->name,
                'phone'    => $m->phone,
                'body'     => \Illuminate\Support\Str::limit($m->body, 90),
                'kind'     => SmsGateway::EVENTS[$m->kind]['label'] ?? 'ارسالِ دستی',
                'status'   => $m->status,
                'error'    => $m->error,
                'segments' => $m->segments,
                'date'     => Jalali::format($m->created_at) . ' ساعت ' . Jalali::fa($m->created_at->format('H:i')),
            ])->all();
    }

    /** ارسالِ دستی. */
    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();
        $can = SmsGateway::can($user);
        if (! $can['ok']) {
            return back()->withErrors(['message' => $can['reason']]);
        }

        $data = $request->validate([
            'message'      => ['required', 'string', 'max:600'],
            'audience'     => ['required', 'in:students,parents,both,teachers,manual'],
            'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
            'student_ids'  => ['nullable', 'array'],
            'student_ids.*' => ['integer'],
            'phones'       => ['nullable', 'string', 'max:4000'],
        ]);

        $targets = $this->targets($user, $data);
        if (! $targets) {
            return back()->withErrors(['message' => 'گیرنده‌ای پیدا نشد. کلاس یا شماره‌ها را بررسی کن.']);
        }

        $res = SmsGateway::sendMany($user, $user->school, $targets, $data['message'], 'manual');

        return $res['ok']
            ? back()->with('flash', $res['message'])
            : back()->withErrors(['message' => $res['message']]);
    }

    /**
     * ساختنِ فهرستِ گیرنده‌ها بر اساسِ انتخابِ کاربر.
     *
     * @return array<int,array{user:?User,phone:string}>
     */
    private function targets(User $user, array $data): array
    {
        $admin = $this->isAdmin($user);
        $out = [];

        if ($data['audience'] === 'manual') {
            foreach (preg_split('/[\s,;\n]+/', (string) ($data['phones'] ?? '')) as $p) {
                if (trim($p) !== '') {
                    $out[] = ['user' => null, 'phone' => trim($p)];
                }
            }

            return $out;
        }

        if ($data['audience'] === 'teachers') {
            if (! $admin) {
                return [];
            }
            foreach (User::role(Roles::TEACHER)->where('school_id', $user->school_id)->get() as $t) {
                if ($t->phone) {
                    $out[] = ['user' => $t, 'phone' => $t->phone];
                }
            }

            return $out;
        }

        // دانش‌آموزان: از کلاسِ انتخابی، یا فهرستِ دستیِ دانش‌آموزان
        $students = User::role(Roles::STUDENT)->where('school_id', $user->school_id)
            ->when(! empty($data['student_ids']), fn ($q) => $q->whereIn('id', $data['student_ids']))
            ->when(! empty($data['classroom_id']), fn ($q) => $q->whereHas(
                'classrooms', fn ($c) => $c->where('classrooms.id', $data['classroom_id'])
            ))
            // معلم فقط دانش‌آموزانِ کلاسِ خودش
            ->when(! $admin, fn ($q) => $q->whereHas(
                'classrooms', fn ($c) => $c->where('teacher_id', $user->id)
            ))
            ->get();

        foreach ($students as $s) {
            if (in_array($data['audience'], ['students', 'both'], true) && $s->phone) {
                $out[] = ['user' => $s, 'phone' => $s->phone];
            }
            if (in_array($data['audience'], ['parents', 'both'], true)) {
                foreach (SmsGateway::parentPhones($s) as $p) {
                    $out[] = ['user' => $s, 'phone' => $p];
                }
            }
        }

        return $out;
    }

    /** مدیرِ مدرسه: اجازه و سهمیه‌ی یک معلم. */
    public function teacher(Request $request, User $user): RedirectResponse
    {
        $me = $request->user();
        abort_unless($this->isAdmin($me), 403);
        abort_unless($user->school_id === $me->school_id && $user->hasRole(Roles::TEACHER), 403);

        $data = $request->validate([
            'sms_allowed' => ['boolean'],
            'sms_quota'   => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $user->update([
            'sms_allowed' => $request->boolean('sms_allowed'),
            'sms_quota'   => $data['sms_quota'] ?? null,
        ]);

        return back()->with('flash', "دسترسیِ پیامکِ «{$user->name}» به‌روز شد ✅");
    }

    /** مدیرِ مدرسه: کدام اعلان‌ها پیامک شوند. */
    public function events(Request $request): RedirectResponse
    {
        $me = $request->user();
        abort_unless($this->isAdmin($me), 403);
        $school = $me->school;
        abort_unless($school !== null, 403);

        $data = $request->validate([
            'events'   => ['nullable', 'array'],
            'events.*.parent'  => ['nullable', 'boolean'],
            'events.*.student' => ['nullable', 'boolean'],
            'sender'   => ['nullable', 'string', 'max:40'],
        ]);

        $clean = [];
        foreach (array_keys(SmsGateway::EVENTS) as $key) {
            $clean[$key] = [
                'parent'  => (bool) ($data['events'][$key]['parent'] ?? false),
                'student' => (bool) ($data['events'][$key]['student'] ?? false),
            ];
        }
        $school->update(['sms_events' => $clean, 'sms_sender' => $data['sender'] ?: $school->sms_sender]);

        return back()->with('flash', 'تنظیماتِ پیامکِ اعلان‌ها ذخیره شد ✅');
    }
}
