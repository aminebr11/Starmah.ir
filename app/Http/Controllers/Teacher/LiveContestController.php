<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\EduGame;
use App\Models\LiveContest;
use App\Models\LiveContestAnswer;
use App\Models\LiveContestPlayer;
use App\Models\LiveTrack;
use App\Models\SmartExam;
use App\Services\RemediationService;
use App\Support\BankAccess;
use App\Support\Jalali;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** معلم: «🏆 مسابقه‌ی زنده» — ساختن، اجرای روی تخته و تاریخچه. */
class LiveContestController extends Controller
{
    use \App\Http\Controllers\Concerns\StoresUploads;

    private function mine(Request $request, LiveContest $contest): void
    {
        abort_unless($contest->teacher_id === $request->user()->id, 403);
    }

    /** متنِ زمانِ پایان: «۵ دقیقه بعد از شروع» یا تاریخ و ساعت. */
    public static function endLabel(LiveContest $c): ?string
    {
        if ($c->ends_at) {
            return Jalali::format($c->ends_at, true) . ' ساعت ' . Jalali::fa($c->ends_at->format('H:i'));
        }
        if ($c->duration_min) {
            $end = $c->endsAt();

            return Jalali::fa((string) $c->duration_min) . ' دقیقه بعد از شروع' . ($end && $c->started_at ? ' (تا ساعت ' . Jalali::fa($end->format('H:i')) . ')' : '');
        }

        return null;
    }

    public static function when(LiveContest $c): ?string
    {
        return $c->starts_at ? Jalali::format($c->starts_at, true) . ' ساعت ' . Jalali::fa($c->starts_at->format('H:i')) : null;
    }

    public function index(Request $request): Response
    {
        if (! LiveContest::ready()) {
            \App\Support\AutoMigrate::ensure(true);
            \App\Support\DbSchema::forget();
        }
        $teacher = $request->user();
        $rooms = Classroom::where('teacher_id', $teacher->id)->orderBy('id')->get(['id', 'name', 'grade']);
        $contests = LiveContest::ready() ? LiveContest::where('teacher_id', $teacher->id)->withCount('players')->latest()->limit(60)->get() : collect();
        $contests->filter(fn ($c) => $c->phase !== 'end')->each(fn ($c) => rescue(fn () => $c->tick(), null, false));

        return Inertia::render('Teacher/LiveContests', [
            'ready' => LiveContest::ready(),
            'contests' => $contests->map(fn ($c) => [
                'id' => $c->id, 'title' => $c->title, 'phase' => $c->phase, 'mode' => $c->mode, 'code' => $c->code,
                'classroom' => $c->classroom_id ? $rooms->firstWhere('id', $c->classroom_id)?->name : 'همه‌ی کلاس‌هایم',
                'questions' => $c->total(), 'seconds' => $c->seconds, 'players' => (int) $c->players_count,
                'when' => self::when($c), 'date' => Jalali::format($c->created_at),
                'winner' => $c->phase === 'end' ? $c->players()->with('student:id,name')->orderByDesc('score')->first()?->student?->name : null,
                'started' => $c->phase !== 'lobby' || (int) $c->players_count > 0,
                'music' => $c->music_track_id,
                'ends' => self::endLabel($c), 'round' => (int) ($c->round ?: 1),
                'edit' => [
                    'title' => $c->title, 'classroom_id' => $c->classroom_id ?? '', 'mode' => $c->mode, 'seconds' => $c->seconds,
                    'starts_at' => $c->starts_at?->format('Y-m-d H:i') ?? '', 'music_track_id' => $c->music_track_id ?? '',
                    'end_type' => $c->ends_at ? 'at' : ($c->duration_min ? 'duration' : 'none'), 'duration_min' => $c->duration_min ?: 5,
                    'ends_at' => $c->ends_at?->format('Y-m-d H:i') ?? '',
                    'questions' => collect($c->questions ?? [])->map(fn ($q) => ['prompt' => $q['prompt'], 'choices' => $q['choices'], 'answer' => $q['answer']])->values(),
                    'locked' => $c->live() ? $c->current + 1 : 0,
                ],
            ])->values(),
            'classrooms' => $rooms,
            'tracks' => LiveTrack::ready() ? LiveTrack::where('teacher_id', $teacher->id)->latest()->get()->map->data()->values() : [],
            'musicReady' => LiveTrack::ready(),
            'exams' => SmartExam::where('teacher_id', $teacher->id)->latest()->limit(40)->get(['id', 'title']),
            'games' => EduGame::where('teacher_id', $teacher->id)->latest()->limit(40)->get(['id', 'title']),
            'subjects' => BankAccess::visibleQuery($teacher)->whereIn('type', ['mc', 'tf'])->whereNotNull('subject')
                ->distinct()->orderBy('subject')->limit(30)->pluck('subject'),
        ]);
    }

    /** سؤال از بانک / آزمون / بازی (برای پر کردنِ فرم). */
    public function import(Request $request): JsonResponse
    {
        $teacher = $request->user();
        $data = $request->validate([
            'source' => ['required', 'in:bank,exam,game'], 'id' => ['nullable', 'integer'],
            'subject' => ['nullable', 'string', 'max:60'], 'topic' => ['nullable', 'string', 'max:80'], 'n' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);
        $n = $data['n'] ?? 10;
        $rows = match ($data['source']) {
            'exam' => SmartExam::where('teacher_id', $teacher->id)->findOrFail($data['id'])->questions()->whereIn('type', ['mc', 'tf'])->get(),
            'game' => EduGame::where('teacher_id', $teacher->id)->findOrFail($data['id'])->questions()->whereIn('type', ['mc', 'tf'])->get(),
            default => BankAccess::visibleQuery($teacher)->whereIn('type', ['mc', 'tf'])
                ->when($data['subject'] ?? null, fn ($q, $s) => $q->where('subject', $s))
                ->when($data['topic'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('topic', 'like', "%{$t}%")->orWhere('prompt', 'like', "%{$t}%")))
                ->inRandomOrder()->limit($n * 2)->get(),
        };
        $qs = LiveContest::normalize($rows->filter(fn ($q) => RemediationService::playable(RemediationService::snapshot($q)))
            ->map(fn ($q) => RemediationService::snapshot($q))->all());

        return response()->json(['questions' => array_slice($qs, 0, $n)]);
    }

    /**
     * اعتبارسنجیِ فرمِ ساخت/ویرایش → ویژگی‌های مسابقه.
     * ویرایشِ مسابقه‌ی در حالِ اجرا: سؤال‌هایی که پرسیده شده‌اند دست نمی‌خورند (پاسخ‌ها و رتبه‌ها به آن‌ها گره خورده‌اند).
     */
    private function attributes(Request $request, ?LiveContest $editing = null): array
    {
        $teacher = $request->user();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'classroom_id' => ['nullable', 'integer'],
            'mode' => ['required', 'in:manual,auto'],
            'starts_at' => ['nullable', 'date', 'required_if:mode,auto'],
            'seconds' => ['required', 'integer', 'min:5', 'max:120'],
            'questions' => ['required', 'array', 'min:1', 'max:40'],
            'questions.*.prompt' => ['required', 'string', 'max:500'],
            'questions.*.choices' => ['required', 'array', 'min:2', 'max:4'],
            'questions.*.answer' => ['required', 'integer', 'min:0', 'max:3'],
            'music_track_id' => ['nullable', 'integer'],
            'end_type' => ['nullable', 'in:none,duration,at'],
            'duration_min' => ['nullable', 'integer', 'min:1', 'max:600', 'required_if:end_type,duration'],
            'ends_at' => ['nullable', 'date', 'required_if:end_type,at'],
        ], [
            'duration_min.required_if' => 'چند دقیقه بعد از شروع تمام شود؟',
            'ends_at.required_if' => 'تاریخ و ساعتِ پایان را انتخاب کنید.',
            'title.required' => 'عنوانِ مسابقه را بنویسید.',
            'questions.required' => 'دست‌کم یک سؤال بگذارید.',
            'questions.min' => 'دست‌کم یک سؤال بگذارید.',
            'starts_at.required_if' => 'برای شروعِ خودکار، تاریخ و ساعت را مشخص کنید.',
            'questions.*.prompt.required' => 'متنِ همه‌ی سؤال‌ها را بنویسید.',
        ]);
        if (! empty($data['classroom_id'])) {
            Classroom::where('teacher_id', $teacher->id)->findOrFail($data['classroom_id']);
        }
        $endType = $data['end_type'] ?? 'none';
        if ($endType === 'at') {
            $end = \Illuminate\Support\Carbon::parse($data['ends_at']);
            $mustBeFuture = ! $editing || $editing->phase !== 'end';
            if (($mustBeFuture && $end->lte(now())) || (! empty($data['starts_at']) && $end->lte(\Illuminate\Support\Carbon::parse($data['starts_at'])))) {
                throw ValidationException::withMessages(['ends_at' => 'زمانِ پایان باید بعد از زمانِ شروع (و بعد از الان) باشد.']);
            }
        }
        $qs = LiveContest::normalize($data['questions']);
        if (count($qs) !== count($data['questions'])) {
            throw ValidationException::withMessages(['questions' => 'هر سؤال باید متن، دست‌کم دو گزینه‌ی پر و یک جوابِ درست داشته باشد.']);
        }
        if ($editing && $editing->live()) {
            $asked = $editing->current + 1;
            if (count($qs) < $asked) {
                throw ValidationException::withMessages(['questions' => 'مسابقه در حالِ اجراست؛ ' . Jalali::fa((string) $asked) . ' سؤالِ اول پرسیده شده و نمی‌شود حذفشان کرد.']);
            }
            $qs = array_merge(array_slice($editing->questions ?? [], 0, $asked), array_slice($qs, $asked));
        }
        $attrs = [
            'classroom_id' => $data['classroom_id'] ?: null, 'title' => $data['title'], 'mode' => $data['mode'],
            'starts_at' => $data['starts_at'] ?? null, 'seconds' => $data['seconds'], 'questions' => $qs,
        ];
        if (\App\Models\LiveContestResult::ready()) {
            $attrs['duration_min'] = $endType === 'duration' ? (int) $data['duration_min'] : null;
            $attrs['ends_at'] = $endType === 'at' ? $data['ends_at'] : null;
        }
        if (($track = $this->ownTrack($teacher->id, $data['music_track_id'] ?? null)) !== false) {
            $attrs['music_track_id'] = $track;
        }

        return $attrs;
    }

    public function store(Request $request): RedirectResponse
    {
        $teacher = $request->user();
        $contest = LiveContest::create($this->attributes($request) + [
            'school_id' => $teacher->school_id, 'teacher_id' => $teacher->id, 'code' => LiveContest::newCode(), 'phase' => 'lobby',
        ]);
        $this->announce($contest);

        return redirect()->route('teacher.live')->with('flash', '🏆 «' . $contest->title . '» ساخته شد و به بچه‌ها خبر داده شد.' . ($contest->mode === 'auto' ? ' سرِ ساعت خودش شروع می‌شود.' : ($contest->starts_at ? ' سرِ ساعت خودش شروع می‌شود؛ اگر تخته را باز کنید، رفتن به سؤالِ بعد با شماست.' : ' هر وقت آماده بودید «اجرا روی تخته» را بزنید.')));
    }

    /** ویرایش در هر زمان (پیش از شروع، در حالِ اجرا یا بعد از پایان برای دورِ بعد). */
    public function update(Request $request, LiveContest $contest): RedirectResponse
    {
        $this->mine($request, $contest);
        $before = [$contest->starts_at?->timestamp, $contest->endsAt()?->timestamp];
        $attrs = $this->attributes($request, $contest);
        $contest->update($attrs);
        $contest->refresh();
        $timeChanged = $before !== [$contest->starts_at?->timestamp, $contest->endsAt()?->timestamp];
        if ($request->boolean('notify') && $contest->phase !== 'end') {
            $this->announce($contest, true);
        }
        $note = $contest->live() ? ' سؤال‌های پرسیده‌شده دست نخورد؛ تغییرها از سؤالِ بعد اعمال می‌شود.' : ($contest->phase === 'end' ? ' در «اجرای دوباره» با همین تغییرها برگزار می‌شود.' : '');

        return redirect()->route('teacher.live')->with('flash', '✏️ «' . $contest->title . '» ویرایش شد.' . $note . ($timeChanged && ! $request->boolean('notify') && $contest->phase !== 'end' ? ' (زمان عوض شد؛ اگر لازم است «اعلان به بچه‌ها» را هم بزنید.)' : ''));
    }

    private function announce(LiveContest $c, bool $updated = false): void
    {
        rescue(function () use ($c, $updated) {
            $ids = $c->audienceIds();
            if (! $ids) return;
            $ann = Announcement::create([
                'school_id' => $c->school_id, 'sender_id' => $c->teacher_id, 'audience' => 'personal',
                'title' => ($updated ? '✏️ تغییر در مسابقه‌ی زنده — ' : '🏆 مسابقه‌ی زنده — ') . $c->title,
                'body' => ($c->starts_at ? 'زمانِ شروع: ' . self::when($c) . '. ' : '') . (self::endLabel($c) ? 'پایان: ' . self::endLabel($c) . '. ' : '') . 'سؤال‌ها روی تخته می‌آید و تو با گوشی یا تبلت جواب می‌دهی. هر چه سریع‌تر و درست‌تر، امتیازِ بیشتر! ⚡',
                'link' => route('live.play', $c->id, false),
            ]);
            $ann->recipients()->sync($ids);
        }, null, true);
    }

    public function host(Request $request, LiveContest $contest): Response
    {
        $this->mine($request, $contest);

        return Inertia::render('Teacher/LiveHost', ['contest' => ['id' => $contest->id, 'title' => $contest->title, 'code' => $contest->code,
            'mode' => $contest->mode, 'total' => $contest->total(), 'seconds' => $contest->seconds, 'when' => self::when($contest),
            'music' => $this->musicFor($contest)]]);
    }

    /** وضعیتِ کاملِ تخته (هر ثانیه). */
    public function state(Request $request, LiveContest $contest): JsonResponse
    {
        $this->mine($request, $contest);
        $contest->markHost();
        $contest->tick();
        $q = $contest->current;
        $question = $contest->live() ? ($contest->questions[$q] ?? null) : null;
        $players = $contest->players()->count();
        $answered = $question ? LiveContestAnswer::where('live_contest_id', $contest->id)->where('q_index', $q)->count() : 0;
        $online = LiveContestPlayer::where('live_contest_id', $contest->id)->where('last_seen_at', '>=', now()->subSeconds(25))->count();
        $ranking = in_array($contest->phase, ['reveal', 'end', 'lobby'], true) ? $contest->ranking() : collect();

        return response()->json([
            'phase' => $contest->phase, 'current' => $q, 'total' => $contest->total(), 'seconds' => $contest->seconds,
            'remaining' => $contest->remaining(), 'elapsed' => $contest->elapsed(), 'mode' => $contest->mode, 'lead' => $contest->lead(),
            'ends_in' => $contest->endsIn(), 'ends' => self::endLabel($contest),
            'golden' => $contest->live() && $contest->golden($q),
            'starts_in' => $contest->starts_at && $contest->phase === 'lobby' && $contest->starts_at->gt(now()->subHours(LiveContest::SCHEDULE_WINDOW_H))
                ? max(0, (int) now()->diffInSeconds($contest->starts_at, false)) : null,
            'question' => $question ? ['prompt' => $question['prompt'], 'choices' => $question['choices']] : null,
            'answer' => $contest->phase === 'reveal' && $question ? $question['answer'] : null,
            'dist' => $contest->phase === 'reveal' && $question ? $contest->distribution($q) : null,
            'answered' => $answered, 'players' => $players, 'online' => $online,
            'names' => $contest->phase === 'lobby' ? $ranking->pluck('name')->take(60)->values() : null,
            'top' => $contest->phase === 'lobby' ? null : $ranking->take($contest->phase === 'end' ? 50 : 6)->values(),
            'audience' => $contest->phase === 'lobby' ? count($contest->audienceIds()) : null,
        ]);
    }

    /** فرمان‌های تخته: شروع، نمایشِ جواب، بعدی، پایان، از نو. */
    public function go(Request $request, LiveContest $contest): JsonResponse
    {
        $this->mine($request, $contest);
        $action = $request->validate(['action' => ['required', 'in:start,reveal,next,end,reset']])['action'];
        $contest->tick();
        match ($action) {
            'start' => in_array($contest->phase, ['lobby', 'draft'], true) && $contest->total() ? $contest->moveTo('question', 0) : null,
            'reveal' => $contest->phase === 'question' ? $contest->moveTo('reveal') : null,
            'next' => $contest->phase === 'reveal' ? $contest->next() : null,
            'end' => $contest->phase !== 'end' ? $contest->moveTo('end') : null,
            'reset' => $this->reset($contest),
        };

        return $this->state($request, $contest->fresh());
    }

    private function reset(LiveContest $contest): void
    {
        LiveContestAnswer::where('live_contest_id', $contest->id)->delete();
        LiveContestPlayer::where('live_contest_id', $contest->id)->delete();
        // امتیازِ (XP) دورِ قبل می‌ماند؛ دورِ تازه دوباره امتیاز نمی‌دهد
        // سابقه‌ی دورِ قبل در live_contest_results می‌ماند؛ این یک دورِ تازه است
        $contest->update(['phase' => 'lobby', 'current' => -1, 'phase_at' => null, 'mode' => 'manual', 'starts_at' => null]
            + (\App\Models\LiveContestResult::ready() ? ['round' => (int) ($contest->round ?: 1) + 1, 'started_at' => null,
                'ends_at' => $contest->ends_at && $contest->ends_at->isPast() ? null : $contest->ends_at] : []));
    }

    /* ─────────────── 🎵 آهنگِ مسابقه ─────────────── */

    /** شناسه‌ی آهنگِ خودِ معلم؛ null = بدونِ آهنگ؛ false = ستون هنوز ساخته نشده. */
    private function ownTrack(int $teacherId, $id): int|null|false
    {
        if (! LiveTrack::ready()) {
            return false;
        }

        return $id ? LiveTrack::where('teacher_id', $teacherId)->whereKey((int) $id)->value('id') : null;
    }

    private function musicFor(LiveContest $contest): ?array
    {
        if (! LiveTrack::ready() || ! $contest->music_track_id) {
            return null;
        }

        return LiveTrack::find($contest->music_track_id)?->data();
    }

    /** آپلودِ آهنگِ تازه به کتابخانه‌ی معلم (در سیستم می‌ماند). */
    public function storeTrack(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless(LiveTrack::ready(), 503, 'جدول‌های آهنگ هنوز ساخته نشده‌اند.');
        $teacher = $request->user();
        $request->validate(['track' => ['required', 'file', 'max:15360'], 'title' => ['nullable', 'string', 'max:120']], [
            'track.required' => 'فایلِ آهنگ را انتخاب کنید.',
            'track.max' => 'آهنگ حداکثر ۱۵ مگابایت.',
            'track.uploaded' => 'فایل به سرور نرسید (خیلی بزرگ است).',
        ]);
        $file = $request->file('track');
        if (! $this->audioExtension($file, AudioTaskController::AUDIO_EXT)) {
            throw ValidationException::withMessages(['track' => 'فقط فایلِ صوتی (mp3، m4a، wav، ogg).']);
        }
        if (LiveTrack::where('teacher_id', $teacher->id)->count() >= 30) {
            throw ValidationException::withMessages(['track' => 'حداکثر ۳۰ آهنگ؛ اول چند آهنگِ قدیمی را حذف کنید.']);
        }
        $path = $this->storeAudio($file, 'live-music', AudioTaskController::AUDIO_EXT);
        if (! $path) {
            throw ValidationException::withMessages(['track' => 'ذخیره‌ی آهنگ انجام نشد؛ دوباره امتحان کنید.']);
        }
        $title = trim((string) $request->input('title')) ?: mb_substr(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 0, 120);
        $track = LiveTrack::create(['school_id' => $teacher->school_id, 'teacher_id' => $teacher->id, 'title' => $title ?: 'آهنگِ مسابقه', 'path' => $path, 'size' => (int) $file->getSize()]);

        return $request->expectsJson() && ! $request->header('X-Inertia')
            ? response()->json(['track' => $track->data()])
            : back()->with('flash', '🎵 «' . $track->title . '» به آهنگ‌های شما اضافه شد.');
    }

    public function destroyTrack(Request $request, LiveTrack $track): RedirectResponse
    {
        abort_unless($track->teacher_id === $request->user()->id, 403);
        \Illuminate\Support\Facades\Storage::disk('public')->delete($track->path);
        LiveContest::where('music_track_id', $track->id)->update(['music_track_id' => null]);
        $track->delete();

        return back()->with('flash', 'آهنگ حذف شد.');
    }

    /** پخشِ آهنگ برای تخته (با نوعِ درست و Range). */
    public function track(Request $request, LiveTrack $track): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        abort_unless($track->teacher_id === $request->user()->id || $request->user()->hasRole(\App\Support\Roles::SUPER_ADMIN), 403);
        abort_unless(\Illuminate\Support\Facades\Storage::disk('public')->exists($track->path), 404, 'فایلِ آهنگ پیدا نشد.');

        return \App\Support\MediaFile::response($track->path, 'live-music-' . $track->id);
    }

    /** آهنگِ یک مسابقه را عوض کن (یا بدونِ آهنگ). */
    public function music(Request $request, LiveContest $contest): RedirectResponse|JsonResponse
    {
        $this->mine($request, $contest);
        $id = $this->ownTrack($request->user()->id, $request->validate(['track_id' => ['nullable', 'integer']])['track_id'] ?? null);
        abort_if($id === false, 503);
        $contest->update(['music_track_id' => $id]);

        return $request->expectsJson() && ! $request->header('X-Inertia')
            ? response()->json(['music' => $this->musicFor($contest->fresh())])
            : back()->with('flash', $id ? '🎵 آهنگِ مسابقه انتخاب شد.' : '🔇 این مسابقه بدونِ آهنگ برگزار می‌شود.');
    }

    /** سوابقِ نتیجه‌ی یک مسابقه برای معلم: رتبه‌بندیِ کامل و درصدِ درستیِ هر سؤال. */
    public function results(Request $request, LiveContest $contest): JsonResponse
    {
        $this->mine($request, $contest);
        $total = max(1, $contest->total());
        $answers = LiveContestAnswer::where('live_contest_id', $contest->id)->get(['student_id', 'q_index', 'correct', 'ms']);
        $byQ = $answers->groupBy('q_index');

        return response()->json([
            'phase' => $contest->phase,
            'ranking' => $contest->ranking()->map(fn ($r) => $r + [
                'accuracy' => (int) round(100 * $r['correct'] / $total),
                'answered' => $answers->where('student_id', $r['id'])->count(),
            ])->values(),
            'questions' => collect($contest->questions ?? [])->values()->map(fn ($q, $i) => [
                'prompt' => $q['prompt'], 'answer' => $q['choices'][$q['answer']] ?? null,
                'answered' => ($byQ[$i] ?? collect())->count(),
                'correct' => ($byQ[$i] ?? collect())->where('correct', true)->count(),
                'avg_s' => ($byQ[$i] ?? collect())->count() ? round(($byQ[$i])->avg('ms') / 1000, 1) : null,
            ]),
            'audience' => count($contest->audienceIds()),
            'ends' => self::endLabel($contest),
            'rounds' => \App\Models\LiveContestResult::ready() ? \App\Models\LiveContestResult::where('live_contest_id', $contest->id)->with('student:id,name')
                ->orderByDesc('round')->orderBy('rank')->get()->groupBy('round')->map(fn ($rows, $round) => [
                    'round' => (int) $round, 'date' => Jalali::format($rows->first()->finished_at, true) . ' ' . Jalali::fa($rows->first()->finished_at?->format('H:i') ?? ''),
                    'players' => $rows->count(), 'avg_accuracy' => (int) round($rows->avg('accuracy')),
                    'rows' => $rows->map(fn ($r) => ['rank' => $r->rank, 'name' => $r->student?->name ?? '—', 'score' => $r->score, 'correct' => $r->correct, 'accuracy' => $r->accuracy])->values(),
                ])->values() : [],
        ]);
    }

    public function destroy(Request $request, LiveContest $contest): RedirectResponse
    {
        $this->mine($request, $contest);
        LiveContestAnswer::where('live_contest_id', $contest->id)->delete();
        LiveContestPlayer::where('live_contest_id', $contest->id)->delete();
        $contest->delete();

        return redirect()->route('teacher.live')->with('flash', 'مسابقه حذف شد.');
    }
}
