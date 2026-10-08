<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StoresUploads;
use App\Models\Announcement;
use App\Models\User;
use App\Models\WorksheetSubmission;
use App\Models\XpEntry;
use App\Services\GamificationService;
use App\Support\WorksheetAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * کاربرگِ پرشده‌ی دانش‌آموز: نمایشِ فایل از مسیرِ خودِ سایت و تصحیحِ معلم.
 *
 * فایل‌ها قبلاً با لینکِ مستقیمِ /storage باز می‌شدند: روی بعضی هاست‌ها «403 Forbidden» می‌داد
 * (دسترسیِ فایل/حفاظتِ هات‌لینک) و در اپ (حالتِ تمام‌صفحه) راهی برای برگشت نبود.
 * حالا فایل را خودِ لاراول و فقط برای کسانی که اجازه دارند می‌فرستد (کارِ بچه‌ها عمومی نیست).
 */
class WorksheetSubmissionController extends Controller
{
    use StoresUploads;

    private function canGrade(User $user, WorksheetSubmission $sub): bool
    {
        $w = $sub->worksheet;
        if ($w && WorksheetAccess::canEdit($user, $w)) {
            return true;
        }

        // معلمِ کلاسِ همان دانش‌آموز
        return $user->hasRole(\App\Support\Roles::TEACHER)
            && \App\Models\Classroom::where('teacher_id', $user->id)
                ->whereHas('students', fn ($q) => $q->where('users.id', $sub->student_id))->exists();
    }

    public function file(Request $request, WorksheetSubmission $submission, string $which = 'file'): BinaryFileResponse
    {
        $user = $request->user();
        abort_unless($submission->student_id === $user->id || $this->canGrade($user, $submission), 403);
        $path = $which === 'marked' ? $submission->marked_path : $submission->file_path;
        abort_unless($path, 404);
        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404, 'فایل روی سرور پیدا نشد.');

        return response()->file($disk->path($path), [
            'Cache-Control' => 'private, max-age=86400',
            'Content-Disposition' => 'inline; filename="worksheet-' . $submission->id . '.' . pathinfo($path, PATHINFO_EXTENSION) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** ثبتِ تصحیح: نمره‌ی توصیفی + امتیاز + توضیح + (اختیاری) عکسِ علامت‌خورده. */
    public function grade(Request $request, WorksheetSubmission $submission, GamificationService $game): JsonResponse
    {
        $teacher = $request->user();
        $submission->load('worksheet', 'student');
        abort_unless($this->canGrade($teacher, $submission), 403);

        $data = $request->validate([
            'grade' => ['nullable', 'string', Rule::in(array_keys(WorksheetSubmission::GRADES))],
            'xp' => ['required', 'integer', 'min:0', 'max:' . WorksheetSubmission::MAX_XP],
            'feedback' => ['nullable', 'string', 'max:500'],
            'marked' => ['nullable', 'file', 'max:8192'],
            'clear_marks' => ['nullable', 'boolean'],
        ], [
            'xp.max' => 'امتیازِ تصحیح حداکثر ' . WorksheetSubmission::MAX_XP . ' است.',
        ]);

        $disk = Storage::disk('public');
        if ($request->hasFile('marked')) {
            if (! $this->extensionAllowed($request->file('marked'), ['jpg', 'jpeg', 'png', 'webp'])) {
                return response()->json(['message' => 'فرمتِ تصویرِ تصحیح معتبر نیست.'], 422);
            }
            $new = $this->storeUpload($request->file('marked'), 'worksheet-marked');
            if ($new) {
                if ($submission->marked_path) {
                    $disk->delete($submission->marked_path);
                }
                $submission->marked_path = $new;
            }
        } elseif (! empty($data['clear_marks']) && $submission->marked_path) {
            $disk->delete($submission->marked_path);
            $submission->marked_path = null;
        }

        $first = ! $submission->graded_at;
        $xp = (int) $data['xp'];
        $submission->fill([
            'grade' => $data['grade'] ?? null, 'grade_xp' => $xp, 'feedback' => $data['feedback'] ?? null,
            'graded_at' => now(), 'graded_by' => $teacher->id,
        ])->save();

        // امتیازِ تصحیح: یک ردیف برای هر کاربرگ؛ تصحیحِ دوباره همان را عوض می‌کند (دو بار داده نمی‌شود)
        $entry = XpEntry::where('source_type', WorksheetSubmission::GRADE_SOURCE)->where('source_id', $submission->id)->first();
        $title = $submission->worksheet?->title ?: 'کاربرگ';
        if ($xp > 0 && $submission->student) {
            $entry
                ? $entry->update(['amount' => $xp, 'reason' => '✍️ تصحیحِ کاربرگ — ' . $title . ($data['grade'] ? ' (' . $data['grade'] . ')' : '')])
                : $game->award($submission->student, $xp, '✍️ تصحیحِ کاربرگ — ' . $title . ($data['grade'] ? ' (' . $data['grade'] . ')' : ''),
                    $teacher, WorksheetSubmission::GRADE_SOURCE, $submission->id);
        } elseif ($entry) {
            $entry->delete();
        }

        // اعلان برای خودِ دانش‌آموز
        if ($submission->student?->school_id) {
            rescue(function () use ($submission, $teacher, $title, $xp, $data, $first) {
                $ann = Announcement::create([
                    'school_id' => $submission->student->school_id, 'sender_id' => $teacher->id, 'audience' => 'personal',
                    'title' => ($first ? '✍️ کاربرگت تصحیح شد — ' : '✍️ تصحیحِ کاربرگت به‌روز شد — ') . mb_substr($title, 0, 120),
                    'body' => trim(($data['grade'] ? 'نمره: ' . $data['grade'] . ' — ' : '') . ($xp > 0 ? '+' . \App\Support\Jalali::fa((string) $xp) . ' امتیاز. ' : '')
                        . ($data['feedback'] ?? '')),
                    'link' => route('my.worksheet', $submission->worksheet_id, false),
                ]);
                $ann->recipients()->sync([$submission->student_id]);
            }, null, true);
        }

        return response()->json(['ok' => true, 'submission' => $submission->fresh()->viewData() + ['student' => $submission->student?->name]]);
    }
}
