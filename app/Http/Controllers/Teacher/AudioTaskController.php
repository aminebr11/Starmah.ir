<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\StoresUploads;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AudioSubmission;
use App\Models\AudioTask;
use App\Models\Classroom;
use App\Services\SpeechService;
use App\Support\Jalali;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * معلم: «🎧 املا و روخوانی».
 * صدا از سه راه: صدای خودِ معلم (ضبط در همین صفحه)، فایلِ صوتی، یا متن → صدای فارسیِ سرور (جمله‌به‌جمله).
 */
class AudioTaskController extends Controller
{
    use StoresUploads;

    public const AUDIO_EXT = ['webm', 'ogg', 'oga', 'mp3', 'm4a', 'mp4', 'wav', 'aac'];

    private function rooms(Request $request)
    {
        return Classroom::where('teacher_id', $request->user()->id)->orderBy('id')->get(['id', 'name', 'grade', 'school_id']);
    }

    private function mine(Request $request, AudioTask $task): void
    {
        abort_unless($task->teacher_id === $request->user()->id, 403);
    }

    public function index(Request $request): Response
    {
        if (! AudioTask::ready()) {
            \App\Support\AutoMigrate::ensure(true);
            \App\Support\DbSchema::forget();
        }
        $rooms = $this->rooms($request);
        $tasks = AudioTask::ready() ? AudioTask::where('teacher_id', $request->user()->id)
            ->withCount(['submissions as submitted' => fn ($q) => $q->whereNotNull('file_path'),
                'submissions as graded' => fn ($q) => $q->whereNotNull('graded_at')])
            ->latest()->get() : collect();

        return Inertia::render('Teacher/AudioTasks', [
            'tasks' => $tasks->map(fn ($t) => [
                'id' => $t->id, 'kind' => $t->kind, 'title' => $t->title, 'source' => $t->source,
                'classroom' => $rooms->firstWhere('id', $t->classroom_id)?->name,
                'sentences' => count($t->sentences ?? []), 'published' => $t->is_published,
                'scheduled' => $t->publish_at && ! $t->is_published ? Jalali::format($t->publish_at, true) . ' ساعت ' . Jalali::fa($t->publish_at->format('H:i')) : null,
                'building' => $t->needsBuild(),
                'due' => $t->due_at ? Jalali::format($t->due_at, true) : null,
                'date' => Jalali::format($t->created_at),
                'submitted' => (int) $t->submitted, 'graded' => (int) $t->graded,
                'students' => $t->classroom_id ? (int) rescue(fn () => Classroom::find($t->classroom_id)?->students()->count(), 0, false) : 0,
            ])->values(),
            'classrooms' => $rooms->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'grade' => $c->grade, 'dictation' => AudioTask::dictationAllowed($c->grade),
            ])->values(),
            'tts' => SpeechService::available(),
            'ready' => AudioTask::ready(),
        ]);
    }

    public function store(Request $request, SpeechService $speech): RedirectResponse
    {
        $teacher = $request->user();
        $data = $request->validate([
            'kind' => ['required', 'in:dictation,reading'],
            'title' => ['required', 'string', 'max:150'],
            'classroom_id' => ['required', 'integer'],
            'source' => ['required', 'in:voice,upload,tts'],
            'text' => ['nullable', 'string', 'max:4000'],
            'audio' => ['nullable', 'file', 'max:20480'],
            'score_type' => ['required', 'in:descriptive,numeric'],
            'penalty' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'due_at' => ['nullable', 'date'],
            'publish' => ['nullable', 'boolean'],
            // review: اول گوش می‌دهم بعد منتشر می‌کنم · schedule: سرِ ساعتِ مشخص · now: همین حالا
            'publish_mode' => ['nullable', 'in:review,schedule,now'],
            'publish_at' => ['nullable', 'required_if:publish_mode,schedule', 'date'],
            'pace' => ['nullable', 'in:short,normal,long'],
        ], [
            'title.required' => 'عنوان را بنویسید.',
            'audio.max' => 'فایلِ صوتی حداکثر ۲۰ مگابایت.',
            'publish_at.required_if' => 'زمانِ انتشار را انتخاب کنید.',
        ]);
        $mode = $data['publish_mode'] ?? (! empty($data['publish']) ? 'now' : 'review');
        if ($mode === 'schedule' && \Illuminate\Support\Carbon::parse($data['publish_at'])->lte(now())) {
            throw ValidationException::withMessages(['publish_at' => 'زمانِ انتشار باید در آینده باشد.']);
        }
        $room = Classroom::where('teacher_id', $teacher->id)->findOrFail($data['classroom_id']);
        if ($data['kind'] === 'dictation' && ! AudioTask::dictationAllowed($room->grade)) {
            throw ValidationException::withMessages(['classroom_id' => 'پایه‌ی «' . ($room->grade ?: '—') . '» درسِ املا ندارد؛ برای این کلاس «روخوانی» بسازید.']);
        }
        $text = trim((string) ($data['text'] ?? ''));
        if (($data['source'] === 'tts' || $data['kind'] === 'reading') && $text === '') {
            throw ValidationException::withMessages(['text' => $data['kind'] === 'reading' ? 'متنِ روخوانی را بنویسید.' : 'متنِ املا را بنویسید تا سیستم بخواند.']);
        }
        if (in_array($data['source'], ['voice', 'upload'], true) && ! $request->hasFile('audio')) {
            throw ValidationException::withMessages(['audio' => $data['source'] === 'voice' ? 'اول صدای خودتان را ضبط کنید.' : 'فایلِ صوتی را انتخاب کنید.']);
        }

        $audioPath = null;
        if ($request->hasFile('audio')) {
            if (! $this->audioExtension($request->file('audio'), self::AUDIO_EXT)) {
                throw ValidationException::withMessages(['audio' => 'فرمتِ صوتی معتبر نیست (mp3، m4a، wav، ogg، webm).']);
            }
            $audioPath = $this->storeAudio($request->file('audio'), 'audio-tasks', self::AUDIO_EXT) ?: null;
        }

        // متن → صدای سیستم: این‌جا ساخته نمی‌شود (۲۰ جمله در یک درخواست از مهلتِ سرور و سرویسِ هوش مصنوعی
        // بیشتر می‌شد و ارتباط قطع می‌شد). صفحه‌ی تکلیف جمله‌به‌جمله می‌سازد و پیشرفت را نشان می‌دهد.
        $pace = $data['pace'] ?? 'normal';
        $sentences = $text !== '' ? array_map(fn ($s) => ['text' => $s, 'audio' => null], AudioTask::speechSentences($text)) : [];

        $task = AudioTask::create([
            'school_id' => $teacher->school_id, 'teacher_id' => $teacher->id, 'classroom_id' => $room->id,
            'kind' => $data['kind'], 'title' => $data['title'], 'grade' => $room->grade,
            'subject' => $data['kind'] === 'dictation' ? 'املا' : 'فارسی',
            'text' => $text ?: null, 'sentences' => $sentences, 'source' => $data['source'], 'audio_path' => $audioPath,
            'score_type' => $data['score_type'], 'penalty' => $data['penalty'] ?? 0.5, 'pace' => $pace,
            'due_at' => $data['due_at'] ?? null,
        ] + ($this->hasSchedule() ? ['publish_at' => $mode === 'schedule' ? $data['publish_at'] : null] : []));
        // «همین حالا» فقط وقتی صدا آماده است (صدای خودِ معلم یا فایل)؛ صدای سیستم بعد از ساخته‌شدن و گوش‌دادن
        if ($mode === 'now' && $task->audioReady()) {
            $task->publishNow();
        }

        $msg = '✅ «' . $task->title . '» ذخیره شد' . ($task->is_published ? ' و برای کلاس فرستاده شد.' : '.');
        if ($task->needsBuild()) {
            $msg .= ' 🤖 صدای سیستم الان جمله‌به‌جمله ساخته می‌شود؛ بعد گوش بدهید و تأیید کنید.';
        } elseif (! $task->is_published) {
            $msg .= $mode === 'schedule' ? ' 🗓️ سرِ زمانِ تعیین‌شده خودش منتشر می‌شود.' : ' 🎧 گوش بدهید و اگر خوب بود «تأیید و انتشار» را بزنید.';
        }

        return redirect()->route('teacher.audio.show', $task)->with('flash', $msg);
    }

    private function hasSchedule(): bool
    {
        return \App\Support\DbSchema::hasColumn('audio_tasks', 'publish_at');
    }

    public function publish(Request $request, AudioTask $task): RedirectResponse
    {
        $this->mine($request, $task);
        if ($task->is_published) {
            $task->update(['is_published' => false]);

            return back()->with('flash', '⏸️ از دیدِ دانش‌آموزان پنهان شد');
        }
        if (! $task->audioReady()) {
            return back()->with('flash', '⏳ اول صدا باید کامل ساخته شود؛ بعد گوش بدهید و منتشر کنید.');
        }
        $task->publishNow();

        return back()->with('flash', '📣 برای کلاس فرستاده شد');
    }

    /** 🗓️ زمان‌بندیِ انتشار (یا لغوِ آن با خالی گذاشتن). */
    public function schedule(Request $request, AudioTask $task): RedirectResponse
    {
        $this->mine($request, $task);
        abort_unless($this->hasSchedule(), 503, 'جدولِ این بخش به‌روز نیست؛ «به‌روزرسانیِ پایگاه‌داده» را اجرا کنید.');
        $data = $request->validate(['publish_at' => ['nullable', 'date']]);
        $at = ! empty($data['publish_at']) ? \Illuminate\Support\Carbon::parse($data['publish_at']) : null;
        if ($at && $at->lte(now())) {
            throw ValidationException::withMessages(['publish_at' => 'زمانِ انتشار باید در آینده باشد.']);
        }
        $task->update(['publish_at' => $at]);

        return back()->with('flash', $at ? '🗓️ زمان‌بندی شد: ' . Jalali::format($at, true) . ' ساعت ' . Jalali::fa($at->format('H:i')) : 'زمان‌بندیِ انتشار لغو شد.');
    }

    /** ✏️ ویرایش: عنوان، متن، مکث، مهلت؛ با عوض‌شدنِ متن/مکث، صدای سیستم دوباره ساخته می‌شود. */
    public function update(Request $request, AudioTask $task): RedirectResponse
    {
        $this->mine($request, $task);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'text' => ['nullable', 'string', 'max:4000'],
            'pace' => ['nullable', 'in:short,normal,long'],
            'due_at' => ['nullable', 'date'],
            'source' => ['nullable', 'in:tts,voice,upload'],
            'score_type' => ['nullable', 'in:descriptive,numeric'],
            'penalty' => ['nullable', 'numeric', 'min:0', 'max:5'],
        ], ['title.required' => 'عنوان را بنویسید.']);
        $text = trim((string) ($data['text'] ?? ''));
        $source = $data['source'] ?? $task->source;
        if (($source === 'tts' || $task->kind === 'reading') && $text === '') {
            throw ValidationException::withMessages(['text' => 'متن را بنویسید.']);
        }
        if ($source !== 'tts' && $source !== $task->source && ! $task->audio_path) {
            throw ValidationException::withMessages(['source' => 'برای صدای خودتان، از «عوض‌کردنِ صدا» ضبط کنید.']);
        }
        $old = $task->wantHash();
        $task->fill([
            'title' => $data['title'], 'text' => $text ?: null, 'pace' => $data['pace'] ?? $task->pace,
            'due_at' => $data['due_at'] ?? null, 'source' => $source,
            'score_type' => $data['score_type'] ?? $task->score_type, 'penalty' => $data['penalty'] ?? $task->penalty,
            'sentences' => $text !== '' ? array_map(fn ($s) => ['text' => $s, 'audio' => null], AudioTask::speechSentences($text)) : [],
        ]);
        $rebuild = $source === 'tts' && ($task->wantHash() !== $old || $task->getOriginal('source') !== 'tts');
        if ($rebuild && \App\Support\DbSchema::hasColumn('audio_tasks', 'audio_hash')) {
            $task->audio_hash = 'stale';   // صدای قبلی تا ساخته‌شدنِ صدای تازه پخش می‌شود
        }
        $task->save();

        return back()->with('flash', $rebuild ? '✏️ ذخیره شد؛ 🤖 صدای تازه الان ساخته می‌شود — بعد گوش بدهید و تأیید کنید.' : '✏️ ذخیره شد.');
    }

    /** 🔁 ساختِ دوباره‌ی صدای سیستم (اگر خواندنِ قبلی را نپسندید). */
    public function rebuild(Request $request, AudioTask $task, SpeechService $speech): RedirectResponse
    {
        $this->mine($request, $task);
        abort_unless($task->source === 'tts' && $task->sentences, 422);
        foreach ($task->sentences as $s) {
            $speech->forgetPcm((string) ($s['text'] ?? ''), $task->speechStyle());
        }
        $task->update(\App\Support\DbSchema::hasColumn('audio_tasks', 'audio_hash') ? ['audio_hash' => 'stale'] : ['audio_path' => null]);

        return back()->with('flash', '🔁 صدا دوباره ساخته می‌شود…');
    }

    /**
     * 🤖 ساختِ قدم‌به‌قدمِ صدای سیستم: هر درخواست چند جمله (در مهلتِ کوتاه)، صفحه پشتِ سرِ هم صدا می‌زند
     * تا همه ساخته شوند؛ بعد صدای پیوسته از جمله‌های ساخته‌شده چسبانده می‌شود (بی‌هزینه و سریع).
     */
    public function build(Request $request, AudioTask $task, SpeechService $speech): \Illuminate\Http\JsonResponse
    {
        $this->mine($request, $task);
        @set_time_limit(120);
        $list = array_values(array_column($task->sentences ?? [], 'text'));
        $total = count($list);
        if (! $task->needsBuild()) {
            return response()->json(['done' => true, 'built' => $total, 'total' => $total]);
        }
        if (! SpeechService::available()) {
            return response()->json(['done' => false, 'error' => 'سرویسِ صدای هوش مصنوعی تنظیم نشده یا خاموش است (مدیرِ کل: «مرکزِ هوش مصنوعی»).', 'built' => 0, 'total' => $total]);
        }
        $style = $task->speechStyle();
        $t0 = microtime(true);
        foreach ($list as $i => $sentence) {
            if ($speech->hasPcm($sentence, $style)) {
                continue;
            }
            // جمله‌ی تازه فقط اگر وقت باقی است (هر جمله تا چند ثانیه طول می‌کشد)
            if (microtime(true) - $t0 > 12) {
                break;
            }
            if ($speech->sentencePcm($sentence, $style) === null) {
                $built = count(array_filter($list, fn ($x) => $speech->hasPcm($x, $style)));

                return response()->json(['done' => false, 'built' => $built, 'total' => $total, 'failed' => $i + 1,
                    'error' => 'جمله‌ی ' . Jalali::fa((string) ($i + 1)) . ' ساخته نشد: ' . (SpeechService::$lastError ?: 'سرویسِ هوش مصنوعی پاسخ نداد.')]);
            }
        }
        $built = count(array_filter($list, fn ($x) => $speech->hasPcm($x, $style)));
        if ($built < $total) {
            return response()->json(['done' => false, 'built' => $built, 'total' => $total]);
        }
        // همه‌ی جمله‌ها آماده → یک صدای پیوسته با مکثِ سرِ هر نقطه
        $path = $speech->continuousPath($list, $style, $task->pace ?: 'normal');
        if (! $path) {
            return response()->json(['done' => false, 'built' => $built, 'total' => $total, 'error' => 'چسباندنِ صدای جمله‌ها نشد؛ دوباره تلاش کنید.']);
        }
        $copy = 'audio-tasks/tts-' . \Illuminate\Support\Str::random(24) . '.wav';
        if (! Storage::disk('public')->copy($path, $copy)) {
            return response()->json(['done' => false, 'built' => $built, 'total' => $total, 'error' => 'ذخیره‌ی فایلِ صدا نشد.']);
        }
        $oldPath = $task->audio_path;
        $task->update(['audio_path' => $copy] + (\App\Support\DbSchema::hasColumn('audio_tasks', 'audio_hash') ? ['audio_hash' => $task->wantHash()] : []));
        if ($oldPath && $oldPath !== $copy && str_starts_with($oldPath, 'audio-tasks/tts-')) {
            Storage::disk('public')->delete($oldPath);
        }

        return response()->json(['done' => true, 'built' => $total, 'total' => $total]);
    }

    /** عوض‌کردنِ صدای معلم (ضبطِ دوباره یا فایلِ تازه) بدونِ ساختنِ تکلیفِ تازه؛ پاسخ‌ها و نمره‌ها می‌مانند. */
    public function replaceAudio(Request $request, AudioTask $task): RedirectResponse
    {
        $this->mine($request, $task);
        $request->validate(['audio' => ['required', 'file', 'max:20480']], [
            'audio.required' => 'اول صدا را ضبط کنید یا فایل را انتخاب کنید.',
            'audio.uploaded' => 'فایل به سرور نرسید (خیلی بزرگ است)؛ کوتاه‌تر ضبط کنید.',
            'audio.max' => 'فایلِ صوتی حداکثر ۲۰ مگابایت.',
        ]);
        if (! $this->audioExtension($request->file('audio'), self::AUDIO_EXT)) {
            throw ValidationException::withMessages(['audio' => 'فرمتِ صوتی معتبر نیست (mp3، m4a، wav، ogg، webm).']);
        }
        $path = $this->storeAudio($request->file('audio'), 'audio-tasks', self::AUDIO_EXT);
        if (! $path) {
            throw ValidationException::withMessages(['audio' => 'ذخیره‌ی فایل انجام نشد؛ دوباره امتحان کنید.']);
        }
        if ($task->audio_path) {
            Storage::disk('public')->delete($task->audio_path);
        }
        $task->update(['audio_path' => $path, 'source' => $task->source === 'tts' ? 'voice' : $task->source]);

        return back()->with('flash', '✅ صدای تازه جایگزین شد؛ بچه‌ها از همین حالا صدای تازه را می‌شنوند.');
    }

    public function destroy(Request $request, AudioTask $task): RedirectResponse
    {
        $this->mine($request, $task);
        $disk = Storage::disk('public');
        foreach ($task->submissions as $s) {
            foreach ([$s->file_path, $s->marked_path] as $p) {
                if ($p) $disk->delete($p);
            }
        }
        if ($task->audio_path) {
            $disk->delete($task->audio_path);
        }
        $task->submissions()->delete();
        $task->delete();

        return redirect()->route('teacher.audio')->with('flash', 'حذف شد (نمره‌های ثبت‌شده در دفترِ نمره می‌مانند).');
    }

    public function show(Request $request, AudioTask $task): Response
    {
        $this->mine($request, $task);
        $room = Classroom::find($task->classroom_id);
        $subs = $task->submissions()->whereNotNull('file_path')->with('student:id,name')
            ->orderByRaw('COALESCE(submitted_at, updated_at) DESC')->get();
        $roster = $room ? $room->students()->orderBy('users.name')->get(['users.id', 'users.name']) : collect();
        $sent = $subs->pluck('student_id')->flip();

        return Inertia::render('Teacher/AudioTaskView', [
            'task' => $this->taskData($task) + ['classroom' => $room?->name, 'text' => $task->text],
            'submissions' => $subs->map(fn ($s) => $s->viewData() + ['student' => $s->student?->name, 'worksheet' => $task->title])->values(),
            'waiting' => $roster->reject(fn ($s) => isset($sent[$s->id]))->map(fn ($s) => $s->name)->values(),
            'grades' => AudioTask::GRADES,
            'ttsOn' => SpeechService::available(),
        ]);
    }

    /** داده‌ی پخش (مشترکِ معلم و دانش‌آموز) — بدونِ متنِ کامل برای املا. */
    public static function taskData(AudioTask $task, bool $forStudent = false): array
    {
        $disk = Storage::disk('public');
        $sentences = collect($task->sentences ?? [])->values()->map(fn ($s, $i) => [
            'i' => $i,
            'audio' => ! empty($s['audio']) ? $disk->url($s['audio']) : null,
            // متنِ جمله‌ی املا به دانش‌آموز داده نمی‌شود؛ فقط اگر صدای سرور نبود (صدای گوشی) از مسیرِ جدا گرفته می‌شود
            'text' => $forStudent && $task->kind === 'dictation' ? null : ($s['text'] ?? ''),
        ]);

        return [
            'id' => $task->id, 'kind' => $task->kind, 'title' => $task->title, 'source' => $task->source,
            // ?v= تا بعد از عوض‌کردنِ صدا، مرورگر نسخه‌ی کش‌شده‌ی قبلی را پخش نکند
            'audio' => $task->audio_path ? route('audio.task-audio', $task->id) . '?v=' . ($task->updated_at?->timestamp ?? 0) : null,
            // ضبطِ قدیمیِ webm/ogg روی آیفون و برخی گوشی‌ها پخش نمی‌شود → به معلم پیشنهادِ ضبطِ دوباره
            'audio_legacy' => $task->audio_path && in_array(strtolower(pathinfo($task->audio_path, PATHINFO_EXTENSION)), ['webm', 'ogg', 'oga'], true),
            'sentences' => $sentences->all(),
            'reading_text' => $task->kind === 'reading' ? $task->text : null,
            'score_type' => $task->score_type, 'penalty' => (float) $task->penalty,
            'published' => (bool) $task->is_published,
            'needs_build' => $task->needsBuild(),
            'ready' => $task->audioReady(),
            'publish_at' => $task->publish_at && ! $task->is_published ? $task->publish_at->format('Y-m-d H:i') : null,
            'publish_at_fa' => $task->publish_at && ! $task->is_published ? Jalali::format($task->publish_at, true) . ' ساعت ' . Jalali::fa($task->publish_at->format('H:i')) : null,
            'pace' => $task->pace ?: 'normal',
            'due_at' => $task->due_at?->format('Y-m-d H:i'),
            'due' => $task->due_at ? Jalali::format($task->due_at, true) : null,
            'date' => Jalali::format($task->created_at),
        ];
    }
}
