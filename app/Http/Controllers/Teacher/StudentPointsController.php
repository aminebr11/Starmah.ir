<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\User;
use App\Models\XpEntry;
use App\Services\GamificationService;
use App\Services\PointsAnalytics;
use App\Support\Jalali;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * مدیریتِ امتیازاتِ دانش‌آموز — معلم می‌تواند امتیاز اضافه/کم کند،
 * یک ردیفِ امتیاز را حذف کند، یا کلِ سابقه‌ی امتیازاتِ یک دانش‌آموز را پاک کند.
 * فقط دانش‌آموزانِ کلاس‌های خودِ معلم.
 */
class StudentPointsController extends Controller
{
    /** شناسه‌ی دانش‌آموزانِ کلاس‌های این معلم. */
    private function myStudentIds(User $teacher): array
    {
        return Classroom::where('teacher_id', $teacher->id)
            ->with('students:id')->get()
            ->flatMap(fn ($c) => $c->students->pluck('id'))->unique()->values()->all();
    }

    public function index(Request $request, PointsAnalytics $points): Response
    {
        $teacher = $request->user();
        $ids = $this->myStudentIds($teacher);

        // بازه‌ی گزارش: هفته (پیش‌فرض) / ماه / روز / سالِ تحصیلی / دلخواه
        $preset = $request->query('range', 'week');
        $range = $points->resolveRange($preset, $request->query('from'), $request->query('to'));

        $students = User::whereIn('id', $ids)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($s) => [
                'id' => $s->id, 'name' => $s->name,
                'total' => (int) XpEntry::where('student_id', $s->id)->sum('amount'),
            ]);

        $selectedId = (int) $request->query('student');
        $ledger = [];
        $selected = null;
        if ($selectedId && in_array($selectedId, $ids, true)) {
            $selected = $students->firstWhere('id', $selectedId);
            $ledger = XpEntry::where('student_id', $selectedId)->latest()->limit(200)->get()
                ->map(fn ($e) => [
                    'id' => $e->id, 'amount' => (int) $e->amount, 'reason' => $e->reason,
                    'date' => Jalali::format($e->created_at, true),
                    'date_raw' => $e->created_at?->timestamp,
                    'kind' => $e->amount >= 0 ? 'plus' : 'minus',
                ]);
        }

        $jy = \App\Support\SchoolCalendar::schoolYearOf();

        return Inertia::render('Teacher/StudentPoints', [
            'students' => $students->values(),
            'selected' => $selected,
            'ledger'   => $ledger,
            // رتبه‌بندیِ بازه‌ی انتخابی + ابزارهای انتخابِ بازه
            'rank'     => $points->rankTable($ids, $range['from'], $range['to']),
            'range'    => [
                'preset' => $preset,
                'label'  => $range['label'],
                'from'   => $range['from']?->toDateString(),
                'to'     => $range['to']?->toDateString(),
            ],
            'weeks'    => collect(\App\Support\SchoolCalendar::weeksSoFar($jy))->reverse()->values()
                ->map(fn ($w) => ['key' => $w['key'], 'label' => $w['short'] . ' — ' . $w['range']])->all(),
            'months'   => collect(\App\Support\SchoolCalendar::months($jy))->filter(fn ($m) => $m['from']->lte(now()))
                ->reverse()->values()->map(fn ($m) => ['key' => $m['from']->toDateString(), 'label' => $m['label']])->all(),
            'yearLabel' => \App\Support\SchoolCalendar::yearLabel($jy),
            'classroom' => Classroom::where('teacher_id', $teacher->id)->value('name'),
            // نمودارِ روندِ دانش‌آموزِ انتخاب‌شده
            'trend'    => $selected ? $points->studentTrend(User::find($selectedId)) : null,
        ]);
    }

    /** برگه‌ی چاپِ رتبه‌بندیِ امتیازها در بازه‌ی انتخابی. */
    public function print(Request $request, PointsAnalytics $points): \Illuminate\View\View
    {
        $teacher = $request->user();
        $ids = $this->myStudentIds($teacher);
        $range = $points->resolveRange($request->query('range', 'week'), $request->query('from'), $request->query('to'));
        $rows = $points->rankTable($ids, $range['from'], $range['to']);

        return view('print.points', [
            ...\App\Http\Controllers\PrintController::header($teacher->school_id, $request),
            'title' => 'رتبه‌بندیِ امتیازِ دانش‌آموزان',
            'range' => $range['label'],
            'classroom' => Classroom::where('teacher_id', $teacher->id)->value('name'),
            'teacher' => $teacher->name,
            'rows' => $rows,
            'sum' => [
                'students' => count($rows),
                'xp' => array_sum(array_column($rows, 'xp')),
                'plus' => array_sum(array_column($rows, 'plus')),
                'minus' => array_sum(array_column($rows, 'minus')),
                'entries' => array_sum(array_column($rows, 'entries')),
            ],
        ]);
    }

    /** افزودن یا کم‌کردنِ امتیاز (amount منفی = کسر). */
    public function adjust(Request $request, GamificationService $game): RedirectResponse
    {
        $teacher = $request->user();
        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'amount'     => ['required', 'integer', 'min:-500', 'max:500', 'not_in:0'],
            'reason'     => ['required', 'string', 'max:120'],
        ]);
        abort_unless(in_array((int) $data['student_id'], $this->myStudentIds($teacher), true), 403);

        $student = User::findOrFail($data['student_id']);
        $game->award($student, $data['amount'], $data['reason'], $teacher, null, null, true);

        return back()->with('flash', ($data['amount'] >= 0 ? 'امتیاز افزوده شد ✅' : 'امتیاز کسر شد ✅'));
    }

    /** حذفِ یک ردیفِ امتیاز. */
    public function destroyEntry(Request $request, XpEntry $xpEntry): RedirectResponse
    {
        abort_unless(in_array((int) $xpEntry->student_id, $this->myStudentIds($request->user()), true), 403);
        $xpEntry->delete();

        return back()->with('flash', 'ردیفِ امتیاز حذف شد');
    }

    /** حذفِ گروهیِ چند ردیفِ امتیازِ انتخاب‌شده. */
    public function destroyMany(Request $request): RedirectResponse
    {
        $teacher = $request->user();
        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);
        $myIds = $this->myStudentIds($teacher);
        $n = XpEntry::whereIn('id', $data['ids'])
            ->whereIn('student_id', $myIds)->delete();

        return back()->with('flash', "{$n} ردیفِ امتیاز حذف شد");
    }

    /** پاک‌کردنِ کلِ سابقه‌ی امتیازاتِ یک دانش‌آموز. */
    public function clear(Request $request): RedirectResponse
    {
        $teacher = $request->user();
        $data = $request->validate(['student_id' => ['required', 'integer']]);
        abort_unless(in_array((int) $data['student_id'], $this->myStudentIds($teacher), true), 403);

        XpEntry::where('student_id', $data['student_id'])->delete();

        return back()->with('flash', 'کلِ سابقه‌ی امتیازاتِ دانش‌آموز پاک شد');
    }
}
