<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\User;
use App\Support\DbSchema;
use App\Support\Jalali;
use App\Support\Roles;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * «🚨 هشدارِ زودهنگام» برای مدیرِ مدرسه: افتِ مشارکت، افتِ تسلط/دقت، افتِ نمره،
 * غیبتِ زیاد، انباشتِ مرورها و کاربرگ‌های تصحیح‌نشده — کلاس‌به‌کلاس، با پیشنهادِ اقدام.
 */
class EarlyWarningService
{
    public const LEVELS = ['crit' => 3, 'warn' => 2, 'info' => 1];

    public function forSchool(int $schoolId, bool $fresh = false): array
    {
        $key = 'early-warning:' . $schoolId;
        if ($fresh) Cache::forget($key);

        return Cache::remember($key, 600, fn () => $this->compute($schoolId));
    }

    private function pct(float $a, float $b): int
    {
        return $b > 0 ? (int) round(100 * $a / $b) : 0;
    }

    public function compute(int $schoolId): array
    {
        $rooms = Classroom::where('school_id', $schoolId)->with('teacher:id,name')->orderBy('name')->get();
        $members = DB::table('classroom_student')->whereIn('classroom_id', $rooms->pluck('id'))->get(['classroom_id', 'student_id'])
            ->groupBy('classroom_id')->map(fn ($g) => $g->pluck('student_id')->map(fn ($v) => (int) $v)->unique()->values());
        $all = $members->flatten()->unique()->values();
        $today = now()->startOfDay();
        $safe = fn (callable $f, $d) => rescue($f, $d, false);

        // روزهای فعال (هر امتیاز = یک فعالیت) در ۶ هفته‌ی اخیر
        $days = $safe(fn () => DB::table('xp_ledger')->whereIn('student_id', $all)->where('created_at', '>=', $today->copy()->subDays(42))
            ->selectRaw('student_id, DATE(created_at) as d')->distinct()->get(), collect());
        $weekOf = fn ($d) => (int) floor($today->diffInDays(\Carbon\Carbon::parse($d)->startOfDay(), true) / 7); // ۰ = ۷ روزِ اخیر
        $activeWeeks = [];   // student => [week => true]
        $lastActive = [];
        foreach ($days as $r) {
            $w = min(5, $weekOf($r->d));
            $activeWeeks[$r->student_id][$w] = true;
            $lastActive[$r->student_id] = max($lastActive[$r->student_id] ?? '', (string) $r->d);
        }

        $acc = $safe(fn () => DbSchema::hasTable('practice_answers') ? DB::table('practice_answers')->whereIn('student_id', $all)
            ->where('created_at', '>=', $today->copy()->subDays(28))
            ->selectRaw('student_id, CASE WHEN created_at >= ? THEN 1 ELSE 0 END as recent, count(*) as n, sum(CASE WHEN correct THEN 1 ELSE 0 END) as ok', [$today->copy()->subDays(13)->toDateTimeString()])
            ->groupBy('student_id', 'recent')->get() : collect(), collect());

        $att = $safe(fn () => DB::table('attendance_records')->whereIn('student_id', $all)->where('date', '>=', $today->copy()->subDays(6)->toDateString())
            ->selectRaw('classroom_id, status, count(*) as c')->groupBy('classroom_id', 'status')->get(), collect());

        $grades = $safe(fn () => DB::table('grades')->join('grade_columns', 'grade_columns.id', '=', 'grades.grade_column_id')
            ->whereIn('grade_columns.classroom_id', $rooms->pluck('id'))->whereNotNull('grades.score')->where('grade_columns.max', '>', 0)
            ->where('grades.updated_at', '>=', $today->copy()->subDays(42))
            ->selectRaw('grade_columns.classroom_id as cid, CASE WHEN grades.updated_at >= ? THEN 1 ELSE 0 END as recent, count(*) as n, avg(grades.score * 100.0 / grade_columns.max) as avg', [$today->copy()->subDays(20)->toDateTimeString()])
            ->groupBy('grade_columns.classroom_id', 'recent')->get(), collect());

        $overdue = $safe(fn () => DbSchema::hasTable('remediations') ? DB::table('remediations')->whereIn('student_id', $all)->where('status', 'open')
            ->where('due_on', '<', $today->copy()->subDays(2)->toDateString())->selectRaw('student_id, count(*) as c')->groupBy('student_id')->pluck('c', 'student_id') : collect(), collect());

        $pendingWs = $safe(fn () => DbSchema::hasColumn('worksheet_submissions', 'graded_at') ? DB::table('worksheet_submissions')
            ->join('worksheets', 'worksheets.id', '=', 'worksheet_submissions.worksheet_id')
            ->whereIn('worksheets.classroom_id', $rooms->pluck('id'))->whereNotNull('worksheet_submissions.file_path')->whereNull('worksheet_submissions.graded_at')
            ->where('worksheet_submissions.submitted_at', '<', now()->subDays(5))
            ->selectRaw('worksheets.classroom_id as cid, count(*) as c')->groupBy('worksheets.classroom_id')->pluck('c', 'cid') : collect(), collect());

        $names = User::whereIn('id', $all)->pluck('name', 'id');
        $classes = [];
        $alerts = [];

        foreach ($rooms as $room) {
            $ids = $members[$room->id] ?? collect();
            $n = $ids->count();
            $trend = [];
            for ($w = 5; $w >= 0; $w--) {
                $trend[] = $n ? $this->pct($ids->filter(fn ($s) => ! empty($activeWeeks[$s][$w]))->count(), $n) : 0;
            }
            $now = end($trend);
            $base = array_slice($trend, 2, 3); // هفته‌های ۲ تا ۴ پیش
            $prev = $base ? (int) round(array_sum($base) / count($base)) : 0;

            $a = $acc->whereIn('student_id', $ids->all());
            $rN = (int) $a->where('recent', 1)->sum('n'); $rOk = (int) $a->where('recent', 1)->sum('ok');
            $pN = (int) $a->where('recent', 0)->sum('n'); $pOk = (int) $a->where('recent', 0)->sum('ok');
            $accNow = $rN >= 20 ? $this->pct($rOk, $rN) : null;
            $accPrev = $pN >= 20 ? $this->pct($pOk, $pN) : null;

            $at = $att->where('classroom_id', $room->id);
            $attN = (int) $at->sum('c');
            $absent = (int) $at->where('status', 'absent')->sum('c');
            $absRate = $attN >= 10 ? $this->pct($absent, $attN) : null;

            $g = $grades->where('cid', $room->id);
            $gNow = optional($g->firstWhere('recent', 1));
            $gPrev = optional($g->firstWhere('recent', 0));
            $gradeNow = ($gNow->n ?? 0) >= 5 ? (int) round($gNow->avg) : null;
            $gradePrev = ($gPrev->n ?? 0) >= 5 ? (int) round($gPrev->avg) : null;

            $inactive = $ids->filter(fn ($s) => empty($lastActive[$s]) || $lastActive[$s] < $today->copy()->subDays(10)->toDateString())
                ->map(fn ($s) => $names[$s] ?? '—')->values();
            $backlog = $ids->filter(fn ($s) => ($overdue[$s] ?? 0) >= 3)->count();

            $base = ['classroom_id' => $room->id, 'classroom' => $room->name, 'teacher' => $room->teacher?->name, 'teacher_id' => $room->teacher_id];
            $add = function (string $level, string $kind, string $title, string $detail, string $tip) use (&$alerts, $base) {
                $alerts[] = $base + compact('level', 'kind', 'title', 'detail', 'tip');
            };
            $fa = fn ($v) => Jalali::fa((string) $v);

            if ($n >= 3) {
                if ($prev >= 25 && $now <= $prev * 0.7) {
                    $add($now <= $prev * 0.5 ? 'crit' : 'warn', 'participation', 'افتِ مشارکت',
                        'دانش‌آموزانِ فعال در ۷ روزِ اخیر: ' . $fa($now) . '٪ (میانگینِ هفته‌های قبل ' . $fa($prev) . '٪).',
                        'با معلم درباره‌ی برنامه‌ی این هفته صحبت کنید؛ یک مأموریتِ کوتاه یا «مسابقه‌ی زنده» مشارکت را سریع بالا می‌برد.');
                } elseif ($now < 20 && $prev < 25 && array_sum($trend) > 0) {
                    $add('info', 'participation', 'مشارکتِ کمِ مداوم', 'فقط ' . $fa($now) . '٪ از دانش‌آموزان این هفته فعال بوده‌اند.',
                        'یادآوری به والدین (گزارشِ هفتگی) و فعال‌کردنِ مأموریت‌های روزانه پیشنهاد می‌شود.');
                }
                if ($accNow !== null && $accPrev !== null && $accPrev - $accNow >= 10) {
                    $add($accPrev - $accNow >= 20 ? 'crit' : 'warn', 'mastery', 'افتِ تسلط',
                        'دقتِ پاسخ‌ها در ۲ هفته‌ی اخیر ' . $fa($accNow) . '٪ (قبلاً ' . $fa($accPrev) . '٪).',
                        'احتمالاً مبحثِ تازه سخت بوده؛ مرورِ هدفمند یا یک جلسه‌ی رفعِ اشکال پیشنهاد می‌شود.');
                } elseif ($accNow !== null && $accNow < 50) {
                    $add('warn', 'mastery', 'تسلطِ پایین', 'دقتِ پاسخ‌ها در ۲ هفته‌ی اخیر فقط ' . $fa($accNow) . '٪ است.',
                        'سطحِ سؤال‌ها یا سرعتِ پیش‌روی را با معلم بررسی کنید.');
                }
                if ($gradeNow !== null && $gradePrev !== null && $gradePrev - $gradeNow >= 10) {
                    $add('warn', 'grades', 'افتِ نمره‌ها', 'میانگینِ نمره‌های اخیر ' . $fa($gradeNow) . '٪ (قبلاً ' . $fa($gradePrev) . '٪).',
                        'نمره‌های پایین را در «دفترِ نمره» ببینید و برای دانش‌آموزانِ نیازمند برنامه‌ی جبرانی بگذارید.');
                }
                if ($absRate !== null && $absRate >= 15) {
                    $add($absRate >= 25 ? 'crit' : 'warn', 'attendance', 'غیبتِ زیاد', 'نرخِ غیبت در ۷ روزِ اخیر ' . $fa($absRate) . '٪.',
                        'با خانواده‌ها تماس بگیرید؛ شاید بیماریِ فصلی یا مشکلِ رفت‌وآمد باشد.');
                }
                if ($backlog >= max(2, (int) ceil($n * 0.3))) {
                    $add('info', 'backlog', 'انباشتِ مرورها', $fa($backlog) . ' دانش‌آموز ۳ مرورِ عقب‌افتاده یا بیشتر دارند.',
                        'معلم می‌تواند فاصله‌ی مرورها را در «مرورِ اشتباه‌ها» سبک‌تر کند.');
                }
                if ($inactive->count() >= max(2, (int) ceil($n * 0.25))) {
                    $add('warn', 'inactive', 'دانش‌آموزانِ غایب از سایت', $fa($inactive->count()) . ' نفر بیش از ۱۰ روز هیچ فعالیتی نداشته‌اند.',
                        'یک پیام به والدینِ همین دانش‌آموزان (سامانه‌ی پیامک) بفرستید.');
                }
            }
            if (($pendingWs[$room->id] ?? 0) >= 5) {
                $add('info', 'grading', 'کاربرگ‌های تصحیح‌نشده', $fa($pendingWs[$room->id]) . ' کاربرگ بیش از ۵ روز منتظرِ تصحیح است.',
                    'بازخوردِ دیر انگیزه‌ی بچه‌ها را کم می‌کند؛ به معلم یادآوری کنید.');
            }

            $classAlerts = collect($alerts)->where('classroom_id', $room->id);
            $classes[] = $base + [
                'students' => $n, 'trend' => $trend, 'active' => $now, 'active_prev' => $prev,
                'accuracy' => $accNow, 'accuracy_prev' => $accPrev, 'absence' => $absRate, 'grade' => $gradeNow,
                'inactive' => $inactive->take(8)->all(), 'inactive_count' => $inactive->count(),
                'level' => $classAlerts->isEmpty() ? 'ok' : $classAlerts->sortByDesc(fn ($x) => self::LEVELS[$x['level']])->first()['level'],
            ];
        }

        usort($alerts, fn ($x, $y) => self::LEVELS[$y['level']] <=> self::LEVELS[$x['level']]);

        return [
            'alerts' => $alerts,
            'classes' => collect($classes)->sortByDesc(fn ($c) => self::LEVELS[$c['level']] ?? 0)->values()->all(),
            'counts' => ['crit' => collect($alerts)->where('level', 'crit')->count(), 'warn' => collect($alerts)->where('level', 'warn')->count(),
                'info' => collect($alerts)->where('level', 'info')->count()],
            'at' => Jalali::format(now(), true) . ' ' . Jalali::fa(now()->format('H:i')),
        ];
    }

    /** روزی یک بار: هشدارهای جدی/مهم را به مدیر(ان) خبر می‌دهد. */
    public function notifyDaily(int $schoolId): void
    {
        if (! Cache::add('early-warning-notified:' . $schoolId . ':' . now()->toDateString(), 1, 86400)) return;
        $data = $this->forSchool($schoolId);
        $serious = collect($data['alerts'])->whereIn('level', ['crit', 'warn']);
        if ($serious->isEmpty()) return;
        $admins = User::role(Roles::SCHOOL_ADMIN)->where('school_id', $schoolId)->pluck('id')->all();
        if (! $admins) return;
        $classes = $serious->pluck('classroom')->unique();
        $ann = Announcement::create([
            'school_id' => $schoolId, 'sender_id' => $admins[0], 'audience' => 'personal',
            'title' => '🚨 هشدارِ زودهنگام: ' . Jalali::fa((string) $classes->count()) . ' کلاس نیاز به توجه دارد',
            'body' => $serious->take(5)->map(fn ($a) => '• ' . $a['classroom'] . ' — ' . $a['title'] . ': ' . $a['detail'])->implode("\n"),
            'link' => '/school/early-warning',
        ]);
        $ann->recipients()->sync($admins);
    }
}
