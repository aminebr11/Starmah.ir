<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Classroom;
use App\Support\Jalali;
use App\Support\Roles;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * مرکز گزارش حضور و غیاب — برای مدیر مدرسه (همه‌ی کلاس‌ها) و معلم (کلاس خودش).
 * فیلترها: بازه‌ی تاریخ، کلاس، دانش‌آموز، وضعیت + خلاصه‌ی آماری + خروجی چاپ.
 */
class AttendanceReportController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isTeacher = $user->hasRole(Roles::TEACHER);

        // دامنه‌ی کلاس‌های مجاز
        $classQuery = $isTeacher
            ? Classroom::where('teacher_id', $user->id)
            : Classroom::query(); // مدیر: BelongsToSchool به‌طور خودکار به مدرسه محدود می‌کند
        $classrooms = $classQuery->with('teacher:id,name')->get(['id', 'name', 'grade', 'teacher_id']);

        // فیلترها
        $from = $request->query('from') ?: now()->subDays(30)->toDateString();
        $to   = $request->query('to') ?: now()->toDateString();
        $classroomId = (int) $request->query('classroom_id') ?: ($classrooms->first()->id ?? 0);
        $studentId = (int) $request->query('student_id') ?: null;
        $status = $request->query('status'); // present|absent|late|excused|null

        $classroom = $classrooms->firstWhere('id', $classroomId);

        [$rows, $summary, $daily, $students] = $this->buildReport($classroom, $from, $to, $studentId, $status);

        return Inertia::render('Reports/Attendance', [
            'role'        => $isTeacher ? 'teacher' : 'school_admin',
            'classrooms'  => $classrooms->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'grade' => $c->grade, 'teacher' => $c->teacher?->name,
            ])->values(),
            'students'    => $students,
            'filters'     => [
                'from' => $from, 'to' => $to, 'classroom_id' => $classroomId,
                'student_id' => $studentId, 'status' => $status,
            ],
            'jfrom'       => Jalali::format(Carbon::parse($from)),
            'jto'         => Jalali::format(Carbon::parse($to)),
            'rows'        => $rows,
            'summary'     => $summary,
            'daily'       => $daily,
            'meta'        => [
                'school'    => $user->school?->name,
                'class'     => $classroom?->name,
                'grade'     => $classroom?->grade,
                'teacher'   => $classroom?->teacher?->name,
                'printedAt' => Jalali::format(now(), true),
            ],
        ]);
    }

    /**
     * ساخت گزارش: ردیف‌های هر دانش‌آموز + خلاصه‌ی کل + شکست روزانه برای نمودار.
     * @return array{0:array,1:array,2:array,3:array}
     */
    private function buildReport(?Classroom $classroom, string $from, string $to, ?int $studentId, ?string $status): array
    {
        if (! $classroom) {
            return [[], $this->emptySummary(), [], []];
        }

        $students = $classroom->students()->get(['users.id', 'name'])
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values();

        $records = AttendanceRecord::where('classroom_id', $classroom->id)
            ->whereBetween('date', [$from, $to])
            ->when($studentId, fn ($q) => $q->where('student_id', $studentId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->get(['student_id', 'status', 'date']);

        $byStudent = $records->groupBy('student_id');
        $nameOf = $students->pluck('name', 'id');

        $rows = collect($studentId ? [$studentId => true] : $nameOf->keys()->flip())
            ->keys()
            ->filter(fn ($id) => $nameOf->has($id))
            ->map(function ($id) use ($byStudent, $nameOf) {
                $rs = $byStudent->get($id) ?? collect();
                $c = fn ($s) => $rs->where('status', $s)->count();
                $present = $c('present'); $absent = $c('absent'); $late = $c('late'); $excused = $c('excused');
                $total = $present + $absent + $late + $excused;
                return [
                    'id' => $id, 'name' => $nameOf->get($id),
                    'present' => $present, 'absent' => $absent, 'late' => $late, 'excused' => $excused,
                    'total' => $total,
                    'percent' => $total ? (int) round(($present + $late) / $total * 100) : 100,
                ];
            })
            ->sortBy('name', SORT_NATURAL)
            ->values()->all();

        // خلاصه‌ی کل
        $summary = [
            'present' => $records->where('status', 'present')->count(),
            'absent'  => $records->where('status', 'absent')->count(),
            'late'    => $records->where('status', 'late')->count(),
            'excused' => $records->where('status', 'excused')->count(),
            'students'=> count($rows),
            'days'    => $records->pluck('date')->map(fn ($d) => (string) $d)->unique()->count(),
        ];
        $summary['total'] = $summary['present'] + $summary['absent'] + $summary['late'] + $summary['excused'];
        $summary['percent'] = $summary['total']
            ? (int) round(($summary['present'] + $summary['late']) / $summary['total'] * 100) : 100;

        // شکست روزانه برای نمودار (غیبت + تأخیر در هر روز)
        $daily = $records->groupBy(fn ($r) => (string) $r->date)
            ->map(fn ($rs, $date) => [
                'date'   => Jalali::format(Carbon::parse($date)),
                'absent' => $rs->where('status', 'absent')->count(),
                'late'   => $rs->where('status', 'late')->count(),
                'present'=> $rs->where('status', 'present')->count(),
            ])
            ->sortKeys()->values()->all();

        return [$rows, $summary, $daily, $students->all()];
    }

    private function emptySummary(): array
    {
        return ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0,
            'total' => 0, 'percent' => 100, 'students' => 0, 'days' => 0];
    }
}
