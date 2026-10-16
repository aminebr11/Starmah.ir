<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Classroom;
use App\Models\OnlineParticipant;
use App\Models\OnlineRecording;
use App\Models\OnlineRoom;
use App\Services\EarlyWarningService;
use App\Support\ClassSupervision;
use App\Support\Jalali;
use App\Support\OnlineAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «🛡️ نظارتِ کلاس‌ها» — صفحه‌ی ناظر: فقط کلاس‌هایی که مدیرِ مدرسه به او داده.
 * کلاسِ آنلاینِ زنده (ورود و کنترل، پایان)، حضورِ امروز، هشدارِ زودهنگام، دانش‌آموزان،
 * کلاس‌های آنلاینِ گذشته با حضور و فیلم.
 */
class SuperviseController extends Controller
{
    public function index(Request $request, EarlyWarningService $warn): Response
    {
        $u = $request->user();
        abort_unless(ClassSupervision::isSupervisor($u), 403, 'مدیرِ مدرسه هنوز کلاسی را به نظارتِ شما نسپرده است.');
        $ids = ClassSupervision::classroomIds($u);

        $classes = Classroom::with('teacher:id,name')->whereIn('id', $ids)->orderBy('name')->get(['id', 'name', 'grade', 'teacher_id', 'school_id']);
        $members = DB::table('classroom_student')->whereIn('classroom_id', $ids)->get(['classroom_id', 'student_id'])->groupBy('classroom_id');
        $studentIds = $members->flatten()->pluck('student_id')->unique()->values();
        $names = \App\Models\User::whereIn('id', $studentIds)->pluck('name', 'id');

        // حضورِ امروز
        $today = AttendanceRecord::whereIn('classroom_id', $ids)->whereDate('date', now()->toDateString())
            ->selectRaw('classroom_id, status, count(*) as c')->groupBy('classroom_id', 'status')->get()->groupBy('classroom_id');

        // امتیاز و آخرین فعالیتِ ۷ روزِ اخیر
        $week = rescue(fn () => DB::table('xp_ledger')->whereIn('student_id', $studentIds)->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('student_id, sum(amount) as xp, max(created_at) as last')->groupBy('student_id')->get()->keyBy('student_id'), collect(), false);
        $last = rescue(fn () => DB::table('xp_ledger')->whereIn('student_id', $studentIds)
            ->selectRaw('student_id, max(created_at) as last')->groupBy('student_id')->pluck('last', 'student_id'), collect(), false);

        // هشدارِ زودهنگامِ همین کلاس‌ها (از همان محاسبه‌ی مدیر)
        $ew = rescue(fn () => $warn->forSchool((int) $u->school_id), ['alerts' => [], 'classes' => []], false);
        $ewClasses = collect($ew['classes'] ?? [])->keyBy('classroom_id');

        $cards = $classes->map(function ($c) use ($members, $names, $today, $week, $last, $ewClasses) {
            $sids = collect($members->get($c->id, []))->pluck('student_id')->unique();
            $t = $today->get($c->id, collect());
            $e = $ewClasses->get($c->id);

            return [
                'id' => $c->id, 'name' => $c->name, 'grade' => $c->grade, 'teacher' => $c->teacher?->name,
                'students' => $sids->count(),
                'today' => [
                    'recorded' => $t->sum('c') > 0,
                    'present' => (int) ($t->firstWhere('status', 'present')->c ?? 0),
                    'absent' => (int) ($t->firstWhere('status', 'absent')->c ?? 0),
                    'late' => (int) ($t->firstWhere('status', 'late')->c ?? 0),
                ],
                'level' => $e['level'] ?? 'ok', 'active' => $e['active'] ?? null, 'accuracy' => $e['accuracy'] ?? null,
                'absence' => $e['absence'] ?? null, 'inactive_count' => $e['inactive_count'] ?? 0,
                'roster' => $sids->map(fn ($id) => [
                    'id' => (int) $id, 'name' => $names[$id] ?? '—',
                    'xp_week' => (int) ($week[$id]->xp ?? 0),
                    'last' => isset($last[$id]) ? Jalali::format(\Carbon\Carbon::parse($last[$id])) : null,
                ])->sortBy('name')->values()->all(),
            ];
        })->values();

        // کلاسِ آنلاین (اگر برای مدرسه روشن باشد)
        $onlineOn = OnlineAccess::ready() && OnlineAccess::schoolEnabled($u->school_id);
        $rooms = collect();
        $recordings = [];
        if ($onlineOn) {
            $rooms = ClassSupervision::onlineRooms($u)->with(['classroom:id,name', 'teacher:id,name'])
                ->withCount(['participants as present_count' => fn ($q) => $q->where('role', 'student')])
                ->orderByRaw("case status when 'live' then 0 when 'scheduled' then 1 else 2 end")->latest('id')->limit(60)->get();
            $rooms->each->closeIfAbandoned();
            if (OnlineRecording::ready() && $rooms->isNotEmpty()) {
                $byId = $rooms->keyBy('id');
                $recordings = OnlineRecording::whereIn('room_id', $rooms->pluck('id'))->where('size', '>', 0)->latest('id')->limit(40)->get()
                    ->map(fn ($r) => $r->card() + [
                        'title' => $byId[$r->room_id]->title, 'classroom' => $byId[$r->room_id]->classroom?->name ?? 'همه‌ی کلاس‌ها',
                        'teacher' => $byId[$r->room_id]->teacher?->name, 'shared' => (bool) $byId[$r->room_id]->recording_shared,
                    ])->values()->all();
            }
        }

        return Inertia::render('Online/Supervise', [
            'school' => $u->school?->name,
            'classes' => $cards,
            'alerts' => collect($ew['alerts'] ?? [])->whereIn('classroom_id', $ids)->values()->all(),
            'onlineOn' => $onlineOn,
            'rooms' => $rooms->map(fn ($r) => $this->room($r))->values(),
            'recordings' => $recordings,
        ]);
    }

    private function room(OnlineRoom $r): array
    {
        $online = $r->live() ? OnlineParticipant::where('room_id', $r->id)->whereNull('left_at')
            ->where('last_seen_at', '>=', now()->subSeconds(OnlineParticipant::ONLINE_SEC))->where('role', 'student')->count() : 0;

        return [
            'id' => $r->id, 'title' => $r->title, 'status' => $r->status, 'classroom' => $r->classroom?->name ?? 'همه‌ی کلاس‌های معلم',
            'teacher' => $r->teacher?->name, 'online' => $online, 'present' => (int) ($r->present_count ?? 0),
            'when' => $r->starts_at ? Jalali::format($r->starts_at) . ' ساعت ' . Jalali::fa($r->starts_at->format('H:i')) : null,
            'started' => $r->started_at ? Jalali::format($r->started_at) . ' ساعت ' . Jalali::fa($r->started_at->format('H:i')) : null,
            'length' => $r->started_at ? (int) round(($r->ended_at ?? now())->diffInSeconds($r->started_at, true) / 60) : null,
        ];
    }
}
