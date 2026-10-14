<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\OnlineBoardPage;
use App\Models\OnlineMessage;
use App\Models\OnlineParticipant;
use App\Models\OnlinePoll;
use App\Models\OnlineRoom;
use App\Models\User;
use App\Support\OnlineAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * منطقِ «کلاسِ آنلاین»: ورود، همگام‌سازیِ سبک، پیام‌های اتصال، پایانِ کلاس با حضور و امتیاز.
 *
 * همگام‌سازی عمداً سبک است تا روی هاستِ اشتراکی با ۴۰+ نفر هم روان بماند:
 * در حالتِ عادی فقط سه پرس‌وجو (کلاس + حضورِ من + پیام‌های اتصالِ من)؛
 * داده‌ی کامل (فهرستِ نفرات، گفت‌وگو، نظرسنجی، تخته) فقط وقتی «version» عوض شده باشد.
 */
class OnlineClassService
{
    /** امتیازِ پیش‌فرض: حضور در کلاس، هر ستاره‌ی معلم. */
    public const ATTEND_XP = 10;
    public const STAR_XP = 5;

    public function __construct(private GamificationService $game) {}

    // ── ورود و همگام‌سازی ────────────────────────────────────────────────

    /** ورود یا ورودِ دوباره. خروجی: [participant, error|null] */
    public function join(OnlineRoom $room, User $user, string $role): array
    {
        $p = OnlineParticipant::firstOrNew(['room_id' => $room->id, 'user_id' => $user->id]);
        if ($p->kicked) {
            return [$p, 'معلم تو را از این کلاس بیرون گذاشته است.'];
        }
        if ($role === 'student') {
            if (! $p->exists && $room->opt('locked')) {
                return [$p, 'در کلاس بسته شده است؛ از معلم بخواه بازش کند.'];
            }
            $online = OnlineParticipant::where('room_id', $room->id)->where('role', 'student')->where('user_id', '!=', $user->id)
                ->whereNull('left_at')->where('last_seen_at', '>=', now()->subSeconds(OnlineParticipant::ONLINE_SEC))->count();
            if ($online >= OnlineAccess::maxParticipants($room->school_id)) {
                return [$p, 'ظرفیتِ کلاس پر است. کمی بعد دوباره امتحان کن.'];
            }
        }

        $fresh = ! $p->exists || $p->left_at || ! $p->last_seen_at || $p->last_seen_at->lt(now()->subSeconds(OnlineParticipant::ONLINE_SEC));
        $p->role = $role;
        $p->joined_at ??= now();
        $p->last_seen_at = now();
        $p->left_at = null;
        if ($fresh) {
            $p->joins = (int) $p->joins + 1;
        }
        if ($role !== 'student') {
            $p->can_speak = $p->can_video = $p->can_draw = true;
        } elseif (! $p->exists) {
            $p->can_speak = (bool) $room->opt('open_mics', false);
        }
        $p->save();

        if ($role === 'host') {
            $room->forceFill(['host_seen_at' => now()])->save();
        }
        if ($fresh) {
            $room->bump();
        }

        return [$p, null];
    }

    /** خروج (دکمه‌ی خروج یا بستنِ صفحه). */
    public function leave(OnlineRoom $room, OnlineParticipant $p): void
    {
        $this->touch($p, true);
        $p->forceFill(['left_at' => now(), 'hand_at' => null])->save();
        DB::table('online_signals')->where('room_id', $room->id)->where(fn ($q) => $q->where('to_id', $p->user_id)->orWhere('from_id', $p->user_id))->delete();
        $room->bump();
    }

    /** «هنوز این‌جاست» + جمع‌کردنِ زمانِ حضور. هر ۸ ثانیه یک‌بار در پایگاه‌داده نوشته می‌شود. */
    public function touch(OnlineParticipant $p, bool $force = false): void
    {
        $now = now();
        $last = $p->last_seen_at;
        if (! $force && $last && $last->gt($now->copy()->subSeconds(8))) {
            return;
        }
        $add = ($last && ! $p->left_at) ? min(30, max(0, $now->diffInSeconds($last, true))) : 0;
        $wasAway = ! $last || $last->lt($now->copy()->subSeconds(OnlineParticipant::ONLINE_SEC)) || $p->left_at;
        $p->forceFill(['last_seen_at' => $now, 'seconds' => (int) $p->seconds + (int) $add, 'left_at' => null])->save();
        if ($wasAway) {
            OnlineRoom::whereKey($p->room_id)->increment('version');
        }
    }

    /**
     * یک دورِ همگام‌سازی.
     *
     * @param  array{v?:int,msg?:int,ms?:string}  $in
     */
    public function sync(OnlineRoom $room, OnlineParticipant $p, array $in): array
    {
        $this->touch($p);
        if ($p->role === 'host') {
            if (! $room->host_seen_at || $room->host_seen_at->lt(now()->subSeconds(15))) {
                OnlineRoom::whereKey($room->id)->update(['host_seen_at' => now()]);
            }
        } else {
            $room->closeIfAbandoned();
        }
        if (isset($in['ms']) && $in['ms'] !== $p->media_state) {
            $p->forceFill(['media_state' => mb_substr((string) $in['ms'], 0, 40)])->save();
            $room->bump();
        }

        // پیام‌های اتصالِ WebRTC برای من — تحویل و پاک
        $sig = DB::table('online_signals')->where('room_id', $room->id)->where('to_id', $p->user_id)->orderBy('id')->limit(60)->get();
        if ($sig->isNotEmpty()) {
            DB::table('online_signals')->whereIn('id', $sig->pluck('id'))->delete();
        }

        $out = [
            'v' => (int) $room->version,
            'status' => $room->status,
            'signals' => $sig->map(fn ($s) => ['from' => (int) $s->from_id, 'kind' => $s->kind, 'payload' => $s->payload])->values(),
            'me' => $this->me($p),
            'now' => now()->getTimestampMs(),
        ];
        if ((int) ($in['v'] ?? 0) !== (int) $room->version) {
            $out['state'] = $this->state($room, $p, (int) ($in['msg'] ?? 0));
        }

        return $out;
    }

    public function me(OnlineParticipant $p): array
    {
        return ['id' => (int) $p->user_id, 'role' => $p->role, 'can_speak' => (bool) $p->can_speak, 'can_video' => (bool) $p->can_video,
            'can_draw' => (bool) $p->can_draw, 'hand' => (bool) $p->hand_at, 'kicked' => (bool) $p->kicked, 'stars' => (int) $p->stars];
    }

    /** وضعیتِ کاملِ کلاس برای یک نفر. */
    public function state(OnlineRoom $room, OnlineParticipant $me, int $sinceMsg = 0): array
    {
        $people = OnlineParticipant::with('user:id,name')->where('room_id', $room->id)->where('kicked', false)->get();
        $host = in_array($me->role, ['host', 'observer'], true);

        $msgs = OnlineMessage::with('user:id,name')->where('room_id', $room->id)->where('id', '>', $sinceMsg)->where('hidden', false)
            ->orderByDesc('id')->limit(80)->get()->reverse()->values();
        $hidden = OnlineMessage::where('room_id', $room->id)->where('hidden', true)->orderByDesc('id')->limit(100)->pluck('id');

        // سؤالی که معلم «پاک» کرده (و قبلی‌هایش) دیگر نشان داده نمی‌شود
        $poll = OnlinePoll::where('room_id', $room->id)->where('id', '>', (int) $room->opt('poll_after', 0))->latest('id')->first();
        $pages = OnlineBoardPage::where('room_id', $room->id)->orderBy('page')->get(['page', 'bg', 'version']);

        return [
            'title' => $room->title,
            'status' => $room->status,
            'media' => $room->media,
            'started_at' => $room->started_at?->getTimestampMs(),
            'settings' => [
                'chat' => (bool) $room->opt('chat', true),
                'locked' => (bool) $room->opt('locked', false),
                'page' => (int) $room->opt('page', 0),
                'stage' => (string) $room->opt('stage', 'board'),
                'kid_view' => (string) $room->opt('kid_view', 'normal'),
                'timer_end' => $room->opt('timer_end'),
                'spotlight' => $room->opt('spotlight'),
                'announce' => $room->opt('announce'),
                'recording' => (bool) $room->opt('recording', false),
                'book' => $room->opt('book'),
                'reactions' => (bool) $room->opt('reactions', true),
                'playing' => $room->opt('playing'),
                'view' => $room->opt('view'),
            ],
            'people' => $people->map(fn ($x) => [
                'id' => (int) $x->user_id, 'name' => $x->user?->name ?? '—', 'role' => $x->role, 'online' => $x->online(),
                'hand' => $x->hand_at?->getTimestampMs(), 'can_speak' => (bool) $x->can_speak, 'can_video' => (bool) $x->can_video,
                'can_draw' => (bool) $x->can_draw, 'stars' => (int) $x->stars, 'ms' => $x->media_state,
            ])->sortBy([['online', 'desc'], ['role', 'asc'], ['name', 'asc']])->values(),
            'messages' => $msgs->map(fn ($m) => ['id' => $m->id, 'uid' => (int) $m->user_id, 'name' => $m->user?->name ?? '—',
                'kind' => $m->kind, 'body' => $m->body, 'at' => $m->created_at?->getTimestampMs()]),
            'hidden' => $hidden,
            'poll' => $poll ? $this->pollData($poll, $me, $host) : null,
            'pages' => $pages->map(fn ($pg) => ['page' => (int) $pg->page, 'bg' => $pg->bg, 'version' => (int) $pg->version])
                ->concat($this->bookPages($room))->values(),
        ];
    }

    /** کتابِ باز: صفحه‌ی فعلی و دو صفحه‌ی بعد (نسخه برای همگام‌سازی، تصویر برای پیش‌بارگذاری). */
    private function bookPages(OnlineRoom $room): array
    {
        $cur = (int) $room->opt('page', 0);
        if (! \App\Support\OnlineBoard::isBook($cur) || ! $room->opt('book') || ! \App\Models\OnlineBook::ready()) {
            return [];
        }
        $n = \App\Support\OnlineBoard::bookPageNo($cur);
        $rows = \App\Models\OnlineBookPage::where('book_id', (int) $room->opt('book')['id'])->whereBetween('page', [$n, $n + 2])->get(['book_id', 'page', 'bg', 'version'])->keyBy('page');

        return collect(range($n, $n + 2))->map(function ($i) use ($rows, $room) {
            $idx = \App\Support\OnlineBoard::boardIndex($i);
            $row = $rows[$i] ?? null;

            return ['page' => $idx, 'bg' => \App\Support\OnlineBoard::bg($room, $idx, $row), 'version' => (int) ($row->version ?? 0)];
        })->all();
    }

    public function pollData(OnlinePoll $poll, OnlineParticipant $me, bool $host): array
    {
        $reveal = $host || $poll->status === 'shown';

        return ['id' => $poll->id, 'question' => $poll->question, 'options' => $poll->options, 'status' => $poll->status,
            'xp' => (int) $poll->xp, 'mine' => $poll->choiceOf((int) $me->user_id),
            'counts' => $reveal ? $poll->counts() : null,
            'correct' => $reveal ? ($poll->correct === null ? null : (int) $poll->correct) : null,
            'answered' => (int) DB::table('online_poll_answers')->where('poll_id', $poll->id)->count(),
            // معلم/ناظر بعد از پایان: چه کسی درست و چه کسی نادرست زد (و چه کسی پاسخ نداد)
            'who' => $host && $poll->status !== 'open' ? $this->pollWho($poll) : null];
    }

    private function pollWho(OnlinePoll $poll): array
    {
        $answers = DB::table('online_poll_answers')->where('poll_id', $poll->id)->get(['user_id', 'choice'])->keyBy('user_id');
        $students = OnlineParticipant::with('user:id,name')->where('room_id', $poll->room_id)->where('role', 'student')->get();
        $rows = $students->map(function ($p) use ($answers, $poll) {
            $a = $answers[$p->user_id] ?? null;
            $choice = $a ? (int) $a->choice : null;

            return ['id' => (int) $p->user_id, 'name' => $p->user?->name ?? '—', 'choice' => $choice,
                'ok' => $choice === null || $poll->correct === null ? null : $choice === (int) $poll->correct];
        });

        // درست‌ها، بعد نادرست‌ها، بعد بی‌پاسخ‌ها
        return $rows->sortBy(fn ($r) => ($r['ok'] === true ? '0' : ($r['ok'] === false ? '1' : ($r['choice'] !== null ? '2' : '3'))) . $r['name'])->values()->all();
    }

    /** ثبتِ یک پیامِ اتصال برای نفرِ دیگر. */
    public function signal(OnlineRoom $room, int $from, int $to, string $kind, ?string $payload): void
    {
        DB::table('online_signals')->insert(['room_id' => $room->id, 'from_id' => $from, 'to_id' => $to, 'kind' => $kind,
            'payload' => $payload, 'created_at' => now()]);
        if (random_int(1, 40) === 1) {
            DB::table('online_signals')->where('created_at', '<', now()->subMinutes(3))->delete();
        }
    }

    // ── شروع و پایان ────────────────────────────────────────────────────

    public function start(OnlineRoom $room): void
    {
        if ($room->live()) {
            return;
        }
        $room->forceFill(['status' => 'live', 'started_at' => now(), 'ended_at' => null, 'host_seen_at' => now(),
            'media' => OnlineAccess::media(), 'xp_done' => false])->save();
        $room->bump();
        $this->announce($room);
    }

    /** پایانِ کلاس: ثبتِ زمانِ حضور و امتیازِ حضور. */
    public function end(OnlineRoom $room, ?User $by): void
    {
        if ($room->ended()) {
            return;
        }
        foreach (OnlineParticipant::where('room_id', $room->id)->whereNull('left_at')->get() as $p) {
            if ($p->online()) {
                $this->touch($p, true);
            }
            $p->forceFill(['left_at' => now(), 'hand_at' => null])->save();
        }
        $room->forceFill(['status' => 'ended', 'ended_at' => now()])->save();
        $room->setOpts(['timer_end' => null, 'recording' => false, 'announce' => null]);
        DB::table('online_signals')->where('room_id', $room->id)->delete();
        $this->rewardAttendance($room);
    }

    /** امتیازِ حضور: کسی که دستِ‌کم نیمی از کلاس (حداکثر ۱۰ دقیقه) حاضر بوده. */
    public function rewardAttendance(OnlineRoom $room): void
    {
        if ($room->xp_done || ! $room->started_at) {
            return;
        }
        $room->forceFill(['xp_done' => true])->save();
        $length = max(60, ($room->ended_at ?? now())->diffInSeconds($room->started_at, true));
        $need = (int) min(600, $length / 2);
        $teacher = $room->teacher;
        foreach (OnlineParticipant::with('user')->where('room_id', $room->id)->where('role', 'student')->where('seconds', '>=', $need)->get() as $p) {
            if (! $p->user) {
                continue;
            }
            $xp = self::xp($p->user, 'online_attend', self::ATTEND_XP);
            if ($xp > 0) {
                rescue(fn () => $this->game->award($p->user, $xp, '🎥 حضور در کلاسِ آنلاین — ' . $room->title, $teacher, OnlineRoom::class, $room->id), null, true);
                $p->increment('xp', $xp);
            }
        }
    }

    /** ستاره‌ی معلم به یک دانش‌آموز. */
    public function star(OnlineRoom $room, OnlineParticipant $p, ?User $by): int
    {
        $p->increment('stars');
        $xp = $p->user ? self::xp($p->user, 'online_star', self::STAR_XP) : 0;
        if ($xp > 0) {
            rescue(fn () => $this->game->award($p->user, $xp, '⭐ ستاره‌ی کلاسِ آنلاین — ' . $room->title, $by, OnlineRoom::class, $room->id), null, true);
            $p->increment('xp', $xp);
        }
        $room->bump();

        return $xp;
    }

    /** بستنِ نظرسنجی: امتیازِ پاسخِ درست. */
    public function closePoll(OnlineRoom $room, OnlinePoll $poll, ?User $by): void
    {
        if ($poll->status === 'open' && $poll->correct !== null && $poll->xp > 0) {
            $ids = DB::table('online_poll_answers')->where('poll_id', $poll->id)->where('choice', $poll->correct)->pluck('user_id');
            foreach (User::whereIn('id', $ids)->get() as $u) {
                $xp = self::xp($u, 'online_poll', (int) $poll->xp);
                if ($xp > 0) {
                    rescue(fn () => $this->game->award($u, $xp, '📊 پاسخِ درست در کلاسِ آنلاین — ' . $room->title, $by, OnlinePoll::class, $poll->id), null, true);
                    OnlineParticipant::where('room_id', $room->id)->where('user_id', $u->id)->increment('xp', $xp);
                }
            }
        }
        $poll->forceFill(['status' => 'shown'])->save();
        $room->bump();
    }

    /** قاعده‌ی امتیازِ مدرسه (صفحه‌ی «مدیریتِ امتیازها»)، اگر نصب باشد. */
    public static function xp(User $student, string $key, int $amount): int
    {
        if (class_exists(\App\Support\XpRules::class) && method_exists(\App\Support\XpRules::class, 'apply')) {
            try {
                return (int) \App\Support\XpRules::apply($student, $key, $amount);
            } catch (\Throwable $e) {
                Log::warning('online xp rule: ' . $e->getMessage());
            }
        }

        return $amount;
    }

    /** اعلان به بچه‌ها: «کلاس شروع شد». */
    public function announce(OnlineRoom $room): void
    {
        rescue(function () use ($room) {
            $ids = $room->audienceIds();
            if (! $ids) {
                return;
            }
            $ann = Announcement::create([
                'school_id' => $room->school_id, 'sender_id' => $room->teacher_id, 'audience' => 'personal',
                'title' => '🎥 کلاسِ آنلاین شروع شد — ' . $room->title,
                'body' => 'معلم منتظرِ توست! روی این پیام بزن و وارد کلاس شو. 🌟',
                'link' => route('online.room', $room->id, false),
            ]);
            $ann->recipients()->sync($ids);
        }, null, true);
    }

    /** اعلانِ کلاسِ برنامه‌ریزی‌شده. */
    public function notifyScheduled(OnlineRoom $room): void
    {
        rescue(function () use ($room) {
            $ids = $room->audienceIds();
            if (! $ids || ! $room->starts_at) {
                return;
            }
            $ann = Announcement::create([
                'school_id' => $room->school_id, 'sender_id' => $room->teacher_id, 'audience' => 'personal',
                'title' => '🗓️ کلاسِ آنلاین — ' . $room->title,
                'body' => 'زمان: ' . \App\Support\Jalali::format($room->starts_at) . ' ساعت ' . \App\Support\Jalali::fa($room->starts_at->format('H:i'))
                    . '. سرِ وقت از «کلاسِ آنلاین» وارد شو. 🎧',
                'link' => route('online.list', [], false),
            ]);
            $ann->recipients()->sync($ids);
        }, null, true);
    }

    // ── LiveKit ──────────────────────────────────────────────────────────

    /** توکنِ ورود به سرورِ LiveKit (JWT با HS256). */
    public function livekitToken(OnlineRoom $room, User $user, string $role): ?array
    {
        return $this->livekitTokenFor(self::livekitRoom($room), $user, $role);
    }

    /** توکن برای یک اتاقِ دلخواه (کلاس، یا اتاقِ آزمایشِ اتصال). */
    public function livekitTokenFor(string $roomName, User $user, string $role, int $ttl = 6 * 3600): ?array
    {
        $lk = OnlineAccess::livekit();
        if (! $lk) {
            return null;
        }
        $now = time();
        $claims = [
            'iss' => $lk['key'], 'sub' => (string) $user->id, 'name' => $user->name, 'nbf' => $now - 300, 'exp' => $now + $ttl,
            'metadata' => json_encode(['role' => $role], JSON_UNESCAPED_UNICODE),
            'video' => ['room' => $roomName, 'roomJoin' => true, 'canSubscribe' => true, 'canPublish' => true,
                'canPublishData' => true, 'roomAdmin' => $role !== 'student'],
        ];
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $head = $b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = $b64(json_encode($claims, JSON_UNESCAPED_UNICODE));
        $sig = $b64(hash_hmac('sha256', "$head.$body", $lk['secret'], true));

        return ['url' => $lk['url'], 'token' => "$head.$body.$sig"];
    }

    public static function livekitRoom(OnlineRoom $room): string
    {
        return 'starmah-' . $room->school_id . '-' . $room->id;
    }
}
