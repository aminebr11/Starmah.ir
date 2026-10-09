<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\EduGame;
use App\Models\LiveContest;
use App\Models\LiveContestAnswer;
use App\Models\LiveContestPlayer;
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
    private function mine(Request $request, LiveContest $contest): void
    {
        abort_unless($contest->teacher_id === $request->user()->id, 403);
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
            ])->values(),
            'classrooms' => $rooms,
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

    public function store(Request $request): RedirectResponse
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
        ], [
            'title.required' => 'عنوانِ مسابقه را بنویسید.',
            'questions.required' => 'دست‌کم یک سؤال بگذارید.',
            'questions.min' => 'دست‌کم یک سؤال بگذارید.',
            'starts_at.required_if' => 'برای شروعِ خودکار، تاریخ و ساعت را مشخص کنید.',
            'questions.*.prompt.required' => 'متنِ همه‌ی سؤال‌ها را بنویسید.',
        ]);
        if (! empty($data['classroom_id'])) {
            Classroom::where('teacher_id', $teacher->id)->findOrFail($data['classroom_id']);
        }
        $qs = LiveContest::normalize($data['questions']);
        if (count($qs) !== count($data['questions'])) {
            throw ValidationException::withMessages(['questions' => 'هر سؤال باید متن، دست‌کم دو گزینه‌ی پر و یک جوابِ درست داشته باشد.']);
        }

        $contest = LiveContest::create([
            'school_id' => $teacher->school_id, 'teacher_id' => $teacher->id, 'classroom_id' => $data['classroom_id'] ?: null,
            'title' => $data['title'], 'code' => LiveContest::newCode(), 'mode' => $data['mode'],
            'starts_at' => $data['starts_at'] ?? null, 'phase' => 'lobby', 'seconds' => $data['seconds'], 'questions' => $qs,
        ]);
        $this->announce($contest);

        return redirect()->route('teacher.live')->with('flash', '🏆 «' . $contest->title . '» ساخته شد و به بچه‌ها خبر داده شد.' . ($contest->mode === 'auto' ? ' سرِ ساعت خودش شروع می‌شود.' : ($contest->starts_at ? ' سرِ ساعت خودش شروع می‌شود؛ اگر تخته را باز کنید، رفتن به سؤالِ بعد با شماست.' : ' هر وقت آماده بودید «اجرا روی تخته» را بزنید.')));
    }

    private function announce(LiveContest $c): void
    {
        rescue(function () use ($c) {
            $ids = $c->audienceIds();
            if (! $ids) return;
            $ann = Announcement::create([
                'school_id' => $c->school_id, 'sender_id' => $c->teacher_id, 'audience' => 'personal',
                'title' => '🏆 مسابقه‌ی زنده — ' . $c->title,
                'body' => ($c->starts_at ? 'زمانِ شروع: ' . self::when($c) . '. ' : '') . 'سؤال‌ها روی تخته می‌آید و تو با گوشی یا تبلت جواب می‌دهی. هر چه سریع‌تر و درست‌تر، امتیازِ بیشتر! ⚡',
                'link' => route('live.play', $c->id, false),
            ]);
            $ann->recipients()->sync($ids);
        }, null, true);
    }

    public function host(Request $request, LiveContest $contest): Response
    {
        $this->mine($request, $contest);

        return Inertia::render('Teacher/LiveHost', ['contest' => ['id' => $contest->id, 'title' => $contest->title, 'code' => $contest->code,
            'mode' => $contest->mode, 'total' => $contest->total(), 'seconds' => $contest->seconds, 'when' => self::when($contest)]]);
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
        $contest->update(['phase' => 'lobby', 'current' => -1, 'phase_at' => null, 'mode' => 'manual', 'starts_at' => null]);
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
