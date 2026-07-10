<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\CurriculumBook;
use App\Models\ScheduleEntry;
use App\Support\Jalali;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** برنامه‌ی کلاسی هفتگی — مدیریت توسط معلم، نمایش به دانش‌آموز (تاریخ شمسی). */
class ScheduleController extends Controller
{
    /** معلم: مدیریت برنامه */
    public function manage(Request $request): Response
    {
        $classroom = Classroom::where('teacher_id', $request->user()->id)->first();
        $level = $request->user()->school?->level;

        // درس‌های پایه‌ی این کلاس برای انتخاب سریع
        $books = ($classroom && $classroom->grade)
            ? CurriculumBook::where('level', $level)->where('grade', $classroom->grade)->where('is_active', true)
                ->orderBy('sort')->get()->map(fn ($b) => ['name' => $b->name, 'icon' => $b->icon])->values()
            : collect();

        return Inertia::render('Teacher/Schedule', [
            'classroom' => $classroom?->only('id', 'name', 'grade'),
            'days'      => Jalali::weekdays(),
            'entries'   => $this->entriesFor($classroom),
            'books'     => $books,
        ]);
    }

    /** مدیر مدرسه: نمای برنامه‌ی همه‌ی کلاس‌ها */
    public function schoolView(Request $request): Response
    {
        $schoolId = $request->user()->school_id;
        $classes = Classroom::where('school_id', $schoolId)->with('teacher:id,name')->get()
            ->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'grade' => $c->grade,
                'teacher' => $c->teacher?->name,
                'entries' => $this->entriesFor($c),
            ])->values();

        return Inertia::render('SchoolAdmin/Schedule', [
            'days'    => Jalali::weekdays(),
            'classes' => $classes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $classroom = Classroom::where('teacher_id', $request->user()->id)->firstOrFail();
        $data = $request->validate([
            'day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'kind'        => ['required', 'in:class,recess'],
            'title'       => ['nullable', 'string', 'max:80'],
            'start_time'  => ['required', 'date_format:H:i'],
            'end_time'    => ['required', 'date_format:H:i', 'after:start_time'],
            'period'      => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $title = $data['kind'] === 'recess' ? ($data['title'] ?: 'زنگ تفریح') : $data['title'];
        if ($data['kind'] === 'class' && empty($title)) {
            return back()->withErrors(['title' => 'برای درس، عنوان لازم است.']);
        }

        // 🔔 هشدار تداخل: بررسی هم‌پوشانیِ زمانی با برنامه‌های همان روز
        $newStart = $this->toMinutes($data['start_time']);
        $newEnd = $this->toMinutes($data['end_time']);
        $overlap = ScheduleEntry::where('classroom_id', $classroom->id)
            ->where('day_of_week', $data['day_of_week'])
            ->whereNotNull('start_time')->whereNotNull('end_time')->get()
            ->first(fn ($e) => $newStart < $this->toMinutes($e->end_time) && $newEnd > $this->toMinutes($e->start_time));
        if ($overlap) {
            return back()->withErrors(['start_time' => "⏰ در این بازه، «{$overlap->title}» ({$overlap->time_range}) از قبل ثبت شده است."]);
        }

        ScheduleEntry::create([
            'school_id'    => $request->user()->school_id,
            'classroom_id' => $classroom->id,
            'day_of_week'  => $data['day_of_week'],
            'kind'         => $data['kind'],
            'title'        => $title,
            'start_time'   => $data['start_time'],
            'end_time'     => $data['end_time'],
            'time_range'   => $data['start_time'] . ' - ' . $data['end_time'],
            'period'       => $data['period'] ?? null,
        ]);

        return back()->with('flash', 'به برنامه اضافه شد ✅');
    }

    private function toMinutes(string $hm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hm) + [1 => 0]);
        return $h * 60 + $m;
    }

    public function destroy(Request $request, ScheduleEntry $scheduleEntry): RedirectResponse
    {
        abort_unless($scheduleEntry->school_id === $request->user()->school_id, 403);
        $scheduleEntry->delete();
        return back();
    }

    /** دانش‌آموز: نمای برنامه‌ی کلاسش */
    public function studentView(Request $request): Response
    {
        $classroom = $request->user()->classrooms()->first();
        $todayIdx = ((int) now()->format('w') + 1) % 7; // 0=شنبه
        return Inertia::render('Student/Schedule', [
            'days'    => Jalali::weekdays(),
            'today'   => $todayIdx,
            'jtoday'  => Jalali::format(now(), true),
            'entries' => $this->entriesFor($classroom),
        ]);
    }

    private function entriesFor(?Classroom $classroom): array
    {
        if (! $classroom) {
            return [];
        }
        return ScheduleEntry::where('classroom_id', $classroom->id)
            ->orderBy('day_of_week')->orderByRaw('start_time IS NULL, start_time')->orderBy('period')->get()
            ->groupBy('day_of_week')
            ->map(fn ($g) => $g->map(fn ($e) => [
                'id' => $e->id, 'title' => $e->title, 'time' => $e->time_range, 'period' => $e->period,
                'kind' => $e->kind ?? 'class', 'start' => $e->start_time, 'end' => $e->end_time,
            ])->values())
            ->toArray();
    }
}
