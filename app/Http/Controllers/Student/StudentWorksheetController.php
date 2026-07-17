<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Worksheet;
use App\Models\WorksheetSubmission;
use App\Services\GamificationService;
use App\Support\Jalali;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/** کاربرگِ دانش‌آموز — مشاهده/چاپ/دانلود (امتیازِ یک‌بار) + ارسالِ کاربرگِ پرشده (امتیازِ یک‌بار). */
class StudentWorksheetController extends Controller
{
    private const DOWNLOAD_XP = 5;
    private const SUBMIT_XP = 15;

    /** آیا این کاربرگ برای کلاسِ دانش‌آموز منتشر شده؟ */
    private function accessible($user, Worksheet $worksheet): bool
    {
        if (! $worksheet->is_published) {
            return false;
        }
        $classroomIds = $user->classrooms()->pluck('classrooms.id')->all();
        return $worksheet->classroom_id === null || in_array($worksheet->classroom_id, $classroomIds, true);
    }

    public function show(Request $request, Worksheet $worksheet): Response
    {
        $user = $request->user();
        abort_unless($this->accessible($user, $worksheet), 403);

        $mine = WorksheetSubmission::where('worksheet_id', $worksheet->id)->where('student_id', $user->id)->first();

        return Inertia::render('Student/WorksheetView', [
            'worksheet' => [
                'id' => $worksheet->id, 'title' => $worksheet->title,
                'mode' => $worksheet->mode ?? 'manual',
                'html' => $worksheet->render_html,
                'image' => $worksheet->image_path ? Storage::disk('public')->url($worksheet->image_path) : null,
                'file' => $worksheet->file_path ? Storage::disk('public')->url($worksheet->file_path) : null,
            ],
            'downloadXp' => self::DOWNLOAD_XP,
            'submitXp' => self::SUBMIT_XP,
            'downloaded' => $mine ? (bool) $mine->download_xp : false,
            'submitted' => $mine && $mine->file_path ? [
                'url' => Storage::disk('public')->url($mine->file_path),
                'note' => $mine->note, 'date' => Jalali::format($mine->submitted_at ?? $mine->created_at, true),
            ] : null,
        ]);
    }

    /** ثبتِ دانلود/مشاهده — امتیازِ یک‌بار (بدون نیاز به ارسال). best-effort. */
    public function download(Request $request, Worksheet $worksheet, GamificationService $game): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->accessible($user, $worksheet), 403);

        $gained = 0;
        try {
            $sub = WorksheetSubmission::firstOrNew(
                ['worksheet_id' => $worksheet->id, 'student_id' => $user->id]
            );
            if (! $sub->download_xp) {
                $game->award($user, self::DOWNLOAD_XP, '🎨 دریافتِ کاربرگ — ' . $worksheet->title,
                    $worksheet->teacher, Worksheet::class, $worksheet->id);
                $sub->download_xp = true;
                $sub->downloaded_at = now();
                $sub->save();
                $gained = self::DOWNLOAD_XP;
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('worksheet download xp failed: ' . $e->getMessage());
        }

        return response()->json(['ok' => true, 'gained' => $gained]);
    }

    /** ارسالِ کاربرگِ پرشده (عکس یا فایل) — امتیازِ یک‌بار. */
    public function submit(Request $request, Worksheet $worksheet, GamificationService $game): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->accessible($user, $worksheet), 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:12288', 'mimes:jpg,jpeg,png,webp,pdf'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        // جایگزینیِ ارسالِ قبلی — file_path پیش از اولین ذخیره ست می‌شود (سازگار با نسخه‌ی قدیمِ NOT NULL)
        $sub = WorksheetSubmission::firstOrNew(
            ['worksheet_id' => $worksheet->id, 'student_id' => $user->id]
        );
        if ($sub->file_path) {
            Storage::disk('public')->delete($sub->file_path);
        }

        $sub->file_path = $request->file('file')->store('worksheet-submissions', 'public');
        $sub->note = $data['note'] ?? null;
        $sub->submitted_at = now();
        $sub->save();

        // امتیازِ ارسال (یک‌بار) — best-effort؛ اگر ستون‌های v18 نبودند آپلود نمی‌شکند
        $awarded = false;
        try {
            if (! $sub->submit_xp) {
                $game->award($user, self::SUBMIT_XP, '🎨 ارسالِ کاربرگ — ' . $worksheet->title,
                    $worksheet->teacher, WorksheetSubmission::class, $sub->id);
                $sub->submit_xp = true;
                $sub->save();
                $awarded = true;
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('worksheet submit xp failed: ' . $e->getMessage());
        }

        return back()->with('flash', $awarded
            ? "کاربرگِ پرشده ارسال شد و +" . self::SUBMIT_XP . " امتیاز گرفتی ✅"
            : 'کاربرگِ پرشده ارسال شد ✅');
    }
}
