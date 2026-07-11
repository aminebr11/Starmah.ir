<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\CurriculumBook;
use App\Models\ScheduleEntry;
use App\Support\Jalali;
use App\Support\Roles;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * برنامه‌ی کلاسی — مدیریت توسط معلم (کلاس خودش) و مدیر مدرسه (همه‌ی کلاس‌ها).
 * زنگ‌ها بر اساس ساعت مرتب می‌شوند؛ برنامه می‌تواند همیشگی یا محدود به تاریخِ خاص باشد.
 */
class ScheduleController extends Controller
{
    private function isTeacher(Request $request): bool
    {
        return $request->user()->hasRole(Roles::TEACHER);
    }

    private function classrooms(Request $request)
    {
        $user = $request->user();
        $q = $this->isTeacher($request)
            ? Classroom::where('teacher_id', $user->id)
            : Classroom::query();
        return $q->orderBy('name')->get(['id', 'name', 'grade']);
    }

    private function routeNames(Request $request): array
    {
        $p = $this->isTeacher($request) ? 'teacher' : 'school';
        return ['index' => "$p.schedule", 'store' => "$p.schedule.store", 'destroy' => "$p.schedule.destroy"];
    }

    /** صفحه‌ی مدیریت برنامه (مشترک معلم/مدیر). */
    public function manage(Request $request): Response
    {
        $classrooms = $this->classrooms($request);
        $classroomId = (int) $request->query('classroom_id') ?: ($classrooms->first()->id ?? 0);
        $classroom = $classrooms->firstWhere('id', $classroomId);
        $level = $request->user()->school?->level;

        $books = ($classroom && $classroom->grade)
            ? CurriculumBook::where('level', $level)->where('grade', $classroom->grade)->where('is_active', true)
                ->orderBy('sort')->get()->map(fn ($b) => ['name' => $b->name, 'icon' => $b->icon])->values()
            : collect();

        return Inertia::render('Schedule/Manage', [
            'role'        => $this->isTeacher($request) ? 'teacher' : 'school_admin',
            'routes'      => $this->routeNames($request),
            'classrooms'  => $classrooms,
            'classroomId' => $classroomId,
            'classroom'   => $classroom?->only('id', 'name', 'grade'),
            'days'        => Jalali::weekdays(),
            'entries'     => $this->entriesFor($classroom?->id),
            'books'       => $books,
        ]);
    }

    /** مدیر مدرسه: نمای برنامه‌ی همه‌ی کلاس‌ها (فقط مشاهده). */
    public function schoolView(Request $request): Response
    {
        $classes = Classroom::with('teacher:id,name')->get()
            ->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'grade' => $c->grade,
                'teacher' => $c->teacher?->name,
                'entries' => $this->entriesFor($c->id),
            ])->values();

        return Inertia::render('SchoolAdmin/Schedule', [
            'days'    => Jalali::weekdays(),
            'classes' => $classes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'classroom_id'  => ['required', 'integer'],
            'day_of_week'   => ['required', 'integer', 'min:0', 'max:6'],
            'kind'          => ['required', 'in:class,recess'],
            'title'         => ['nullable', 'string', 'max:80'],
            'start_time'    => ['required', 'date_format:H:i'],
            'end_time'      => ['required', 'date_format:H:i', 'after:start_time'],
            'period'        => ['nullable', 'integer', 'min:1', 'max:12'],
            'specific_date' => ['nullable', 'date'],
        ]);

        $classroom = $this->authorizedClassroom($request, $data['classroom_id']);

        $title = $data['kind'] === 'recess' ? ($data['title'] ?: 'زنگ تفریح') : $data['title'];
        if ($data['kind'] === 'class' && empty($title)) {
            return back()->withErrors(['title' => 'برای درس، عنوان لازم است.']);
        }

        $specificDate = ! empty($data['specific_date']) ? Carbon::parse($data['specific_date'])->toDateString() : null;

        // 🔔 هشدار تداخل: هم‌پوشانیِ زمانی در همان روز و همان دامنه (همیشگی یا همان تاریخِ خاص)
        $newStart = $this->toMinutes($data['start_time']);
        $newEnd = $this->toMinutes($data['end_time']);
        $overlap = ScheduleEntry::where('classroom_id', $classroom->id)
            ->where('day_of_week', $data['day_of_week'])
            ->when($specificDate,
                fn ($q) => $q->whereDate('specific_date', $specificDate),
                fn ($q) => $q->whereNull('specific_date'))
            ->whereNotNull('start_time')->whereNotNull('end_time')->get()
            ->first(fn ($e) => $newStart < $this->toMinutes($e->end_time) && $newEnd > $this->toMinutes($e->start_time));
        if ($overlap) {
            return back()->withErrors(['start_time' => "⏰ تداخل زمانی: «{$overlap->title}» ({$overlap->time_range}) از قبل در این بازه ثبت شده است."]);
        }

        ScheduleEntry::create([
            'school_id'     => $classroom->school_id,
            'classroom_id'  => $classroom->id,
            'day_of_week'   => $data['day_of_week'],
            'specific_date' => $specificDate,
            'kind'          => $data['kind'],
            'title'         => $title,
            'start_time'    => $data['start_time'],
            'end_time'      => $data['end_time'],
            'time_range'    => $data['start_time'] . ' - ' . $data['end_time'],
            'period'        => $data['period'] ?? null,
        ]);

        return back()->with('flash', 'به برنامه اضافه شد ✅');
    }

    public function destroy(Request $request, ScheduleEntry $scheduleEntry): RedirectResponse
    {
        $this->authorizedClassroom($request, $scheduleEntry->classroom_id);
        $scheduleEntry->delete();
        return back();
    }

    /** دانش‌آموز: نمای برنامه‌ی کلاسش. */
    public function studentView(Request $request): Response
    {
        $classroom = $request->user()->classrooms()->first();
        $todayIdx = ((int) now()->format('w') + 1) % 7;
        return Inertia::render('Student/Schedule', [
            'days'    => Jalali::weekdays(),
            'today'   => $todayIdx,
            'jtoday'  => Jalali::format(now(), true),
            'entries' => $this->entriesFor($classroom?->id),
        ]);
    }

    private function toMinutes(string $hm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hm) + [1 => 0]);
        return $h * 60 + $m;
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

    /** ورودی‌های برنامه، مرتب‌شده بر اساس روز و سپس ساعتِ شروع (نه ترتیب ثبت). */
    private function entriesFor(?int $classroomId): array
    {
        if (! $classroomId) {
            return [];
        }
        return ScheduleEntry::where('classroom_id', $classroomId)
            ->orderBy('day_of_week')
            ->orderByRaw('start_time IS NULL, start_time')
            ->orderBy('period')
            ->get()
            ->groupBy('day_of_week')
            ->map(fn ($g) => $g->map(fn ($e) => [
                'id' => $e->id, 'title' => $e->title, 'time' => $e->time_range, 'period' => $e->period,
                'kind' => $e->kind ?? 'class', 'start' => $e->start_time, 'end' => $e->end_time,
                'specific_date' => $e->specific_date?->toDateString(),
                'jdate' => $e->specific_date ? Jalali::format($e->specific_date) : null,
            ])->values())
            ->toArray();
    }
}
