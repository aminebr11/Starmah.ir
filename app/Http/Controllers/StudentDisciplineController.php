<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\DisciplineRecord;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** موارد انضباطی دانش‌آموز: جدید (۲۴ ساعت اخیر) + سوابق + خلاصه‌ی حضور و غیاب. */
class StudentDisciplineController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        \App\Support\Notifications::markSeen($user);
        $attendance = $this->attendanceSummary($user->id);
        $all = DisciplineRecord::where('student_id', $user->id)->latest()->limit(100)->get();

        $map = fn ($r) => [
            'title' => $r->title ?? $r->note ?? ($r->points >= 0 ? 'تشویق' : 'تذکر'),
            'points' => $r->points,
            'kind' => $r->points >= 0 ? 'positive' : 'negative',
            'note' => $r->note,
            'date' => $r->jalaliDate(),
        ];

        $recent = $all->filter(fn ($r) => $r->created_at->gt(now()->subDay()))->map($map)->values();
        $history = $all->filter(fn ($r) => $r->created_at->lte(now()->subDay()))->map($map)->values();

        return Inertia::render('Student/Discipline', [
            'recent'  => $recent,
            'history' => $history,
            'totals'  => [
                'positive' => $all->where('points', '>', 0)->sum('points'),
                'negative' => $all->where('points', '<', 0)->sum('points'),
            ],
            'attendance' => $attendance,
        ]);
    }

    /** خلاصه‌ی حضور و غیاب یک دانش‌آموز (برای کارنامه‌ی انضباطی). */
    public static function attendanceSummary(int $studentId): array
    {
        $c = AttendanceRecord::where('student_id', $studentId)
            ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $present = (int) ($c['present'] ?? 0);
        $absent  = (int) ($c['absent'] ?? 0);
        $late    = (int) ($c['late'] ?? 0);
        $excused = (int) ($c['excused'] ?? 0);
        $total   = $present + $absent + $late + $excused;

        return [
            'present' => $present, 'absent' => $absent, 'late' => $late, 'excused' => $excused,
            'total' => $total,
            'percent' => $total ? (int) round(($present + $late) / $total * 100) : 100,
        ];
    }
}
