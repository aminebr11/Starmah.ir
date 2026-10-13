<?php

namespace App\Http\Controllers\Online;

use App\Http\Controllers\Controller;
use App\Models\OnlineBoardPage;
use App\Models\OnlineMessage;
use App\Models\OnlineParticipant;
use App\Models\OnlinePoll;
use App\Models\OnlineRoom;
use App\Services\OnlineClassService;
use App\Support\Jalali;
use App\Support\OnlineAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * اتاقِ «کلاسِ آنلاین» — یک صفحه برای معلم (host)، دانش‌آموز (student) و ناظر (observer).
 * نقش را سرور تعیین می‌کند (OnlineAccess::roleIn)، نه مرورگر.
 */
class RoomController extends Controller
{
    public function __construct(private OnlineClassService $svc) {}

    /** @return array{0:string,1:?OnlineParticipant} */
    private function ctx(Request $request, OnlineRoom $room, bool $needJoin = true): array
    {
        abort_unless(OnlineAccess::ready(), 503, 'جدول‌های کلاسِ آنلاین هنوز ساخته نشده‌اند.');
        $role = OnlineAccess::roleIn($request->user(), $room);
        abort_unless($role, 403, 'این کلاس برای حسابِ شما نیست.');
        $p = OnlineParticipant::where('room_id', $room->id)->where('user_id', $request->user()->id)->first();
        if ($needJoin) {
            abort_unless($p && ! $p->kicked, 409, 'اول وارد کلاس شوید.');
        }

        return [$role, $p];
    }

    private function moderator(string $role): bool
    {
        return in_array($role, ['host', 'observer'], true);
    }

    public function show(Request $request, OnlineRoom $room): Response
    {
        [$role] = $this->ctx($request, $room, false);
        $room->closeIfAbandoned();
        $user = $request->user();

        return Inertia::render('Online/Room', [
            'room' => ['id' => $room->id, 'title' => $room->title, 'status' => $room->status, 'media' => $room->live() ? $room->media : OnlineAccess::media(),
                'teacher' => $room->teacher?->name, 'teacher_id' => (int) $room->teacher_id, 'classroom' => $room->classroom?->name,
                'starts_at' => $room->starts_at ? Jalali::format($room->starts_at) . ' ساعت ' . Jalali::fa($room->starts_at->format('H:i')) : null,
                'starts_ms' => $room->starts_at?->getTimestampMs(), 'duration' => $room->duration_min],
            'me' => ['id' => $user->id, 'name' => $user->name, 'role' => $role],
            'config' => ['ice' => OnlineAccess::iceServers(), 'budget' => OnlineAccess::budgetKbps(), 'recording' => OnlineAccess::recordingAllowed(),
                'max' => OnlineAccess::maxParticipants($room->school_id), 'livekit' => (bool) OnlineAccess::livekit()],
            'back' => match ($role) {
                'host' => route('teacher.online'),
                'student' => route('online.list'),
                default => $user->hasRole(\App\Support\Roles::SUPER_ADMIN) ? route('admin.online') : route('school.online'),
            },
        ]);
    }

    /** ورود به کلاس. */
    public function join(Request $request, OnlineRoom $room): JsonResponse
    {
        [$role] = $this->ctx($request, $room, false);
        if ($role === 'host' && ! $room->live()) {
            abort_unless(OnlineAccess::canHost($request->user()), 403, 'مدیرِ مدرسه هنوز اجازه‌ی برگزاریِ کلاسِ آنلاین را به شما نداده است.');
            $this->svc->start($room);
        }
        if (! $room->live()) {
            return response()->json(['waiting' => true, 'status' => $room->status, 'starts_ms' => $room->starts_at?->getTimestampMs()]);
        }
        [$p, $error] = $this->svc->join($room, $request->user(), $role);
        if ($error) {
            return response()->json(['error' => $error], 423);
        }

        return response()->json($this->svc->sync($room->fresh(), $p, []));
    }

    public function sync(Request $request, OnlineRoom $room): JsonResponse
    {
        [$role, $p] = $this->ctx($request, $room, false);
        if (! $room->live()) {
            return response()->json(['status' => $room->status, 'v' => (int) $room->version, 'signals' => [],
                'starts_ms' => $room->starts_at?->getTimestampMs()]);
        }
        if (! $p) {
            return response()->json(['rejoin' => true, 'status' => $room->status]);
        }
        if ($p->kicked) {
            return response()->json(['kicked' => true, 'status' => 'ended']);
        }
        $in = $request->validate(['v' => 'nullable|integer', 'msg' => 'nullable|integer', 'ms' => 'nullable|string|max:40']);

        return response()->json($this->svc->sync($room, $p, $in));
    }

    public function leave(Request $request, OnlineRoom $room): JsonResponse
    {
        [, $p] = $this->ctx($request, $room, false);
        if ($p && ! $p->left_at) {
            $this->svc->leave($room, $p);
        }

        return response()->json(['ok' => true]);
    }

    /** پیامِ اتصالِ WebRTC (offer/answer/hello/bye) برای یک نفر. */
    public function signal(Request $request, OnlineRoom $room): JsonResponse
    {
        [$role, $p] = $this->ctx($request, $room);
        $data = $request->validate(['to' => 'required|integer', 'kind' => 'required|in:offer,answer,hello,bye,ice',
            'payload' => 'nullable|string|max:60000']);
        // ستاره‌ای: معلم با همه، بقیه فقط با معلم
        if ($role !== 'host') {
            abort_unless((int) $data['to'] === (int) $room->teacher_id, 403);
        } else {
            abort_unless(OnlineParticipant::where('room_id', $room->id)->where('user_id', $data['to'])->exists(), 404);
        }
        $this->svc->signal($room, (int) $p->user_id, (int) $data['to'], $data['kind'], $data['payload'] ?? null);

        return response()->json(['ok' => true]);
    }

    // ── گفت‌وگو و دست ───────────────────────────────────────────────────

    public function chat(Request $request, OnlineRoom $room): JsonResponse
    {
        [$role, $p] = $this->ctx($request, $room);
        abort_unless($this->moderator($role) || $room->opt('chat', true), 423, 'معلم گفت‌وگو را فعلاً بسته است.');
        $data = $request->validate(['body' => 'required|string|max:500']);
        $body = trim(preg_replace('/\s+/u', ' ', $data['body']));
        abort_if($body === '', 422);
        // جلوگیری از رگبارِ پیام
        $recent = OnlineMessage::where('room_id', $room->id)->where('user_id', $p->user_id)->where('created_at', '>=', now()->subSeconds(10))->count();
        abort_if(! $this->moderator($role) && $recent >= 4, 429, 'کمی آرام‌تر 🙂');
        $m = OnlineMessage::create(['room_id' => $room->id, 'user_id' => $p->user_id, 'body' => $body]);
        $room->bump();

        return response()->json(['id' => $m->id]);
    }

    public function hideMessage(Request $request, OnlineRoom $room, OnlineMessage $message): JsonResponse
    {
        [$role, $p] = $this->ctx($request, $room);
        abort_unless((int) $message->room_id === (int) $room->id, 404);
        abort_unless($this->moderator($role) || (int) $message->user_id === (int) $p->user_id, 403);
        $message->forceFill(['hidden' => true])->save();
        $room->bump();

        return response()->json(['ok' => true]);
    }

    public function hand(Request $request, OnlineRoom $room): JsonResponse
    {
        [, $p] = $this->ctx($request, $room);
        $up = $request->boolean('up');
        $p->forceFill(['hand_at' => $up ? now() : null])->save();
        $room->bump();

        return response()->json(['hand' => $up]);
    }

    /**
     * فرمان‌های معلم/ناظر: دسترسی‌ها، بی‌صدا کردنِ همه، قفل، گفت‌وگو، تایمر، ستاره، صحنه، پایان.
     */
    public function control(Request $request, OnlineRoom $room): JsonResponse
    {
        [$role, $me] = $this->ctx($request, $room);
        abort_unless($this->moderator($role), 403);
        $data = $request->validate([
            'action' => 'required|in:perm,mute_all,lower_hands,chat,lock,timer,star,stage,page,kick,spotlight,announce,recording,open_mics,end',
            'user' => 'nullable|integer', 'perm' => 'nullable|in:can_speak,can_video,can_draw', 'value' => 'nullable',
        ]);
        $target = isset($data['user']) ? OnlineParticipant::where('room_id', $room->id)->where('user_id', $data['user'])->first() : null;
        $value = $data['value'] ?? null;
        $out = ['ok' => true];

        switch ($data['action']) {
            case 'perm':
                abort_unless($target && $target->role === 'student' && isset($data['perm']), 422);
                $on = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                $target->forceFill([$data['perm'] => $on] + ($data['perm'] === 'can_speak' && $on ? ['hand_at' => null] : []))->save();
                $room->bump();
                break;
            case 'mute_all':
                OnlineParticipant::where('room_id', $room->id)->where('role', 'student')->update(['can_speak' => false, 'can_video' => false]);
                $room->setOpts(['open_mics' => false]);
                break;
            case 'open_mics':
                $on = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                OnlineParticipant::where('room_id', $room->id)->where('role', 'student')->update(['can_speak' => $on]);
                $room->setOpts(['open_mics' => $on]);
                break;
            case 'lower_hands':
                OnlineParticipant::where('room_id', $room->id)->update(['hand_at' => null]);
                $room->bump();
                break;
            case 'chat':
                $room->setOpts(['chat' => filter_var($value, FILTER_VALIDATE_BOOLEAN)]);
                break;
            case 'lock':
                $room->setOpts(['locked' => filter_var($value, FILTER_VALIDATE_BOOLEAN)]);
                break;
            case 'timer':
                $sec = (int) $value;
                $room->setOpts(['timer_end' => $sec > 0 ? now()->addSeconds(min($sec, 3600))->getTimestampMs() : null]);
                break;
            case 'star':
                abort_unless($target && $target->role === 'student', 422);
                $out['xp'] = $this->svc->star($room, $target, $request->user());
                break;
            case 'stage':
                abort_unless(in_array($value, ['board', 'screen', 'cam'], true), 422);
                $room->setOpts(['stage' => $value]);
                break;
            case 'page':
                $room->setOpts(['page' => max(0, min(199, (int) $value))]);
                break;
            case 'spotlight':
                $room->setOpts(['spotlight' => $target ? (int) $target->user_id : null]);
                break;
            case 'announce':
                $room->setOpts(['announce' => $value ? mb_substr(trim((string) $value), 0, 140) : null]);
                break;
            case 'recording':
                $room->setOpts(['recording' => filter_var($value, FILTER_VALIDATE_BOOLEAN)]);
                break;
            case 'kick':
                abort_unless($target && $target->role === 'student', 422);
                $target->forceFill(['kicked' => true, 'left_at' => now(), 'hand_at' => null])->save();
                $room->bump();
                break;
            case 'end':
                $this->svc->end($room, $request->user());
                break;
        }

        return response()->json($out);
    }

    // ── تخته ─────────────────────────────────────────────────────────────

    public function boardGet(Request $request, OnlineRoom $room): JsonResponse
    {
        $this->ctx($request, $room, false);
        $page = OnlineBoardPage::where('room_id', $room->id)->where('page', (int) $request->query('page', 0))->first();

        return response()->json(['page' => (int) $request->query('page', 0), 'bg' => $page?->bg ?? 'blank',
            'ops' => $page?->ops ? json_decode($page->ops, true) : [], 'version' => (int) ($page?->version ?? 0)]);
    }

    /** ذخیره‌ی یک صفحه‌ی تخته — فقط معلم (منبعِ اصلیِ تخته). */
    public function boardSave(Request $request, OnlineRoom $room): JsonResponse
    {
        [$role] = $this->ctx($request, $room);
        abort_unless($role === 'host', 403);
        $data = $request->validate(['page' => 'required|integer|min:0|max:199', 'bg' => 'nullable|string|max:255',
            'ops' => 'nullable|string|max:3000000', 'remove' => 'nullable|boolean']);
        if ($request->boolean('remove')) {
            OnlineBoardPage::where('room_id', $room->id)->where('page', $data['page'])->delete();
            OnlineBoardPage::where('room_id', $room->id)->where('page', '>', $data['page'])->orderBy('page')->get()
                ->each(fn ($pg) => $pg->forceFill(['page' => $pg->page - 1])->save());
            $room->bump();

            return response()->json(['ok' => true]);
        }
        $page = OnlineBoardPage::firstOrNew(['room_id' => $room->id, 'page' => $data['page']]);
        $page->bg = $data['bg'] ?? ($page->bg ?: 'blank');
        if (array_key_exists('ops', $data) && $data['ops'] !== null) {
            $page->ops = $data['ops'];
        }
        $page->version = (int) $page->version + 1;
        $page->save();
        $room->bump();

        return response()->json(['version' => $page->version]);
    }

    /** بارگذاریِ تصویر برای تخته (اسلاید/صفحه‌ی PDF). */
    public function assetUpload(Request $request, OnlineRoom $room): JsonResponse
    {
        [$role] = $this->ctx($request, $room);
        abort_unless($role === 'host', 403);
        $request->validate(['file' => 'required|file|max:10240|mimes:jpg,jpeg,png,webp,gif']);
        $file = $request->file('file');
        $name = Str::random(24) . '.' . strtolower($file->getClientOriginalExtension() ?: 'jpg');
        Storage::disk('local')->putFileAs("online/{$room->id}", $file, $name);

        return response()->json(['url' => route('online.asset', [$room->id, $name], false)]);
    }

    public function asset(Request $request, OnlineRoom $room, string $name): BinaryFileResponse
    {
        $this->ctx($request, $room, false);
        abort_unless(preg_match('/^[A-Za-z0-9]{24}\.(jpe?g|png|webp|gif)$/', $name), 404);
        $path = "online/{$room->id}/{$name}";
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'private, max-age=604800, immutable']);
    }

    // ── نظرسنجی ──────────────────────────────────────────────────────────

    public function pollCreate(Request $request, OnlineRoom $room): JsonResponse
    {
        [$role] = $this->ctx($request, $room);
        abort_unless($this->moderator($role), 403);
        $data = $request->validate(['question' => 'required|string|max:300', 'options' => 'required|array|min:2|max:6',
            'options.*' => 'required|string|max:120', 'correct' => 'nullable|integer|min:0|max:5', 'xp' => 'nullable|integer|min:0|max:50']);
        OnlinePoll::where('room_id', $room->id)->where('status', 'open')->get()->each(fn ($p) => $this->svc->closePoll($room, $p, $request->user()));
        $correct = isset($data['correct']) && $data['correct'] < count($data['options']) ? (int) $data['correct'] : null;
        $poll = OnlinePoll::create(['room_id' => $room->id, 'question' => trim($data['question']), 'options' => array_values(array_map('trim', $data['options'])),
            'correct' => $correct, 'xp' => $correct === null ? 0 : (int) ($data['xp'] ?? 5), 'status' => 'open']);
        $room->bump();

        return response()->json(['id' => $poll->id]);
    }

    public function pollAnswer(Request $request, OnlineRoom $room, OnlinePoll $poll): JsonResponse
    {
        [, $p] = $this->ctx($request, $room);
        abort_unless((int) $poll->room_id === (int) $room->id && $poll->status === 'open', 423, 'این نظرسنجی بسته شده است.');
        $choice = (int) $request->validate(['choice' => 'required|integer|min:0|max:5'])['choice'];
        abort_unless($choice < count($poll->options ?? []), 422);
        DB::table('online_poll_answers')->insertOrIgnore(['poll_id' => $poll->id, 'user_id' => $p->user_id, 'choice' => $choice,
            'created_at' => now(), 'updated_at' => now()]);
        $room->bump();

        return response()->json(['mine' => $poll->choiceOf((int) $p->user_id)]);
    }

    public function pollClose(Request $request, OnlineRoom $room, OnlinePoll $poll): JsonResponse
    {
        [$role] = $this->ctx($request, $room);
        abort_unless($this->moderator($role) && (int) $poll->room_id === (int) $room->id, 403);
        $this->svc->closePoll($room, $poll, $request->user());

        return response()->json(['ok' => true]);
    }

    // ── ضبط ──────────────────────────────────────────────────────────────

    /** یک تکه از فایلِ ضبط (هر ۱۰ ثانیه از مرورگرِ معلم). */
    public function recordChunk(Request $request, OnlineRoom $room): JsonResponse
    {
        [$role] = $this->ctx($request, $room);
        abort_unless($role === 'host' && OnlineAccess::recordingAllowed(), 403);
        $request->validate(['seq' => 'required|integer|min:0', 'chunk' => 'required|file|max:51200']);
        $dir = "online/{$room->id}";
        Storage::disk('local')->makeDirectory($dir);
        $path = Storage::disk('local')->path("$dir/recording.webm");
        if ((int) $request->input('seq') === 0) {
            @unlink($path);
        }
        clearstatcache(true, $path);
        abort_if(is_file($path) && filesize($path) > 2 * 1024 ** 3, 413, 'فایلِ ضبط بیش از اندازه بزرگ شد.');
        file_put_contents($path, file_get_contents($request->file('chunk')->getRealPath()), FILE_APPEND | LOCK_EX);
        clearstatcache(true, $path);
        $room->forceFill(['recording_path' => "$dir/recording.webm", 'recording_size' => filesize($path)])->save();

        return response()->json(['size' => filesize($path)]);
    }

    /** پایانِ ضبط: نوشتنِ مدتِ فیلم در فایل تا نوارِ پخش کار کند. */
    public function recordFinish(Request $request, OnlineRoom $room): JsonResponse
    {
        [$role] = $this->ctx($request, $room);
        abort_unless($role === 'host' && $room->recording_path, 403);
        $ms = (float) $request->validate(['duration_ms' => 'required|numeric|min:1|max:86400000'])['duration_ms'];
        $ok = \App\Support\WebmDuration::fix(Storage::disk('local')->path($room->recording_path), $ms);

        return response()->json(['ok' => $ok]);
    }

    public function recording(Request $request, OnlineRoom $room): BinaryFileResponse
    {
        [$role] = $this->ctx($request, $room, false);
        abort_unless($room->recording_path && ($role !== 'student' || $room->recording_shared), 404);
        abort_unless(Storage::disk('local')->exists($room->recording_path), 404);

        return response()->file(Storage::disk('local')->path($room->recording_path), [
            'Content-Type' => 'video/webm', 'Cache-Control' => 'private, max-age=3600',
            'Content-Disposition' => 'inline; filename="class-' . $room->id . '.webm"',
        ]);
    }

    // ── LiveKit ──────────────────────────────────────────────────────────

    public function token(Request $request, OnlineRoom $room): JsonResponse
    {
        [$role] = $this->ctx($request, $room);
        $t = $this->svc->livekitToken($room, $request->user(), $role);
        abort_unless($t, 404, 'سرورِ رسانه تنظیم نشده است.');

        return response()->json($t);
    }

    // ── گزارش ────────────────────────────────────────────────────────────

    /** گزارشِ حضورِ یک کلاس (معلم، مدیر، ناظر). */
    public function report(Request $request, OnlineRoom $room): JsonResponse
    {
        [$role] = $this->ctx($request, $room, false);
        abort_unless($this->moderator($role), 403);
        $audience = $room->audienceIds();
        $rows = OnlineParticipant::with('user:id,name')->where('room_id', $room->id)->where('role', 'student')->get()->keyBy('user_id');
        $names = \App\Models\User::whereIn('id', $audience)->pluck('name', 'id');
        $list = collect($audience)->map(fn ($id) => [
            'id' => $id, 'name' => $names[$id] ?? ($rows[$id]->user->name ?? '—'),
            'present' => isset($rows[$id]), 'minutes' => isset($rows[$id]) ? (int) round($rows[$id]->seconds / 60) : 0,
            'joins' => (int) ($rows[$id]->joins ?? 0), 'stars' => (int) ($rows[$id]->stars ?? 0), 'xp' => (int) ($rows[$id]->xp ?? 0),
            'first' => isset($rows[$id]) && $rows[$id]->joined_at ? Jalali::fa($rows[$id]->joined_at->format('H:i')) : null,
        ])->sortBy([['present', 'desc'], ['minutes', 'desc']])->values();

        return response()->json([
            'title' => $room->title, 'status' => $room->status,
            'length' => $room->started_at ? (int) round(($room->ended_at ?? now())->diffInSeconds($room->started_at, true) / 60) : 0,
            'present' => $list->where('present', true)->count(), 'total' => $list->count(), 'rows' => $list,
            'messages' => OnlineMessage::where('room_id', $room->id)->count(),
        ]);
    }
}
