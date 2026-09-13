<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\SmsMessage;
use App\Models\StudentSmsSetting;
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

    /**
     * دانش‌آموزانی که این کاربر می‌تواند به آن‌ها پیامک بدهد — با شماره‌ها
     * و تنظیمِ اختصاصیِ هرکدام.
     *
     * چرا کامل: کادرِ ارسال باید پیش از زدنِ دکمه نشان دهد چند نفر واقعاً
     * شماره دارند. پیش از این معلم «اولیا» را انتخاب می‌کرد، هیچ‌کس شماره‌ی
     * ولی نداشت و فقط پیامِ «گیرنده‌ای پیدا نشد» می‌گرفت.
     */
    private function students(User $u)
    {
        $admin = $this->isAdmin($u);

        $rows = User::role(Roles::STUDENT)
            ->where('school_id', $u->school_id)
            ->when(! $admin, fn ($q) => $q->whereHas('classrooms', fn ($c) => $c->where('teacher_id', $u->id)))
            ->with(['classrooms:id,name'])
            ->orderBy('name')->get();

        $prefs = StudentSmsSetting::whereIn('student_id', $rows->pluck('id'))->get()->keyBy('student_id');

        return $rows->map(function ($s) use ($prefs) {
            $p = $prefs->get($s->id);

            return [
                'id'        => $s->id,
                'name'      => $s->name,
                'classroom' => $s->classrooms->pluck('name')->implode('، '),
                'class_ids' => $s->classrooms->pluck('id')->all(),
                'phone'     => $s->phone,
                'parents'   => SmsGateway::parentPhones($s),
                'sms'       => [
                    'enabled'    => $p ? (bool) $p->enabled : true,
                    'to_parent'  => $p ? (bool) $p->to_parent : true,
                    'to_student' => $p ? (bool) $p->to_student : false,
                    'events'     => $p?->events,
                    'phone'      => $p?->phone_override,
                    'note'       => $p?->note,
                    'custom'     => (bool) $p,
                ],
            ];
        })->values();
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
            'students'   => $this->students($user),
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
            // سقفِ مدرسه را معلم هم می‌بیند تا بداند چرا یک رویداد
            // با وجودِ روشن‌بودن برای دانش‌آموز پیامک نمی‌شود
            'schoolEvents' => SmsGateway::events($school),
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
            // دسترسیِ دستیارِ هوشمند هم تنظیمِ همین مدرسه است
            $payload['assistant'] = [
                'students' => $school?->assistant_students !== false,
                'teachers' => $school?->assistant_teachers !== false,
                'ai'       => $school?->assistant_ai !== false,
                'ai_ready' => app(\App\Services\AiContentService::class)->isConfigured(),
            ];
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
            return back()->withErrors(['message' => $this->whyNoTargets($user, $data)]);
        }

        $res = SmsGateway::sendMany($user, $user->school, $targets, $data['message'], 'manual');

        return $res['ok']
            ? back()->with('flash', $res['message'])
            : back()->withErrors(['message' => $res['message']]);
    }

    /**
     * چرا گیرنده‌ای پیدا نشد؟
     *
     * پیامِ قبلی («کلاس یا شماره‌ها را بررسی کن») به کاربر نمی‌گفت مشکل
     * کجاست؛ معلمی که شماره‌ی ولیِ دانش‌آموزانش ثبت نشده بود، هرچه
     * می‌فرستاد همین یک جمله را می‌دید و فکر می‌کرد سامانه خراب است.
     */
    private function whyNoTargets(User $user, array $data): string
    {
        if ($data['audience'] === 'manual') {
            return 'هیچ شماره‌ای ننوشته‌اید. شماره‌ها را با فاصله یا کاما جدا کنید.';
        }
        if ($data['audience'] === 'teachers') {
            return $this->isAdmin($user)
                ? 'هیچ معلمی در این مدرسه شماره‌ی موبایل ثبت‌شده ندارد.'
                : 'ارسال به معلم‌ها فقط برای مدیرِ مدرسه است.';
        }

        $pool = $this->studentPool($user, $data);
        if ($pool->isEmpty()) {
            return ! empty($data['student_ids'])
                ? 'دانش‌آموزانِ انتخاب‌شده در دسترسِ شما نیستند.'
                : 'در این کلاس دانش‌آموزی ثبت نشده است.';
        }

        $noParent = $pool->filter(fn ($s) => ! SmsGateway::parentPhones($s))->count();
        $noOwn = $pool->filter(fn ($s) => ! $s->phone)->count();
        $fa = fn ($n) => Jalali::fa((string) $n);

        return match ($data['audience']) {
            'parents' => 'برای ' . $fa($noParent) . ' دانش‌آموزِ انتخاب‌شده شماره‌ی ولی ثبت نشده است. '
                . 'شماره‌ی ولی را در پرونده‌ی دانش‌آموز یا در تبِ «دسترسیِ دانش‌آموزان» وارد کنید.',
            'students' => 'برای ' . $fa($noOwn) . ' دانش‌آموزِ انتخاب‌شده شماره‌ی موبایل ثبت نشده است.',
            default => 'هیچ‌کدام از دانش‌آموزانِ انتخاب‌شده شماره‌ی موبایل یا شماره‌ی ولی ندارند.',
        };
    }

    /** دانش‌آموزانِ هدف — مشترکِ ساختِ گیرنده و تشخیصِ علتِ خالی‌بودن. */
    private function studentPool(User $user, array $data)
    {
        $admin = $this->isAdmin($user);

        return User::role(Roles::STUDENT)->where('school_id', $user->school_id)
            ->when(! empty($data['student_ids']), fn ($q) => $q->whereIn('id', $data['student_ids']))
            ->when(! empty($data['classroom_id']), fn ($q) => $q->whereHas(
                'classrooms', fn ($c) => $c->where('classrooms.id', $data['classroom_id'])
            ))
            // معلم فقط دانش‌آموزانِ کلاسِ خودش
            ->when(! $admin, fn ($q) => $q->whereHas(
                'classrooms', fn ($c) => $c->where('teacher_id', $user->id)
            ))
            ->get();
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

        // دانش‌آموزان: از کلاسِ انتخابی، یا فهرستِ نام‌به‌نامِ انتخاب‌شده
        foreach ($this->studentPool($user, $data) as $s) {
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

    /**
     * دسترسیِ پیامکِ یک دانش‌آموز — تنظیمِ معلمِ همان کلاس (یا مدیر).
     *
     * با `ids` می‌توان همین تنظیم را یک‌جا روی چند دانش‌آموز گذاشت؛
     * وگرنه فقط روی همان یک نفر.
     */
    public function studentAccess(Request $request, User $student): RedirectResponse
    {
        $me = $request->user();
        abort_unless($this->canManage($me, $student), 403);

        $data = $request->validate([
            'enabled'    => ['boolean'],
            'to_parent'  => ['boolean'],
            'to_student' => ['boolean'],
            'events'     => ['nullable', 'array'],
            'events.*'   => ['string', 'in:' . implode(',', array_keys(SmsGateway::EVENTS))],
            'phone'      => ['nullable', 'string', 'max:20'],
            'note'       => ['nullable', 'string', 'max:200'],
            'ids'        => ['nullable', 'array'],
            'ids.*'      => ['integer'],
        ]);

        $payload = [
            'enabled'        => $request->boolean('enabled'),
            'to_parent'      => $request->boolean('to_parent'),
            'to_student'     => $request->boolean('to_student'),
            // آرایه‌ی خالی یعنی «هیچ رویدادی»؛ نبودِ کلید یعنی «هرچه مدرسه گفت»
            'events'         => $request->has('events') ? array_values($data['events'] ?? []) : null,
            'phone_override' => $data['phone'] ?: null,
            'note'           => $data['note'] ?: null,
            'updated_by'     => $me->id,
        ];

        $targets = collect([$student]);
        if (! empty($data['ids'])) {
            $targets = User::role(Roles::STUDENT)->whereIn('id', $data['ids'])->get()
                ->filter(fn ($s) => $this->canManage($me, $s));
        }

        foreach ($targets as $s) {
            StudentSmsSetting::updateOrCreate(
                ['student_id' => $s->id],
                $payload + ['school_id' => $s->school_id],
            );
        }

        $n = $targets->count();

        return back()->with('flash', $n > 1
            ? 'تنظیمِ پیامک برای ' . Jalali::fa((string) $n) . ' دانش‌آموز ذخیره شد ✅'
            : "تنظیمِ پیامکِ «{$student->name}» ذخیره شد ✅");
    }

    /** آیا این کاربر حقِ تنظیمِ پیامکِ این دانش‌آموز را دارد؟ */
    private function canManage(User $me, User $student): bool
    {
        if (! $student->hasRole(Roles::STUDENT) || $student->school_id !== $me->school_id) {
            return false;
        }
        if ($this->isAdmin($me)) {
            return true;
        }

        // معلم فقط دانش‌آموزِ کلاسِ خودش
        return $me->hasRole(Roles::TEACHER)
            && $student->classrooms()->where('teacher_id', $me->id)->exists();
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

    /** مدیرِ مدرسه: دسترسیِ دستیارِ هوشمند. */
    public function assistant(Request $request): RedirectResponse
    {
        $me = $request->user();
        abort_unless($this->isAdmin($me), 403);
        $school = $me->school;
        abort_unless($school !== null, 403);

        $request->validate([
            'students' => ['boolean'], 'teachers' => ['boolean'], 'ai' => ['boolean'],
        ]);
        $school->update([
            'assistant_students' => $request->boolean('students'),
            'assistant_teachers' => $request->boolean('teachers'),
            'assistant_ai'       => $request->boolean('ai'),
        ]);

        return back()->with('flash', 'دسترسیِ دستیارِ هوشمند به‌روز شد ✅');
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
