<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Worksheet;
use App\Models\WorksheetSubmission;
use App\Support\Jalali;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/** کاربرگِ دانش‌آموز — مشاهده/چاپ + ارسالِ کاربرگِ پرشده (عکس/فایل) به معلم. */
class StudentWorksheetController extends Controller
{
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
                'html' => $worksheet->render_html,
                'image' => $worksheet->image_path ? Storage::disk('public')->url($worksheet->image_path) : null,
            ],
            'submitted' => $mine ? [
                'url' => Storage::disk('public')->url($mine->file_path),
                'note' => $mine->note, 'date' => Jalali::format($mine->submitted_at ?? $mine->created_at, true),
            ] : null,
        ]);
    }

    /** ارسالِ کاربرگِ پرشده (عکس یا فایل). */
    public function submit(Request $request, Worksheet $worksheet): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->accessible($user, $worksheet), 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:12288', 'mimes:jpg,jpeg,png,webp,pdf'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        // جایگزینیِ ارسالِ قبلی
        $prev = WorksheetSubmission::where('worksheet_id', $worksheet->id)->where('student_id', $user->id)->first();
        if ($prev && $prev->file_path) {
            Storage::disk('public')->delete($prev->file_path);
        }

        $path = $request->file('file')->store('worksheet-submissions', 'public');

        WorksheetSubmission::updateOrCreate(
            ['worksheet_id' => $worksheet->id, 'student_id' => $user->id],
            ['file_path' => $path, 'note' => $data['note'] ?? null, 'submitted_at' => now()]
        );

        return back()->with('flash', 'کاربرگِ پرشده برای معلم ارسال شد ✅');
    }
}
