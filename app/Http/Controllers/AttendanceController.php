<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\Classroom;
use App\Models\XpEntry;
use App\Services\GamificationService;
use App\Support\Jalali;
use App\Support\Roles;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ثبت حضور و غیاب — مشترک بین معلم (کلاس خودش) و مدیر مدرسه (همه‌ی کلاس‌ها).
 * شامل ثبت/ویرایش یک روز و حذف رکوردهای یک روز (برای یک دانش‌آموز یا همه).
 */
class AttendanceController extends Controller
{
    /** آیا کاربر معلم است؟ */
    private function isTeacher(Request $request): bool
    {
        return $request->user()->hasRole(Roles::TEACHER);
    }

    /** نام مسیرها بر اساس نقش (برای فرانت). */
    private function routeNames(Request $request): array
    {
        $p = $this->isTeacher($request) ? 'teacher' : 'school';
        return [
            'index'        => "$p.attendance",
            'store'        => "$p.attendance.store",
            'destroyDay'   => "$p.attendance.day.destroy",
            'destroyOne'   => "$p.attendance.one.destroy",
            'report'       => "$p.attendance.report",
            'monthlySheet' => "$p.attendance.monthly",
        ];
    }

    /** کلاس‌های در دسترس کاربر. */
    private function classrooms(Request $request)
    {
        $user = $request->user();
        $q = $this->isTeacher($request)
            ? Classroom::where('teacher_id', $user->id)
            : Classroom::query(); // مدیر: BelongsToSchool به مدرسه محدود می‌کند
        return $q->orderBy('name')->get(['id', 'name', 'grade']);
    }

    public function record(Request $request): Response
    {
        $user = $request->user();
        $classrooms = $this->classrooms($request);
        $classroomId = (int) $request->query('classroom_id') ?: ($classrooms->first()->id ?? 0);
        $classroom = $classrooms->firstWhere('id', $classroomId);

        $date = $request->query('date') ?: now()->toDateString();
        try { $carbon = Carbon::parse($date); } catch (\Throwable $e) { $carbon = now(); $date = now()->toDateString(); }

        $students = collect();
        $recentGroups = [];

        if ($classroom) {
            $real = Classroom::find($classroom->id); // برای رابطه‌ها
            $today = AttendanceRecord::where('classroom_id', $classroom->id)
                ->whereDate('date', $date)->get()->keyBy('student_id');

            $stats = AttendanceRecord::where('classroom_id', $classroom->id)
                ->selectRaw('student_id, status, COUNT(*) as c')->groupBy('student_id', 'status')->get()
                ->groupBy('student_id');

            // نگاشتِ ثبت‌کننده‌ها (نام + نقش) — برای نمایش «چه کسی ثبت/اصلاح کرده»
            $recorders = $this->recorderMap($classroom->id);

            $students = $real->students()->get(['users.id', 'name'])->map(function ($s) use ($today, $stats, $recorders) {
                $st = $stats->get($s->id) ?? collect();
                $rec = $today->get($s->id);
                return [
                    'id' => $s->id, 'name' => $s->name,
                    'status' => $rec?->status ?? 'present',
                    'recorded' => (bool) $rec,
                    'by' => $rec ? ($recorders[$rec->recorded_by] ?? null) : null,
                    'absent' => (int) ($st->firstWhere('status', 'absent')->c ?? 0),
                    'late'   => (int) ($st->firstWhere('status', 'late')->c ?? 0),
                ];
            })->values();

            $recentGroups = $this->recentGroups($classroom->id, $recorders);
        }

        $hasRecords = $students->contains(fn ($s) => $s['recorded']);

        return Inertia::render('Attendance/Manage', [
            'role'         => $this->isTeacher($request) ? 'teacher' : 'school_admin',
            'routes'       => $this->routeNames($request),
            'classrooms'   => $classrooms,
            'classroomId'  => $classroomId,
            'classroom'    => $classroom,
            'students'     => $students,
            'date'         => $date,
            'jdate'        => Jalali::format($carbon, true),
            'hasRecords'   => $hasRecords,
            'recentGroups' => $recentGroups,
        ]);
    }

    /** نگاشت شناسه‌ی ثبت‌کننده به نام و نقشِ فارسی. */
    private function recorderMap(int $classroomId): array
    {
        $ids = AttendanceRecord::where('classroom_id', $classroomId)
            ->whereNotNull('recorded_by')->distinct()->pluck('recorded_by');
        return \App\Models\User::whereIn('id', $ids)->with('roles:id,name')->get()
            ->mapWithKeys(fn ($u) => [$u->id => ['name' => $u->name, 'role' => $this->roleLabel($u)]])->all();
    }

    private function roleLabel(\App\Models\User $u): string
    {
        $r = $u->roles->pluck('name')->first();
        return ['super_admin' => 'ادمین کل', 'school_admin' => 'مدیر مدرسه', 'teacher' => 'معلم', 'student' => 'دانش‌آموز'][$r] ?? 'کاربر';
    }

    /**
     * سوابق اخیر، دسته‌بندی‌شده بر اساس سال ← ماهِ شمسی (فقط ماه‌ها/سال‌هایی که رکورد دارند).
     */
    private function recentGroups(int $classroomId, array $recorders): array
    {
        $records = AttendanceRecord::where('classroom_id', $classroomId)
            ->orderByDesc('date')->orderByDesc('updated_at')
            ->get(['date', 'status', 'recorded_by', 'updated_at']);

        $byDate = $records->groupBy(fn ($r) => Carbon::parse($r->date)->toDateString());
        $years = [];

        foreach ($byDate as $ds => $recs) {
            $c = Carbon::parse($ds);
            $p = Jalali::ymParts($c);
            $latest = $recs->first(); // آخرین ویرایش همان روز
            $day = [
                'date' => $ds,
                'day' => $p['day'], 'weekday' => $p['weekday'], 'short' => $p['short'],
                'absents' => $recs->where('status', 'absent')->count(),
                'total' => $recs->count(),
                'by' => $latest && $latest->recorded_by ? ($recorders[$latest->recorded_by] ?? null) : null,
            ];
            $yk = (string) $p['jy'];
            $mk = $p['jy'] . '-' . str_pad((string) $p['jm'], 2, '0', STR_PAD_LEFT);
            $years[$yk]['year'] = $p['year'];
            $years[$yk]['jy'] = $p['jy'];
            $years[$yk]['months'][$mk]['label'] = $p['monthLabel'];
            $years[$yk]['months'][$mk]['jm'] = $p['jm'];
            $years[$yk]['months'][$mk]['days'][] = $day;
        }

        // مرتب‌سازی: سال نزولی، ماه نزولی
        krsort($years);
        return array_values(array_map(function ($y) {
            krsort($y['months']);
            $y['months'] = array_values($y['months']);
            return $y;
        }, $years));
    }

    /** فرم خالی حضور و غیاب ماهانه برای چاپ (اسامی دانش‌آموزان × روزهای ماه). */
    public function monthlySheet(Request $request): Response
    {
        $classrooms = $this->classrooms($request);
        $classroomId = (int) $request->query('classroom_id') ?: ($classrooms->first()->id ?? 0);
        $classroom = $classrooms->firstWhere('id', $classroomId);

        $students = collect();
        if ($classroom) {
            $students = Classroom::find($classroom->id)->students()->orderBy('name')
                ->get(['users.id', 'name'])->map(fn ($s) => $s->name)->values();
        }

        return Inertia::render('Attendance/MonthlySheet', [
            'role'        => $this->isTeacher($request) ? 'teacher' : 'school_admin',
            'routes'      => $this->routeNames($request),
            'classrooms'  => $classrooms,
            'classroomId' => $classroomId,
            'classroom'   => $classroom,
            'students'    => $students,
            'meta'        => [
                'school'  => $request->user()->school?->name,
                'teacher' => $classroom?->teacher_id ? \App\Models\User::find($classroom->teacher_id)?->name : null,
            ],
        ]);
    }

    public function store(Request $request, GamificationService $game): RedirectResponse
    {
        $data = $request->validate([
            'classroom_id'         => ['required', 'integer'],
            'date'                 => ['required', 'date'],
            'records'              => ['required', 'array'],
            'records.*.student_id' => ['required', 'integer'],
            'records.*.status'     => ['required', 'in:present,absent,late,excused'],
            'records.*.note'       => ['nullable', 'string', 'max:200'],
        ]);

        $classroom = $this->authorizedClassroom($request, $data['classroom_id']);
        $studentIds = $classroom->students()->pluck('users.id')->all();
        $date = Carbon::parse($data['date'])->toDateString();
        $jdate = Jalali::format(Carbon::parse($date));
        $recorder = $request->user();

        foreach ($data['records'] as $r) {
            if (! in_array($r['student_id'], $studentIds, true)) {
                continue;
            }
            // جست‌وجو با whereDate تا با فرمت ذخیره‌شده‌ی ستون تاریخ (datetime) هم‌خوان باشد
            $record = AttendanceRecord::where('classroom_id', $classroom->id)
                ->where('student_id', $r['student_id'])->whereDate('date', $date)->first();

            $wasNew = false; $statusChanged = false;
            if ($record) {
                $statusChanged = $record->status !== $r['status'];
                $record->fill(['status' => $r['status'], 'note' => $r['note'] ?? null, 'recorded_by' => $recorder->id])->save();
            } else {
                $record = AttendanceRecord::create([
                    'school_id' => $classroom->school_id, 'classroom_id' => $classroom->id,
                    'student_id' => $r['student_id'], 'date' => $date,
                    'status' => $r['status'], 'note' => $r['note'] ?? null, 'recorded_by' => $recorder->id,
                ]);
                $wasNew = true;
            }

            if ($wasNew || $statusChanged) {
                $game->awardForAttendance($record, $recorder);
                $this->syncAbsenceAlarm($record, $classroom, $jdate, $date, $recorder);
            }
        }

        return back()->with('flash', 'حضور و غیاب ثبت شد ✅');
    }

    /** حذف رکوردهای یک روزِ کلاس (همه‌ی دانش‌آموزان) + برگشت XP. */
    public function destroyDay(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'classroom_id' => ['required', 'integer'],
            'date'         => ['required', 'date'],
        ]);
        $classroom = $this->authorizedClassroom($request, $data['classroom_id']);
        $date = Carbon::parse($data['date'])->toDateString();

        $records = AttendanceRecord::where('classroom_id', $classroom->id)->whereDate('date', $date)->get();
        $this->purgeRecords($records);

        return back()->with('flash', 'رکوردهای این روز حذف شد 🗑️');
    }

    /** حذف رکورد یک دانش‌آموز در یک روز. */
    public function destroyOne(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'classroom_id' => ['required', 'integer'],
            'student_id'   => ['required', 'integer'],
            'date'         => ['required', 'date'],
        ]);
        $classroom = $this->authorizedClassroom($request, $data['classroom_id']);
        $date = Carbon::parse($data['date'])->toDateString();

        $records = AttendanceRecord::where('classroom_id', $classroom->id)
            ->where('student_id', $data['student_id'])->whereDate('date', $date)->get();
        $this->purgeRecords($records);

        return back()->with('flash', 'رکورد این دانش‌آموز حذف شد 🗑️');
    }

    /** حذف مجموعه‌ای از رکوردها همراه با XP مربوط به هرکدام. */
    private function purgeRecords($records): void
    {
        foreach ($records as $rec) {
            XpEntry::where('source_type', AttendanceRecord::class)->where('source_id', $rec->id)->delete();
            $rec->delete();
        }
    }

    /** کلاسی که کاربر مجاز به مدیریت آن است، یا 403/404. */
    private function authorizedClassroom(Request $request, int $classroomId): Classroom
    {
        $classroom = Classroom::findOrFail($classroomId);
        if ($this->isTeacher($request)) {
            abort_unless($classroom->teacher_id === $request->user()->id, 403);
        } else {
            abort_unless($classroom->school_id === $request->user()->school_id, 403);
        }
        return $classroom;
    }

    /** اعلان (الارم) غیبت/تأخیر به دانش‌آموز — بدون تکرار برای همان روز. */
    private function syncAbsenceAlarm(AttendanceRecord $record, Classroom $classroom, string $jdate, string $date, $recorder): void
    {
        if (! in_array($record->status, ['absent', 'late'], true)) {
            return;
        }
        $title = ($record->status === 'absent' ? 'ثبت غیبت' : 'ثبت تأخیر') . ' — ' . $jdate;
        $exists = Announcement::where('school_id', $classroom->school_id)
            ->where('title', $title)->where('audience', 'personal')
            ->whereHas('recipients', fn ($q) => $q->where('users.id', $record->student_id))->exists();
        if ($exists) {
            return;
        }
        $ann = Announcement::create([
            'school_id' => $classroom->school_id, 'sender_id' => $recorder->id,
            'title' => $title, 'audience' => 'personal',
            'body' => $record->status === 'absent'
                ? "امروز ({$jdate}) در کلاس غایب بودی. در صورت موجه‌بودن، با مدرسه هماهنگ کن."
                : "امروز ({$jdate}) با تأخیر وارد کلاس شدی. لطفاً سر وقت حاضر شو.",
        ]);
        $ann->recipients()->sync([$record->student_id]);
    }
}
