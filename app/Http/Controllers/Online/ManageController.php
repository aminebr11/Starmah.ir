<?php

namespace App\Http\Controllers\Online;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\OnlineParticipant;
use App\Models\OnlineRoom;
use App\Models\School;
use App\Models\Setting;
use App\Models\User;
use App\Services\OnlineClassService;
use App\Support\Jalali;
use App\Support\OnlineAccess;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * صفحه‌های مدیریتیِ «کلاسِ آنلاین»: فهرستِ معلم، فهرستِ دانش‌آموز،
 * کنترلِ مدیرِ مدرسه و تنظیماتِ ادمینِ کل.
 */
class ManageController extends Controller
{
    public function __construct(private OnlineClassService $svc) {}

    /** خلاصه‌ی یک کلاس برای فهرست‌ها. */
    private function card(OnlineRoom $r, bool $withStats = false): array
    {
        $online = $r->live() ? OnlineParticipant::where('room_id', $r->id)->whereNull('left_at')
            ->where('last_seen_at', '>=', now()->subSeconds(OnlineParticipant::ONLINE_SEC))->where('role', 'student')->count() : 0;
        $out = [
            'id' => $r->id, 'title' => $r->title, 'status' => $r->status, 'classroom' => $r->classroom?->name ?? 'همه‌ی کلاس‌هایم',
            'classroom_id' => $r->classroom_id, 'teacher' => $r->teacher?->name,
            'when' => $r->starts_at ? Jalali::format($r->starts_at) . ' ساعت ' . Jalali::fa($r->starts_at->format('H:i')) : null,
            'starts_at' => $r->starts_at?->format('Y-m-d H:i') ?? '', 'duration_min' => $r->duration_min,
            'started' => $r->started_at ? Jalali::format($r->started_at) . ' ساعت ' . Jalali::fa($r->started_at->format('H:i')) : null,
            'length' => $r->started_at ? (int) round(($r->ended_at ?? now())->diffInSeconds($r->started_at, true) / 60) : null,
            'online' => $online, 'recording' => (bool) $r->recording_path, 'recording_size' => $r->recording_size,
            'recording_shared' => (bool) $r->recording_shared,
        ];
        if ($withStats) {
            $out['present'] = $r->present_count ?? OnlineParticipant::where('room_id', $r->id)->where('role', 'student')->count();
        }

        return $out;
    }

    /** فهرستِ فیلم‌های ضبط‌شده (جدیدترین اول) برای صفحه‌ها. */
    private function recordings($roomQuery, int $limit = 100): array
    {
        if (! \App\Models\OnlineRecording::ready()) {
            return [];
        }
        $rooms = (clone $roomQuery)->with('classroom:id,name')->get(['id', 'title', 'classroom_id', 'recording_shared', 'teacher_id'])->keyBy('id');
        if ($rooms->isEmpty()) {
            return [];
        }
        $names = \App\Models\User::whereIn('id', $rooms->pluck('teacher_id')->unique())->pluck('name', 'id');

        return \App\Models\OnlineRecording::whereIn('room_id', $rooms->keys())->where('size', '>', 0)->latest('id')->limit($limit)->get()
            ->map(fn ($r) => $r->card() + [
                'title' => $rooms[$r->room_id]->title, 'classroom' => $rooms[$r->room_id]->classroom?->name ?? 'همه‌ی کلاس‌ها',
                'teacher' => $names[$rooms[$r->room_id]->teacher_id] ?? null, 'shared' => (bool) $rooms[$r->room_id]->recording_shared,
            ])->values()->all();
    }

    // ── معلم ─────────────────────────────────────────────────────────────

    public function teacher(Request $request): Response
    {
        $t = $request->user();
        $ready = OnlineAccess::ready();
        $rooms = $ready ? OnlineRoom::with(['classroom:id,name', 'teacher:id,name'])->withCount(['participants as present_count' => fn ($q) => $q->where('role', 'student')])
            ->where('teacher_id', $t->id)->latest('id')->limit(60)->get() : collect();
        $rooms->each->closeIfAbandoned();

        return Inertia::render('Teacher/OnlineClasses', [
            'ready' => $ready,
            'allowed' => OnlineAccess::canHost($t),
            'supervisor' => $ready && OnlineAccess::canSupervise($t),
            'schoolOn' => OnlineAccess::schoolEnabled($t->school_id),
            'media' => OnlineAccess::media(),
            'max' => OnlineAccess::maxParticipants($t->school_id),
            'rooms' => $rooms->map(fn ($r) => $this->card($r, true))->values(),
            'recordings' => $ready ? $this->recordings(OnlineRoom::where('teacher_id', $t->id)) : [],
            'classrooms' => Classroom::where('teacher_id', $t->id)->withCount('students')->get(['id', 'name'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'students' => $c->students_count]),
        ]);
    }

    private function roomData(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:150', 'classroom_id' => 'nullable|integer',
            'starts_at' => 'nullable|date', 'duration_min' => 'nullable|integer|min:5|max:300', 'recording_shared' => 'nullable|boolean',
        ], [], ['title' => 'عنوان', 'starts_at' => 'زمانِ شروع', 'duration_min' => 'مدت']);
        if (! empty($data['classroom_id'])) {
            abort_unless(Classroom::where('id', $data['classroom_id'])->where('teacher_id', $request->user()->id)->exists(), 403);
        }

        return ['title' => $data['title'], 'classroom_id' => $data['classroom_id'] ?: null, 'starts_at' => $data['starts_at'] ?? null,
            'duration_min' => $data['duration_min'] ?? null, 'recording_shared' => $request->boolean('recording_shared', true)];
    }

    public function store(Request $request): RedirectResponse
    {
        $t = $request->user();
        abort_unless(OnlineAccess::canHost($t), 403, 'مدیرِ مدرسه هنوز اجازه‌ی برگزاریِ کلاسِ آنلاین را به شما نداده است.');
        $room = OnlineRoom::create($this->roomData($request) + ['school_id' => $t->school_id, 'teacher_id' => $t->id, 'status' => 'scheduled',
            'settings' => ['chat' => true, 'page' => 0, 'stage' => 'board']]);

        if ($request->boolean('now')) {
            return redirect()->route('online.room', $room);
        }
        if ($room->starts_at) {
            $this->svc->notifyScheduled($room);
        }

        return back()->with('success', '🎥 کلاسِ «' . $room->title . '» ساخته شد.');
    }

    public function update(Request $request, OnlineRoom $room): RedirectResponse
    {
        abort_unless((int) $room->teacher_id === (int) $request->user()->id, 403);
        $room->update($this->roomData($request));

        return back()->with('success', '✏️ کلاس ویرایش شد.');
    }

    public function destroy(Request $request, OnlineRoom $room): RedirectResponse
    {
        abort_unless((int) $room->teacher_id === (int) $request->user()->id, 403);
        abort_if($room->live(), 422, 'کلاسِ در حالِ برگزاری را اول تمام کنید.');
        $this->purge($room);

        return back()->with('success', '🗑️ کلاس حذف شد.');
    }

    private function purge(OnlineRoom $room): void
    {
        Storage::disk('local')->deleteDirectory("online/{$room->id}");
        foreach (['online_participants', 'online_messages', 'online_board_pages', 'online_signals'] as $t) {
            DB::table($t)->where('room_id', $room->id)->delete();
        }
        $polls = DB::table('online_polls')->where('room_id', $room->id)->pluck('id');
        DB::table('online_poll_answers')->whereIn('poll_id', $polls)->delete();
        DB::table('online_polls')->where('room_id', $room->id)->delete();
        $room->delete();
    }

    /** اشتراکِ فیلمِ ضبط‌شده با بچه‌ها یا حذفِ آن. */
    public function recording(Request $request, OnlineRoom $room): RedirectResponse
    {
        abort_unless((int) $room->teacher_id === (int) $request->user()->id || OnlineAccess::canSupervise($request->user(), $room), 403);
        if ($request->boolean('delete')) {
            $recs = \App\Models\OnlineRecording::ready() ? \App\Models\OnlineRecording::where('room_id', $room->id)
                ->when($request->filled('rec'), fn ($q) => $q->whereKey($request->integer('rec')))->get() : collect();
            foreach ($recs as $rec) {
                Storage::disk('local')->delete($rec->path);
                $rec->delete();
            }
            if (! $request->filled('rec') && $room->recording_path) {
                Storage::disk('local')->delete($room->recording_path);
            }
            // فیلمِ «آخرِ» کلاس: جدیدترینِ باقی‌مانده
            $last = \App\Models\OnlineRecording::ready() ? \App\Models\OnlineRecording::where('room_id', $room->id)->latest('id')->first() : null;
            $room->forceFill(['recording_path' => $last?->path, 'recording_size' => $last?->size])->save();

            return back()->with('success', 'فیلمِ ضبط‌شده حذف شد.');
        }
        $room->forceFill(['recording_shared' => $request->boolean('shared')])->save();

        return back();
    }

    // ── دانش‌آموز ───────────────────────────────────────────────────────

    public function student(Request $request): Response
    {
        $s = $request->user();
        $on = OnlineAccess::ready() && OnlineAccess::schoolEnabled($s->school_id);
        $rooms = $on ? OnlineRoom::forStudent($s)->with(['classroom:id,name', 'teacher:id,name'])->where('status', '!=', 'ended')
            ->orderByRaw("case status when 'live' then 0 when 'scheduled' then 1 else 2 end")->latest('id')->limit(40)->get() : collect();
        $rooms->each->closeIfAbandoned();
        $mine = $on ? OnlineParticipant::where('user_id', $s->id)->whereIn('room_id', $rooms->pluck('id'))->get()->keyBy('room_id') : collect();

        return Inertia::render('Student/OnlineClasses', [
            'on' => $on,
            'rooms' => $rooms->map(fn ($r) => $this->card($r) + ['mine' => isset($mine[$r->id]) ? ['minutes' => (int) round($mine[$r->id]->seconds / 60),
                'stars' => (int) $mine[$r->id]->stars, 'xp' => (int) $mine[$r->id]->xp] : null])->values(),
            // فیلم‌هایی که معلم برای بچه‌ها گذاشته (از همه‌ی کلاس‌های من)
            'recordings' => $on ? $this->recordings(OnlineRoom::forStudent($s)->where('recording_shared', true), 60) : [],
        ]);
    }

    // ── مدیرِ مدرسه ─────────────────────────────────────────────────────

    public function school(Request $request): Response
    {
        $u = $request->user();
        $ready = OnlineAccess::ready();
        $sid = $u->school_id;
        $hosts = $ready ? DB::table('online_hosts')->where('school_id', $sid)->get()->keyBy('user_id') : collect();
        $teachers = User::role(Roles::TEACHER)->where('school_id', $sid)->orderBy('name')->get(['id', 'name']);
        $rooms = $ready ? OnlineRoom::with(['classroom:id,name', 'teacher:id,name'])->withCount(['participants as present_count' => fn ($q) => $q->where('role', 'student')])
            ->where('school_id', $sid)->latest('id')->limit(80)->get() : collect();
        $rooms->each->closeIfAbandoned();
        $month = $ready ? OnlineRoom::where('school_id', $sid)->where('started_at', '>=', now()->subDays(30))->get() : collect();

        return Inertia::render('SchoolAdmin/OnlineClasses', [
            'ready' => $ready,
            'on' => OnlineAccess::schoolEnabled($sid),
            'max' => OnlineAccess::maxParticipants($sid),
            'media' => OnlineAccess::media(),
            'teachers' => $teachers->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'can_host' => (bool) ($hosts[$t->id]->can_host ?? false),
                'supervisor' => (bool) ($hosts[$t->id]->supervisor ?? false),
                'rooms' => $rooms->where('teacher_id', $t->id)->count()]),
            'rooms' => $rooms->map(fn ($r) => $this->card($r, true))->values(),
            'stats' => ['classes' => $month->count(), 'minutes' => (int) $month->sum(fn ($r) => $r->started_at ? ($r->ended_at ?? now())->diffInMinutes($r->started_at, true) : 0),
                'live' => $rooms->where('status', 'live')->count()],
        ]);
    }

    /** 🛡️ صفحه‌ی ناظم: کلاس‌های زنده، برنامه‌ریزی‌شده و گذشته‌ی کلِ مدرسه. */
    public function supervise(Request $request): Response|RedirectResponse
    {
        $u = $request->user();
        if ($u->hasRole(Roles::SUPER_ADMIN)) {
            return redirect()->route('admin.online');
        }
        if ($u->hasRole(Roles::SCHOOL_ADMIN)) {
            return redirect()->route('school.online');
        }
        abort_unless(OnlineAccess::ready() && OnlineAccess::canSupervise($u), 403, 'مدیرِ مدرسه شما را ناظمِ کلاس‌های آنلاین تعیین نکرده است.');
        $sid = $u->school_id;
        $rooms = OnlineRoom::with(['classroom:id,name', 'teacher:id,name'])->withCount(['participants as present_count' => fn ($q) => $q->where('role', 'student')])
            ->where('school_id', $sid)->orderByRaw("case status when 'live' then 0 when 'scheduled' then 1 else 2 end")->latest('id')->limit(80)->get();
        $rooms->each->closeIfAbandoned();

        return Inertia::render('Online/Supervise', [
            'school' => \App\Models\School::find($sid)?->name,
            'rooms' => $rooms->map(fn ($r) => $this->card($r, true))->values(),
            'recordings' => $this->recordings(OnlineRoom::where('school_id', $sid), 40),
        ]);
    }

    /** اجازه‌ی برگزاری یا نظارت برای یک معلم (یا همه). */
    public function grant(Request $request): RedirectResponse
    {
        $u = $request->user();
        abort_unless(OnlineAccess::ready(), 503);
        $data = $request->validate(['user' => 'nullable|integer', 'all' => 'nullable|boolean', 'field' => 'required|in:can_host,supervisor', 'value' => 'required|boolean']);
        $ids = $request->boolean('all')
            ? User::role(Roles::TEACHER)->where('school_id', $u->school_id)->pluck('id')->all()
            : [(int) $data['user']];
        $valid = User::role(Roles::TEACHER)->where('school_id', $u->school_id)->whereIn('id', $ids)->pluck('id');
        abort_if($valid->isEmpty(), 404);
        foreach ($valid as $id) {
            $row = DB::table('online_hosts')->where('user_id', $id)->first();
            $vals = [$data['field'] => (bool) $data['value'], 'school_id' => $u->school_id, 'granted_by' => $u->id, 'updated_at' => now()];
            $row ? DB::table('online_hosts')->where('user_id', $id)->update($vals)
                : DB::table('online_hosts')->insert($vals + ['user_id' => $id, 'created_at' => now()]);
        }

        return back()->with('success', $data['value'] ? '✅ دسترسی داده شد.' : 'دسترسی برداشته شد.');
    }

    /** پایانِ اجباریِ یک کلاس توسطِ مدیر. */
    public function endRoom(Request $request, OnlineRoom $room): RedirectResponse
    {
        abort_unless(OnlineAccess::canSupervise($request->user(), $room), 403);
        $this->svc->end($room, $request->user());

        return back()->with('success', '⏹ کلاسِ «' . $room->title . '» تمام شد.');
    }

    // ── ادمینِ کل ────────────────────────────────────────────────────────

    public function admin(): Response
    {
        $ready = OnlineAccess::ready();
        $access = $ready ? DB::table('online_schools')->get()->keyBy('school_id') : collect();
        $usage = $ready ? OnlineRoom::where('started_at', '>=', now()->subDays(30))->get()->groupBy('school_id') : collect();

        return Inertia::render('Admin/OnlineClasses', [
            'ready' => $ready,
            'settings' => [
                'media' => Setting::get('online_media', 'builtin'),
                'livekit_url' => (string) Setting::get('online_livekit_url', ''),
                'livekit_key' => (string) Setting::get('online_livekit_key', ''),
                'livekit_secret_set' => OnlineAccess::secret() !== '',
                'ice' => (string) Setting::get('online_ice', "stun:stun.l.google.com:19302\nstun:stun.cloudflare.com:3478"),
                'budget' => OnlineAccess::budgetKbps(),
                'default_max' => OnlineAccess::defaultMax(),
                'recording' => OnlineAccess::recordingAllowed(),
                'convert_url' => (string) Setting::get('online_convert_url', ''),
                'convert_token_set' => (string) Setting::get('online_convert_token', '') !== '',
            ],
            'active' => OnlineAccess::media(),
            'schools' => School::orderBy('name')->get(['id', 'name', 'city'])->map(fn ($s) => [
                'id' => $s->id, 'name' => $s->name, 'city' => $s->city,
                'enabled' => (bool) ($access[$s->id]->enabled ?? false), 'max' => (int) ($access[$s->id]->max_participants ?? OnlineAccess::defaultMax()),
                'classes' => isset($usage[$s->id]) ? $usage[$s->id]->count() : 0,
                'live' => isset($usage[$s->id]) ? $usage[$s->id]->where('status', 'live')->count() : 0,
            ]),
        ]);
    }

    public function adminSettings(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'media' => 'required|in:builtin,livekit', 'livekit_url' => 'nullable|string|max:255', 'livekit_key' => 'nullable|string|max:255',
            'livekit_secret' => 'nullable|string|max:255', 'ice' => 'nullable|string|max:3000', 'budget' => 'required|integer|min:500|max:20000',
            'default_max' => 'required|integer|min:2|max:1000', 'recording' => 'required|boolean',
            'convert_url' => ['nullable', 'string', 'max:255', 'regex:#^https?://#i'], 'convert_token' => 'nullable|string|max:255',
        ], ['convert_url.regex' => 'نشانیِ سرویسِ تبدیل باید با http:// یا https:// شروع شود (مثلاً https://live.starmah.ir/convert-api).']);
        if ($d['media'] === 'livekit') {
            $url = trim((string) ($d['livekit_url'] ?? ''));
            if (! preg_match('#^wss?://#i', $url)) {
                return back()->withErrors(['livekit_url' => 'نشانیِ سرور باید با wss:// شروع شود (مثلاً wss://live.example.ir).']);
            }
        }
        Setting::put('online_media', $d['media']);
        Setting::put('online_livekit_url', trim((string) ($d['livekit_url'] ?? '')));
        Setting::put('online_livekit_key', trim((string) ($d['livekit_key'] ?? '')));
        if (! empty($d['livekit_secret'])) {
            Setting::put('online_livekit_secret', Crypt::encryptString(trim($d['livekit_secret'])));
        }
        Setting::put('online_ice', trim((string) ($d['ice'] ?? '')));
        Setting::put('online_budget_kbps', (int) $d['budget']);
        Setting::put('online_default_max', (int) $d['default_max']);
        Setting::put('online_recording', $d['recording'] ? 1 : 0);
        Setting::put('online_convert_url', rtrim(trim((string) ($d['convert_url'] ?? '')), '/'));
        if (! empty($d['convert_token'])) {
            Setting::put('online_convert_token', Crypt::encryptString(trim($d['convert_token'])));
        }
        if ($d['media'] === 'livekit' && ! OnlineAccess::livekit()) {
            return back()->with('error', 'تنظیمات ذخیره شد، ولی LiveKit کامل نیست (نشانی، کلید و رمز لازم است)؛ فعلاً حالتِ داخلی به کار می‌رود.');
        }

        return back()->with('success', '✅ تنظیماتِ کلاسِ آنلاین ذخیره شد.');
    }

    /** آزمایشِ سرویسِ تبدیلِ پاورپوینت از روی همین هاست. */
    public function adminTestConvert(): \Illuminate\Http\JsonResponse
    {
        $c = OnlineAccess::converter();
        if (! $c) {
            return response()->json(['ok' => false, 'message' => 'نشانی و رمزِ سرویسِ تبدیل وارد نشده است.']);
        }
        try {
            $res = \Illuminate\Support\Facades\Http::timeout(12)->withHeaders(['X-Token' => $c['token']])->get($c['url'] . '/health');
            if ($res->status() === 401) {
                return response()->json(['ok' => false, 'message' => 'سرویس جواب داد ولی رمز (Token) اشتباه است.']);
            }

            return response()->json($res->successful()
                ? ['ok' => true, 'message' => 'سرویسِ تبدیل در دسترس است و کار می‌کند.']
                : ['ok' => false, 'message' => 'سرویس جواب داد ولی خطا: ' . $res->status()]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'هاست به سرویسِ تبدیل وصل نشد (' . mb_substr($e->getMessage(), 0, 120) . ').']);
        }
    }

    public function adminSchool(Request $request, School $school): RedirectResponse
    {
        $d = $request->validate(['enabled' => 'required|boolean', 'max' => 'required|integer|min:2|max:1000']);
        $vals = ['enabled' => (bool) $d['enabled'], 'max_participants' => (int) $d['max'], 'granted_by' => $request->user()->id, 'updated_at' => now()];
        DB::table('online_schools')->where('school_id', $school->id)->exists()
            ? DB::table('online_schools')->where('school_id', $school->id)->update($vals)
            : DB::table('online_schools')->insert($vals + ['school_id' => $school->id, 'created_at' => now()]);
        OnlineAccess::forget();

        return back()->with('success', ($d['enabled'] ? '🎥 کلاسِ آنلاین برای «' : 'کلاسِ آنلاین برای «') . $school->name . ($d['enabled'] ? '» روشن شد.' : '» خاموش شد.'));
    }
}
