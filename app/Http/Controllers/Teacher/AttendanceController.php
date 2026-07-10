<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\Classroom;
use App\Services\GamificationService;
use App\Support\Jalali;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** حضور و غیاب کلاسی معلم. */
class AttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        $teacher = $request->user();
        $classroom = Classroom::where('teacher_id', $teacher->id)->first();

        $date = $request->query('date') ?: now()->toDateString();
        try { $carbon = Carbon::parse($date); } catch (\Throwable $e) { $carbon = now(); $date = now()->toDateString(); }

        $students = collect();
        $recentDates = collect();

        if ($classroom) {
            // وضعیت این تاریخ
            $today = AttendanceRecord::where('classroom_id', $classroom->id)
                ->whereDate('date', $date)->get()->keyBy('student_id');

            // آمار کل هر دانش‌آموز
            $stats = AttendanceRecord::where('classroom_id', $classroom->id)
                ->selectRaw('student_id, status, COUNT(*) as c')->groupBy('student_id', 'status')->get()
                ->groupBy('student_id');

            $students = $classroom->students()->get(['users.id', 'name'])->map(function ($s) use ($today, $stats) {
                $st = $stats->get($s->id) ?? collect();
                return [
                    'id' => $s->id, 'name' => $s->name,
                    'status' => $today->get($s->id)?->status ?? 'present',
                    'note'   => $today->get($s->id)?->note,
                    'absent' => (int) ($st->firstWhere('status', 'absent')->c ?? 0),
                    'late'   => (int) ($st->firstWhere('status', 'late')->c ?? 0),
                ];
            })->values();

            // تاریخ‌های اخیرِ ثبت‌شده
            $recentDates = AttendanceRecord::where('classroom_id', $classroom->id)
                ->selectRaw('date, SUM(status="absent") as absents, COUNT(*) as total')
                ->groupBy('date')->orderByDesc('date')->limit(14)->get()
                ->map(fn ($r) => [
                    'date' => Carbon::parse($r->date)->toDateString(),
                    'jdate' => Jalali::format(Carbon::parse($r->date)),
                    'absents' => (int) $r->absents, 'total' => (int) $r->total,
                ]);
        }

        return Inertia::render('Teacher/Attendance', [
            'classroom'   => $classroom?->only('id', 'name', 'grade'),
            'students'    => $students,
            'date'        => $date,
            'jdate'       => Jalali::format($carbon, true),
            'recentDates' => $recentDates,
        ]);
    }

    public function store(Request $request, GamificationService $game): RedirectResponse
    {
        $teacher = $request->user();
        $classroom = Classroom::where('teacher_id', $teacher->id)->firstOrFail();

        $data = $request->validate([
            'date'              => ['required', 'date'],
            'records'           => ['required', 'array'],
            'records.*.student_id' => ['required', 'integer'],
            'records.*.status'  => ['required', 'in:present,absent,late,excused'],
            'records.*.note'    => ['nullable', 'string', 'max:200'],
        ]);

        $studentIds = $classroom->students()->pluck('users.id')->all();
        $date = Carbon::parse($data['date'])->toDateString();
        $jdate = Jalali::format(Carbon::parse($date));

        foreach ($data['records'] as $r) {
            if (! in_array($r['student_id'], $studentIds, true)) {
                continue;
            }
            $record = AttendanceRecord::updateOrCreate(
                ['classroom_id' => $classroom->id, 'student_id' => $r['student_id'], 'date' => $date],
                ['school_id' => $teacher->school_id, 'status' => $r['status'], 'note' => $r['note'] ?? null, 'recorded_by' => $teacher->id]
            );

            // موتور واحد XP: امتیاز حضور و غیاب (idempotent؛ فقط هنگام ثبت یا تغییر وضعیت)
            if ($record->wasRecentlyCreated || $record->wasChanged('status')) {
                $game->awardForAttendance($record, $teacher);
            }

            // اعلان غیبت به دانش‌آموز (بدون تکرار برای همان روز)
            if (in_array($r['status'], ['absent', 'late'], true)) {
                $title = ($r['status'] === 'absent' ? 'ثبت غیبت' : 'ثبت تأخیر') . ' — ' . $jdate;
                $exists = Announcement::where('school_id', $teacher->school_id)
                    ->where('title', $title)->where('audience', 'personal')
                    ->whereHas('recipients', fn ($q) => $q->where('users.id', $r['student_id']))->exists();
                if (! $exists) {
                    $ann = Announcement::create([
                        'school_id' => $teacher->school_id, 'sender_id' => $teacher->id,
                        'title' => $title, 'audience' => 'personal',
                        'body' => $r['status'] === 'absent'
                            ? "امروز ({$jdate}) در کلاس غایب بودی. در صورت موجه‌بودن، با مدرسه هماهنگ کن."
                            : "امروز ({$jdate}) با تأخیر وارد کلاس شدی. لطفاً سر وقت حاضر شو.",
                    ]);
                    $ann->recipients()->sync([$r['student_id']]);
                }
            }
        }

        return back()->with('flash', 'حضور و غیاب ثبت شد ✅');
    }
}
