<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StoresUploads;
use App\Http\Controllers\Teacher\AudioTaskController;
use App\Models\Announcement;
use App\Models\AudioSubmission;
use App\Models\AudioTask;
use App\Models\Classroom;
use App\Models\Grade;
use App\Models\GradeColumn;
use App\Models\User;
use App\Services\GamificationService;
use App\Support\Jalali;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** «🎧 املا و روخوانی»: صفحه‌ی دانش‌آموز، ارسال، فایل‌ها (از مسیرِ سایت) و تصحیحِ معلم. */
class AudioSubmissionController extends Controller
{
    use StoresUploads;

    public const SUBMIT_XP = 5;

    private function inClass(User $user, AudioTask $task): bool
    {
        return $task->is_published && $task->classroom_id
            && $user->classrooms()->where('classrooms.id', $task->classroom_id)->exists();
    }

    private function teaches(User $user, AudioTask $task): bool
    {
        return $task->teacher_id === $user->id
            || ($user->hasRole(\App\Support\Roles::SCHOOL_ADMIN) && $task->school_id === $user->school_id)
            || $user->hasRole(\App\Support\Roles::SUPER_ADMIN);
    }

    /* ─────────────── دانش‌آموز ─────────────── */

    public function index(Request $request): Response
    {
        $user = $request->user();
        $ids = $user->classrooms()->pluck('classrooms.id');
        $tasks = AudioTask::ready() ? AudioTask::whereIn('classroom_id', $ids)->where('is_published', true)->latest('published_at')->get() : collect();
        $mine = $tasks->isEmpty() ? collect() : AudioSubmission::where('student_id', $user->id)->whereIn('audio_task_id', $tasks->pluck('id'))->get()->keyBy('audio_task_id');

        return Inertia::render('Student/AudioTasks', [
            'tasks' => $tasks->map(function ($t) use ($mine) {
                $s = $mine[$t->id] ?? null;

                return [
                    'id' => $t->id, 'kind' => $t->kind, 'title' => $t->title,
                    'date' => Jalali::format($t->published_at ?? $t->created_at),
                    'due' => $t->due_at ? Jalali::format($t->due_at, true) : null,
                    'overdue' => $t->due_at && $t->due_at->isPast() && ! ($s?->file_path),
                    'status' => ! $s?->file_path ? 'todo' : ($s->graded_at ? 'graded' : 'sent'),
                    'grade' => $s?->grade, 'score' => $s?->score,
                ];
            })->values(),
        ]);
    }

    public function show(Request $request, AudioTask $task): Response
    {
        $user = $request->user();
        abort_unless($this->inClass($user, $task), 403);
        $mine = AudioSubmission::where('audio_task_id', $task->id)->where('student_id', $user->id)->first();

        return Inertia::render('Student/AudioTaskPlay', [
            'task' => AudioTaskController::taskData($task, true),
            'submitted' => $mine?->file_path ? $mine->viewData() + ['worksheet' => $task->title] : null,
            'plays' => (int) ($mine?->plays ?? 0),
            'submitXp' => self::SUBMIT_XP,
        ]);
    }

    /** شمارشِ شنیدن (برای معلم: چند بار گوش داده). */
    public function played(Request $request, AudioTask $task): JsonResponse
    {
        abort_unless($this->inClass($request->user(), $task), 403);
        $sub = AudioSubmission::firstOrCreate(['audio_task_id' => $task->id, 'student_id' => $request->user()->id]);
        $sub->increment('plays');

        return response()->json(['plays' => $sub->plays]);
    }

    /** وقتی صدای سرور برای یک جمله نیست: فقط همان جمله برای صدای گوشی (بقیه‌ی متن فرستاده نمی‌شود). */
    public function sentence(Request $request, AudioTask $task, int $i): JsonResponse
    {
        abort_unless($this->inClass($request->user(), $task) || $this->teaches($request->user(), $task), 403);
        $s = ($task->sentences ?? [])[$i] ?? null;
        abort_unless($s, 404);

        return response()->json(['text' => $s['text'] ?? '']);
    }

    public function submit(Request $request, AudioTask $task, GamificationService $game): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->inClass($user, $task), 403);
        $request->validate(['file' => ['required', 'file', 'max:20480'], 'note' => ['nullable', 'string', 'max:300']], [
            'file.required' => $task->kind === 'dictation' ? 'عکسِ برگه‌ی املا را انتخاب کن.' : 'اول صدایت را ضبط کن.',
            'file.uploaded' => 'فایل به سرور نرسید (خیلی بزرگ است)؛ دوباره امتحان کن.',
        ]);
        $file = $request->file('file');
        $allowed = $task->kind === 'dictation' ? ['jpg', 'jpeg', 'png', 'webp', 'pdf'] : AudioTaskController::AUDIO_EXT;
        if (! $this->extensionSafe($file, $allowed)) {
            throw ValidationException::withMessages(['file' => $task->kind === 'dictation' ? 'فقط عکس (jpg/png/webp) یا PDF.' : 'فرمتِ صدا معتبر نیست.']);
        }
        $sub = AudioSubmission::firstOrNew(['audio_task_id' => $task->id, 'student_id' => $user->id]);
        $disk = Storage::disk('public');
        if ($sub->file_path) {
            $disk->delete($sub->file_path);
        }
        $ext = strtolower($file->getClientOriginalExtension());
        $sub->file_path = $this->storeUpload($file, 'audio-submissions') ?: null;
        $sub->file_kind = $task->kind === 'reading' ? 'audio' : ($ext === 'pdf' ? 'pdf' : 'image');
        $sub->note = $request->input('note');
        $sub->submitted_at = now();
        // نسخه‌ی تازه بعد از تصحیح → دوباره «منتظرِ تصحیح» (نمره‌ی قبلی در دفتر می‌ماند تا معلم دوباره ببیند)
        if ($sub->graded_at) {
            if ($sub->marked_path) {
                $disk->delete($sub->marked_path);
            }
            $sub->marked_path = null;
            $sub->graded_at = null;
        }
        $sub->save();

        $xp = false;
        if (! $sub->submit_xp) {
            rescue(function () use ($game, $user, $task, $sub, &$xp) {
                $game->award($user, self::SUBMIT_XP, ($task->kind === 'dictation' ? '📝 ارسالِ املا — ' : '🎙️ ارسالِ روخوانی — ') . $task->title,
                    null, AudioSubmission::class, $sub->id);
                $sub->update(['submit_xp' => true]);
                $xp = true;
            }, null, true);
        }

        return back()->with('flash', '✅ برای معلم فرستاده شد' . ($xp ? ' و +' . Jalali::fa((string) self::SUBMIT_XP) . ' امتیاز گرفتی' : ''));
    }

    /* ─────────────── فایل‌ها ─────────────── */

    public function file(Request $request, AudioSubmission $submission, string $which = 'file'): BinaryFileResponse
    {
        $user = $request->user();
        $task = $submission->task;
        abort_unless($task && ($submission->student_id === $user->id || $this->teaches($user, $task)), 403);
        $path = $which === 'marked' ? $submission->marked_path : $submission->file_path;
        abort_unless($path && Storage::disk('public')->exists($path), 404, 'فایل روی سرور پیدا نشد.');

        return response()->file(Storage::disk('public')->path($path), ['Cache-Control' => 'private, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** صدای ضبط‌شده/بارگذاری‌شده‌ی معلم. */
    public function taskAudio(Request $request, AudioTask $task): BinaryFileResponse
    {
        abort_unless($this->inClass($request->user(), $task) || $this->teaches($request->user(), $task), 403);
        abort_unless($task->audio_path && Storage::disk('public')->exists($task->audio_path), 404, 'فایلِ صوتی پیدا نشد.');

        return response()->file(Storage::disk('public')->path($task->audio_path), ['Cache-Control' => 'private, max-age=86400']);
    }

    /* ─────────────── تصحیحِ معلم ─────────────── */

    public function grade(Request $request, AudioSubmission $submission, GamificationService $game): JsonResponse
    {
        $teacher = $request->user();
        $submission->load('task', 'student');
        $task = $submission->task;
        abort_unless($task && $this->teaches($teacher, $task), 403);

        $data = $request->validate([
            'grade' => ['nullable', 'string', Rule::in(AudioTask::GRADES)],
            'score' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'mistakes' => ['nullable', 'integer', 'min:0', 'max:200'],
            'feedback' => ['nullable', 'string', 'max:500'],
            'marked' => ['nullable', 'file', 'max:8192'],
        ]);
        if ($task->score_type === 'numeric' && ! isset($data['score'])) {
            return response()->json(['message' => 'نمره‌ی عددی (از ۲۰) را وارد کنید.'], 422);
        }
        if ($task->score_type === 'descriptive' && empty($data['grade'])) {
            return response()->json(['message' => 'یکی از ارزشیابی‌ها (خیلی خوب … نیاز به تلاش) را انتخاب کنید.'], 422);
        }

        try {
            $disk = Storage::disk('public');
            if ($request->hasFile('marked') && $this->extensionSafe($request->file('marked'), ['jpg', 'jpeg', 'png', 'webp'])) {
                $new = $this->storeUpload($request->file('marked'), 'audio-marked');
                if ($new) {
                    if ($submission->marked_path) {
                        $disk->delete($submission->marked_path);
                    }
                    $submission->marked_path = $new;
                }
            }
            $submission->fill([
                'grade' => $data['grade'] ?? null, 'score' => $data['score'] ?? null, 'mistakes' => $data['mistakes'] ?? null,
                'feedback' => $data['feedback'] ?? null, 'graded_at' => now(), 'graded_by' => $teacher->id,
            ])->save();

            // دفترِ نمره: یک ستون برای همین تکلیف؛ نمره‌ی هر دانش‌آموز در آن (و امتیازش طبقِ قاعده‌ی دفتر)
            $col = $this->column($task);
            if ($col) {
                $g = Grade::updateOrCreate(['grade_column_id' => $col->id, 'student_id' => $submission->student_id], [
                    'score' => $task->score_type === 'numeric' ? $data['score'] : null,
                    'text' => $task->score_type === 'descriptive' ? $data['grade'] : null,
                    'feedback' => mb_substr(trim(($data['mistakes'] ?? null) !== null ? 'تعدادِ غلط: ' . $data['mistakes'] . '. ' : '') . ($data['feedback'] ?? ''), 0, 255) ?: null,
                ]);
                $game->awardForGrade($g, $col, $teacher);
                \App\Services\MasteryService::forgetMany([$submission->student_id]);
                rescue(fn () => \App\Services\LearningService::fromGradeColumn($col->fresh()), null, true);
            }

            rescue(function () use ($submission, $task, $teacher, $data) {
                if (! $submission->student?->school_id) {
                    return;
                }
                $value = $task->score_type === 'numeric' ? Jalali::fa((string) $data['score']) . ' از ۲۰' : $data['grade'];
                $ann = Announcement::create([
                    'school_id' => $submission->student->school_id, 'sender_id' => $teacher->id, 'audience' => 'personal',
                    'title' => ($task->kind === 'dictation' ? '📝 املایت تصحیح شد — ' : '🎙️ روخوانی‌ات بررسی شد — ') . $task->title,
                    'body' => 'نمره: ' . $value . (($data['mistakes'] ?? null) !== null ? ' — تعدادِ غلط: ' . Jalali::fa((string) $data['mistakes']) : '')
                        . (! empty($data['feedback']) ? "\n💬 " . $data['feedback'] : ''),
                    'link' => route('listen.show', $task->id, false),
                ]);
                $ann->recipients()->sync([$submission->student_id]);
            }, null, true);

            return response()->json(['ok' => true, 'submission' => $submission->fresh()->viewData() + ['student' => $submission->student?->name, 'worksheet' => $task->title]]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'ثبتِ نمره انجام نشد: ' . \App\Http\Controllers\Concerns\FriendlySaveErrors::explainError($e)
                . ' [' . class_basename($e) . ': ' . mb_substr($e->getMessage(), 0, 200) . ']'], 500);
        }
    }

    /** ستونِ دفترِ نمره‌ی این تکلیف (یک‌بار ساخته می‌شود). */
    private function column(AudioTask $task): ?GradeColumn
    {
        if ($task->grade_column_id && ($c = GradeColumn::withoutGlobalScopes()->find($task->grade_column_id))) {
            return $c;
        }
        if (! $task->classroom_id) {
            return null;
        }
        $type = $task->score_type === 'numeric' ? 'numeric' : 'descriptive';
        $col = GradeColumn::create([
            'school_id' => $task->school_id, 'classroom_id' => $task->classroom_id, 'teacher_id' => $task->teacher_id,
            'title' => ($task->kind === 'dictation' ? '📝 املا: ' : '🎙️ روخوانی: ') . mb_substr($task->title, 0, 80),
            'type' => $type, 'score_type' => $type,
            'lesson' => $task->kind === 'dictation' ? 'املا' : 'فارسی', 'topic' => $task->kind === 'dictation' ? 'املای صوتی' : 'روخوانی',
            'max' => 20, 'graded_at' => now(),
        ]);
        $task->update(['grade_column_id' => $col->id]);

        return $col;
    }
}
