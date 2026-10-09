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
        ], [
            'title.required' => 'عنوان را بنویسید.',
            'audio.max' => 'فایلِ صوتی حداکثر ۲۰ مگابایت.',
        ]);
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
            if (! $this->extensionSafe($request->file('audio'), self::AUDIO_EXT)) {
                throw ValidationException::withMessages(['audio' => 'فرمتِ صوتی معتبر نیست (mp3، m4a، wav، ogg، webm).']);
            }
            $audioPath = $this->storeUpload($request->file('audio'), 'audio-tasks') ?: null;
        }

        // متن → صدای فارسیِ سرور، جمله‌به‌جمله (برای املا آهسته و کلمه‌به‌کلمه)
        $sentences = [];
        if ($text !== '') {
            foreach (AudioTask::splitSentences($text) as $s) {
                $sentences[] = ['text' => $s, 'audio' => $data['source'] === 'tts'
                    ? rescue(fn () => $speech->pathFor($s, $data['kind'] === 'dictation' ? 'dictation' : 'reading'), null, true) : null];
            }
        }
        $missing = $data['source'] === 'tts' ? collect($sentences)->whereNull('audio')->count() : 0;

        $task = AudioTask::create([
            'school_id' => $teacher->school_id, 'teacher_id' => $teacher->id, 'classroom_id' => $room->id,
            'kind' => $data['kind'], 'title' => $data['title'], 'grade' => $room->grade,
            'subject' => $data['kind'] === 'dictation' ? 'املا' : 'فارسی',
            'text' => $text ?: null, 'sentences' => $sentences, 'source' => $data['source'], 'audio_path' => $audioPath,
            'score_type' => $data['score_type'], 'penalty' => $data['penalty'] ?? 0.5,
            'due_at' => $data['due_at'] ?? null,
        ]);
        if (! empty($data['publish'])) {
            $this->doPublish($task);
        }

        $msg = '✅ «' . $task->title . '» ساخته شد' . (! empty($data['publish']) ? ' و برای کلاس فرستاده شد' : ' (پیش‌نویس)');
        if ($missing) {
            $msg .= ' — ⚠️ صدای فارسیِ سرور برای ' . Jalali::fa((string) $missing) . ' جمله ساخته نشد؛ دانش‌آموزان با صدای گوشیِ خودشان می‌شنوند. برای صدای بهتر، صدای خودتان را ضبط کنید یا کلیدِ صدا را در «مرکزِ هوش مصنوعی» ثبت کنید.';
        }

        return redirect()->route('teacher.audio.show', $task)->with('flash', $msg);
    }

    private function doPublish(AudioTask $task): void
    {
        $first = ! $task->published_at;
        $task->update(['is_published' => true, 'published_at' => $task->published_at ?? now()]);
        if (! $first) {
            return;
        }
        rescue(function () use ($task) {
            $ids = Classroom::find($task->classroom_id)?->students()->pluck('users.id')->all() ?? [];
            if (! $ids) {
                return;
            }
            $ann = Announcement::create([
                'school_id' => $task->school_id, 'sender_id' => $task->teacher_id, 'audience' => 'personal',
                'title' => ($task->kind === 'dictation' ? '📝 املای صوتیِ تازه — ' : '🎙️ روخوانیِ تازه — ') . $task->title,
                'body' => $task->kind === 'dictation'
                    ? 'یک برگه و مداد بردار، گوش بده و بنویس؛ بعد عکسش را برای معلم بفرست.'
                    : 'متن را با صدای بلند بخوان و صدایت را برای معلم بفرست.',
                'link' => route('listen.show', $task->id, false),
            ]);
            $ann->recipients()->sync($ids);
        }, null, true);
    }

    public function publish(Request $request, AudioTask $task): RedirectResponse
    {
        $this->mine($request, $task);
        $task->is_published ? $task->update(['is_published' => false]) : $this->doPublish($task);

        return back()->with('flash', $task->fresh()->is_published ? '📣 برای کلاس فرستاده شد' : '⏸️ از دیدِ دانش‌آموزان پنهان شد');
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
        if (! $this->extensionSafe($request->file('audio'), self::AUDIO_EXT)) {
            throw ValidationException::withMessages(['audio' => 'فرمتِ صوتی معتبر نیست (mp3، m4a، wav، ogg، webm).']);
        }
        $path = $this->storeUpload($request->file('audio'), 'audio-tasks');
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
            'due' => $task->due_at ? Jalali::format($task->due_at, true) : null,
            'date' => Jalali::format($task->created_at),
        ];
    }
}
