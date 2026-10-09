<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ClassroomPref;
use App\Models\WeeklyReport;
use App\Services\WeeklyReportService;
use App\Support\Jalali;
use App\Support\SmsGateway;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** معلم: «📬 گزارشِ هفتگیِ والدین» — تنظیمات، پیش‌نویس‌ها، یادداشت و ارسال. */
class WeeklyReportController extends Controller
{
    private function room(Request $request, $id): Classroom
    {
        return Classroom::where('teacher_id', $request->user()->id)->findOrFail($id);
    }

    private function week(Request $request): Carbon
    {
        $w = $request->input('week');

        return $w ? WeeklyReportService::weekStart(Carbon::parse($w)) : WeeklyReportService::weekStart();
    }

    public function index(Request $request, WeeklyReportService $svc): Response
    {
        if (! WeeklyReport::ready()) {
            \App\Support\AutoMigrate::ensure(true);
            \App\Support\DbSchema::forget();
        }
        $teacher = $request->user();
        $rooms = Classroom::where('teacher_id', $teacher->id)->orderBy('id')->get(['id', 'name', 'grade', 'school_id']);
        $room = $rooms->firstWhere('id', (int) $request->query('classroom')) ?? $rooms->first();
        $week = $this->week($request);
        if (WeeklyReport::ready()) {
            rescue(fn () => $svc->runDue($teacher->school_id), null, false);
        }
        $reports = $room && WeeklyReport::ready()
            ? WeeklyReport::where('classroom_id', $room->id)->whereDate('week_start', $week->toDateString())->with('student:id,name,avatar')->get()->sortBy('student.name')
            : collect();

        $weeks = collect(range(0, 5))->map(function ($i) {
            $w = WeeklyReportService::weekStart()->subWeeks($i);

            return ['value' => $w->toDateString(), 'label' => ($i === 0 ? 'این هفته — ' : ($i === 1 ? 'هفته‌ی قبل — ' : '')) . Jalali::format($w)];
        });

        return Inertia::render('Teacher/WeeklyReports', [
            'ready' => WeeklyReport::ready(),
            'classrooms' => $rooms,
            'classroomId' => $room?->id,
            'week' => $week->toDateString(),
            'weeks' => $weeks,
            'settings' => WeeklyReportService::settings($room?->id),
            'days' => WeeklyReportService::DAYS,
            'smsReady' => SmsGateway::gatewayReady() && SmsGateway::schoolEnabled($teacher->school),
            'reports' => $reports->map(fn ($r) => [
                'id' => $r->id, 'student' => $r->student?->name, 'status' => $r->status, 'note' => $r->teacher_note,
                'data' => $r->data, 'activity' => $r->activity, 'sms_status' => $r->sms_status,
                'sent' => $r->sent_at ? Jalali::format($r->sent_at, true) : null,
                'sms_text' => WeeklyReportService::smsText($r, $r->student?->name ?? ''),
            ])->values(),
        ]);
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'classroom_id' => ['required', 'integer'], 'mode' => ['required', 'in:manual,approve,auto'],
            'day' => ['required', 'integer', 'min:0', 'max:6'], 'hour' => ['required', 'integer', 'min:0', 'max:23'],
            'app' => ['boolean'], 'sms' => ['boolean'], 'all' => ['boolean'],
        ]);
        $value = ['mode' => $data['mode'], 'day' => (int) $data['day'], 'hour' => (int) $data['hour'], 'app' => (bool) ($data['app'] ?? true), 'sms' => (bool) ($data['sms'] ?? false)];
        $ids = ! empty($data['all']) ? Classroom::where('teacher_id', $request->user()->id)->pluck('id') : collect([$this->room($request, $data['classroom_id'])->id]);
        foreach ($ids as $id) {
            ClassroomPref::put($id, WeeklyReportService::KEY, $value);
        }

        return back()->with('flash', '✅ تنظیماتِ گزارشِ هفتگی ذخیره شد.');
    }

    public function build(Request $request, WeeklyReportService $svc): RedirectResponse
    {
        $room = $this->room($request, $request->input('classroom_id'));
        $n = $svc->build($room, $this->week($request));

        return back()->with('flash', '🛠️ گزارشِ ' . Jalali::fa((string) $n) . ' دانش‌آموز ساخته/به‌روز شد.');
    }

    private function mine(Request $request, WeeklyReport $report): void
    {
        abort_unless(Classroom::where('teacher_id', $request->user()->id)->whereKey($report->classroom_id)->exists(), 403);
    }

    public function update(Request $request, WeeklyReport $report): RedirectResponse
    {
        $this->mine($request, $report);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:600'], 'status' => ['nullable', 'in:draft,approved,skipped']]);
        $patch = [];
        if ($request->has('note')) $patch['teacher_note'] = $data['note'];
        if (! empty($data['status']) && $report->status !== 'sent') $patch['status'] = $data['status'];
        $report->update($patch);

        return back();
    }

    public function send(Request $request, WeeklyReportService $svc): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']]);
        $teacher = $request->user();
        $rooms = Classroom::where('teacher_id', $teacher->id)->pluck('id');
        $sent = 0; $sms = 0;
        foreach (WeeklyReport::whereIn('id', $data['ids'])->whereIn('classroom_id', $rooms)->whereIn('status', ['draft', 'approved'])->get() as $r) {
            $res = $svc->send($r, $teacher);
            $sent += $res['ok'] ? 1 : 0;
            $sms += ($res['sms'] ?? null) === 'sent' ? 1 : 0;
        }

        return back()->with('flash', '📬 ' . Jalali::fa((string) $sent) . ' گزارش برای والدین فرستاده شد' . ($sms ? ' (' . Jalali::fa((string) $sms) . ' پیامک)' : '') . '.');
    }
}
