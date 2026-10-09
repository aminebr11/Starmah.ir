<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ClassroomPref;
use App\Models\ScreenTime;
use App\Models\User;
use App\Services\WellbeingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** معلم: «🌿 سلامتِ دیجیتال» برای هر کلاس + زمانِ امروزِ بچه‌ها. */
class WellbeingController extends Controller
{
    public function index(Request $request, WellbeingService $wb): Response
    {
        if (! ScreenTime::ready()) {
            \App\Support\AutoMigrate::ensure(true);
            \App\Support\DbSchema::forget();
        }
        $rooms = Classroom::where('teacher_id', $request->user()->id)->orderBy('id')->get(['id', 'name']);
        $room = $rooms->firstWhere('id', (int) $request->query('classroom')) ?? $rooms->first();
        $students = $room ? $room->students()->orderBy('name')->get(['users.id', 'users.name', 'users.settings']) : collect();
        $rows = ScreenTime::ready() ? ScreenTime::whereIn('student_id', $students->pluck('id'))->whereDate('day', now()->toDateString())->get()->keyBy('student_id') : collect();
        $week = ScreenTime::ready() ? ScreenTime::whereIn('student_id', $students->pluck('id'))->whereDate('day', '>=', now()->subDays(6)->toDateString())
            ->selectRaw('student_id, sum(seconds) as s')->groupBy('student_id')->pluck('s', 'student_id') : collect();

        return Inertia::render('Teacher/Wellbeing', [
            'ready' => ScreenTime::ready(),
            'classrooms' => $rooms,
            'classroomId' => $room?->id,
            'settings' => WellbeingService::classSettings($room?->id),
            'students' => $students->map(function (User $s) use ($wb, $rows) {
                $st = $wb->status($s, $rows[$s->id] ?? null);

                return ['id' => $s->id, 'name' => $s->name, 'today' => (int) round($st['used'] / 60), 'session' => (int) round($st['session'] / 60),
                    'locked' => $st['reason'], 'parent_daily' => $st['limits']['parent_daily'], 'parent_session' => $st['limits']['parent_session'],
                    'resets' => (int) ($rows[$s->id]->resets ?? 0)];
            })->values()->map(fn ($x) => $x + ['week' => (int) round(($week[$x['id']] ?? 0) / 60)]),
        ]);
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'classroom_id' => ['required', 'integer'],
            'daily' => ['required', 'integer', 'min:0', 'max:600'], 'session' => ['required', 'integer', 'min:0', 'max:300'],
            'break_every' => ['required', 'integer', 'min:0', 'max:120'], 'break_minutes' => ['required', 'integer', 'min:1', 'max:15'],
            'all' => ['boolean'],
        ]);
        $mine = Classroom::where('teacher_id', $request->user()->id);
        $ids = ! empty($data['all']) ? $mine->pluck('id') : collect([$mine->findOrFail($data['classroom_id'])->id]);
        foreach ($ids as $id) {
            ClassroomPref::put($id, WellbeingService::KEY, collect($data)->only(['daily', 'session', 'break_every', 'break_minutes'])->map(fn ($v) => (int) $v)->all());
            WellbeingService::forget(Classroom::find($id)->students()->pluck('users.id'));
        }

        return back()->with('flash', '🌿 تنظیماتِ سلامتِ دیجیتال ذخیره شد.');
    }

    public function reset(Request $request, User $student, WellbeingService $wb): RedirectResponse
    {
        abort_unless(Classroom::where('teacher_id', $request->user()->id)->whereHas('students', fn ($q) => $q->where('users.id', $student->id))->exists(), 403);
        $wb->reset($student);

        return back()->with('flash', '🔓 زمانِ امروزِ ' . $student->name . ' صفر شد.');
    }
}
