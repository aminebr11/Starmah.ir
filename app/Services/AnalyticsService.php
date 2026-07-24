<?php

namespace App\Services;

use App\Models\ActivityAward;
use App\Models\ClassActivity;
use App\Models\Classroom;
use App\Models\School;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Support\Facades\DB;

/**
 * موتور گزارش‌گیری و تحلیل عملکرد در همه‌ی سطوح:
 * دانش‌آموز، کلاس (معلم)، مدرسه (مدیر + تحلیل هر معلم)، پلتفرم (ادمین کل).
 */
class AnalyticsService
{
    /* ---------------- کلاس (گزارش معلم) ---------------- */
    public function classroomReport(Classroom $classroom): array
    {
        $students = $classroom->students()->with('theme')->get();

        $perStudent = $students->map(fn ($s) => [
            'id' => $s->id, 'name' => $s->name, 'theme_id' => $s->theme_id,
            'group' => $s->theme?->name, 'emoji' => $s->theme?->emoji,
            'xp' => $s->totalXp(),
            'mastery' => (int) round($s->skillMastery()->avg('mastery') ?? 0),
            'activities' => ActivityAward::where('student_id', $s->id)->count(),
        ])->sortByDesc('xp')->values();

        // امتیازِ دستیِ گروهی برای همین کلاس
        $teamBonus = \App\Models\TeamPoint::where('classroom_id', $classroom->id)
            ->selectRaw('theme_id, SUM(amount) as s')->groupBy('theme_id')->pluck('s', 'theme_id');

        // امتیاز هر تیم (اعضا + امتیازِ گروهی)
        $byGroup = $perStudent->whereNotNull('group')->groupBy('group')->map(fn ($g, $name) => [
            'name' => $name, 'emoji' => $g->first()['emoji'],
            'total' => $g->sum('xp') + (int) ($teamBonus[$g->first()['theme_id']] ?? 0),
            'count' => $g->count(),
        ])->sortByDesc('total')->values();

        // امتیاز بر اساس نوع فعالیت
        $byType = DB::table('activity_awards')
            ->join('class_activities', 'class_activities.id', '=', 'activity_awards.class_activity_id')
            ->where('class_activities.classroom_id', $classroom->id)
            ->groupBy('class_activities.type')
            ->select('class_activities.type', DB::raw('SUM(activity_awards.points) as points'), DB::raw('COUNT(*) as cnt'))
            ->get()->map(fn ($r) => ['type' => $r->type, 'label' => ClassActivity::typeLabel($r->type), 'points' => (int) $r->points, 'count' => (int) $r->cnt]);

        $studentIds = $students->pluck('id');
        $activeWeek = ActivityAward::whereIn('student_id', $studentIds)
            ->where('created_at', '>=', now()->subDays(7))->distinct('student_id')->count('student_id');

        return [
            'totals' => [
                'students'   => $students->count(),
                'points'     => $perStudent->sum('xp'),
                'activities' => ClassActivity::where('classroom_id', $classroom->id)->count(),
                'engagement' => $students->count() ? (int) round($activeWeek / $students->count() * 100) : 0,
                'avg_mastery'=> (int) round($perStudent->avg('mastery') ?? 0),
            ],
            'by_group'    => $byGroup,
            'by_type'     => $byType,
            'per_student' => $perStudent,
            'top'         => $perStudent->take(5),
            'needs_help'  => $perStudent->sortBy('xp')->take(3)->values(),
        ];
    }

    /* ---------------- مدرسه (گزارش مدیر + تحلیل هر معلم) ---------------- */
    public function schoolReport(School $school): array
    {
        $teachers = User::role(Roles::TEACHER)->where('school_id', $school->id)->get();

        $perTeacher = $teachers->map(function ($t) {
            $class = Classroom::where('teacher_id', $t->id)->first();
            $studentIds = $class ? $class->students()->pluck('users.id') : collect();
            $points = ActivityAward::whereIn('student_id', $studentIds)->sum('points');
            $activities = ClassActivity::where('teacher_id', $t->id)->count();
            $activeWeek = ActivityAward::whereIn('student_id', $studentIds)
                ->where('created_at', '>=', now()->subDays(7))->distinct('student_id')->count('student_id');
            $count = $studentIds->count();

            return [
                'id' => $t->id, 'name' => $t->name,
                'class' => $class?->name, 'students' => $count,
                'activities' => $activities,
                'points' => (int) $points,
                'engagement' => $count ? (int) round($activeWeek / $count * 100) : 0,
                // امتیاز عملکرد ساده: ترکیب فعالیت + درگیری
                'score' => min(100, $activities * 5 + ($count ? (int) round($activeWeek / $count * 60) : 0)),
            ];
        })->sortByDesc('score')->values();

        return [
            'totals' => [
                'teachers' => $teachers->count(),
                'students' => User::role(Roles::STUDENT)->where('school_id', $school->id)->count(),
                'classes'  => Classroom::where('school_id', $school->id)->count(),
                'points'   => (int) DB::table('xp_ledger')->join('users', 'users.id', '=', 'xp_ledger.student_id')
                    ->where('users.school_id', $school->id)->sum('amount'),
            ],
            'per_teacher' => $perTeacher,
        ];
    }

    /* ---------------- پلتفرم (گزارش ادمین کل) ---------------- */
    public function platformReport(): array
    {
        $perSchool = School::withCount(['classrooms'])->get()->map(function ($s) {
            $students = User::role(Roles::STUDENT)->where('school_id', $s->id)->count();
            $teachers = User::role(Roles::TEACHER)->where('school_id', $s->id)->count();
            $points = (int) DB::table('xp_ledger')->join('users', 'users.id', '=', 'xp_ledger.student_id')
                ->where('users.school_id', $s->id)->sum('amount');
            return [
                'id' => $s->id, 'name' => $s->name, 'city' => $s->city,
                'plan' => $s->plan, 'status' => $s->status,
                'students' => $students, 'teachers' => $teachers,
                'classes' => $s->classrooms_count, 'points' => $points,
            ];
        })->sortByDesc('points')->values();

        return [
            'totals' => [
                'schools'  => School::count(),
                'students' => User::role(Roles::STUDENT)->count(),
                'teachers' => User::role(Roles::TEACHER)->count(),
                'points'   => (int) DB::table('xp_ledger')->sum('amount'),
                'activities' => ClassActivity::count(),
            ],
            'per_school' => $perSchool,
        ];
    }

    /* ---------------- روندها (نمودارهای BI) ---------------- */

    /**
     * سریِ روزانه‌ی امتیاز برای مجموعه‌ای از دانش‌آموزان (نمودارِ روند).
     * خروجی: ردیف‌های [date, label(شمسی), value] برای $days روزِ آخر — روزهای بدونِ امتیاز صفر.
     */
    public function dailyXpSeries($studentIds, int $days = 28): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $rows = DB::table('xp_ledger')->whereIn('student_id', $studentIds)
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as d, SUM(amount) as s')
            ->groupBy('d')->pluck('s', 'd');

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i);
            $key = $day->toDateString();
            $out[] = [
                'date'  => $key,
                'label' => \App\Support\Jalali::format($day),
                'value' => (int) ($rows[$key] ?? 0),
            ];
        }

        return $out;
    }

    /** نقشه‌ی گرمای فعالیت: روزِ هفته (شنبه..جمعه) × هفته‌های اخیر — تعدادِ ثبت‌های امتیاز. */
    public function activityHeatmap($studentIds, int $weeks = 6): array
    {
        // شنبه‌ی شروعِ بازه (هفته‌ی ایرانی از شنبه است)
        $start = now()->startOfDay();
        while ($start->dayOfWeek !== 6) { // Carbon: Saturday = 6
            $start->subDay();
        }
        $start->subWeeks($weeks - 1);

        $rows = DB::table('xp_ledger')->whereIn('student_id', $studentIds)
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')->pluck('c', 'd');

        $grid = [];
        for ($w = 0; $w < $weeks; $w++) {
            $week = [];
            for ($d = 0; $d < 7; $d++) {
                $day = $start->copy()->addWeeks($w)->addDays($d);
                $week[] = $day->isFuture() ? null : (int) ($rows[$day->toDateString()] ?? 0);
            }
            $grid[] = $week;
        }

        return ['weeks' => $grid, 'days' => ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج']];
    }

    /* ---------------- گزارشِ فرزند (برای والدین) ---------------- */

    /** گزارشِ کاملِ یک فرزند برای والد: امتیاز، روند، ترکیب، تسلط، حضور، انضباط، جایگاه + توصیه. */
    public function childReport(User $student): array
    {
        $classroom = $student->classrooms()->with('teacher:id,name')->first();
        $summary = $this->studentSummary($student);
        $trend = $this->dailyXpSeries([$student->id], 28);
        $heat = $this->activityHeatmap([$student->id], 6);

        // جایگاه در کلاس
        $rank = null; $classSize = null; $classAvgWeek = null;
        if ($classroom) {
            $peers = $classroom->students()->get()->map(fn ($s) => ['id' => $s->id, 'xp' => $s->totalXp()])->sortByDesc('xp')->values();
            $classSize = $peers->count();
            $i = $peers->search(fn ($p) => $p['id'] === $student->id);
            $rank = $i === false ? null : $i + 1;
            $peerIds = $peers->pluck('id');
            $classAvgWeek = $classSize ? (int) round(
                (int) DB::table('xp_ledger')->whereIn('student_id', $peerIds)
                    ->where('created_at', '>=', now()->subDays(7))->sum('amount') / $classSize
            ) : null;
        }

        // حضور و غیاب (۳۰ روزِ اخیر)
        $att = DB::table('attendance_records')->where('student_id', $student->id)
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        // انضباط (۳۰ روزِ اخیر)
        $discipline = DB::table('discipline_records')->where('student_id', $student->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw("SUM(CASE WHEN points >= 0 THEN 1 ELSE 0 END) as pos, SUM(CASE WHEN points < 0 THEN 1 ELSE 0 END) as neg")
            ->first();

        $mastery = (int) round($student->skillMastery()->avg('mastery') ?? 0);
        $weekXp = (int) $summary['week_points'];
        $prevWeekXp = (int) DB::table('xp_ledger')->where('student_id', $student->id)
            ->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])->sum('amount');

        return [
            'id'        => $student->id,
            'name'      => $student->name,
            'classroom' => $classroom?->name,
            'teacher'   => $classroom?->teacher?->name,
            'team'      => $student->theme ? ['name' => $student->theme->name, 'emoji' => $student->theme->emoji] : null,
            'xp_total'  => $student->totalXp(),
            'week_xp'   => $weekXp,
            'prev_week_xp' => $prevWeekXp,
            'class_avg_week' => $classAvgWeek,
            'rank'      => $rank,
            'class_size'=> $classSize,
            'mastery'   => $mastery,
            'by_type'   => $summary['by_type'],
            'trend'     => $trend,
            'heatmap'   => $heat,
            'attendance'=> [
                'present' => (int) ($att['present'] ?? 0), 'absent' => (int) ($att['absent'] ?? 0),
                'late'    => (int) ($att['late'] ?? 0),   'excused' => (int) ($att['excused'] ?? 0),
            ],
            'discipline'=> ['pos' => (int) ($discipline->pos ?? 0), 'neg' => (int) ($discipline->neg ?? 0)],
            'advice'    => $this->childAdvice($weekXp, $prevWeekXp, $classAvgWeek, $mastery, (int) ($att['absent'] ?? 0), (int) ($discipline->neg ?? 0)),
        ];
    }

    /** توصیه‌ی خودکارِ قابل‌فهم برای والد — قاعده‌محور، بدونِ نیاز به AI. */
    private function childAdvice(int $weekXp, int $prevWeekXp, ?int $classAvgWeek, int $mastery, int $absents, int $negDiscipline): array
    {
        $advice = [];
        if ($weekXp > $prevWeekXp && $weekXp > 0) {
            $advice[] = ['tone' => 'ok', 'text' => 'روندِ این هفته صعودی است — فرزندتان فعال‌تر از هفته‌ی قبل بوده؛ تشویقش کنید. 🎉'];
        } elseif ($weekXp < $prevWeekXp) {
            $advice[] = ['tone' => 'warn', 'text' => 'فعالیتِ این هفته نسبت به هفته‌ی قبل کم شده — چند دقیقه بازی یا مأموریتِ مشترک می‌تواند دوباره راهش بیندازد.'];
        }
        if ($classAvgWeek !== null && $weekXp < $classAvgWeek) {
            $advice[] = ['tone' => 'warn', 'text' => 'امتیازِ این هفته از میانگینِ کلاس پایین‌تر است؛ همراهی در مأموریت‌های روزانه کمک می‌کند.'];
        } elseif ($classAvgWeek !== null && $weekXp >= $classAvgWeek && $weekXp > 0) {
            $advice[] = ['tone' => 'ok', 'text' => 'فرزندتان بالاتر از میانگینِ کلاس فعالیت می‌کند — عالی است! 👏'];
        }
        if ($mastery > 0 && $mastery < 50) {
            $advice[] = ['tone' => 'warn', 'text' => 'تسلطِ مهارتی هنوز جای رشد دارد؛ تکرارِ بازی‌های درسی (حتی تمرینی) به تثبیتِ یادگیری کمک می‌کند.'];
        }
        if ($absents >= 2) {
            $advice[] = ['tone' => 'warn', 'text' => "در ۳۰ روزِ اخیر {$absents} غیبت ثبت شده — در صورتِ ابهام با معلم در میان بگذارید."];
        }
        if ($negDiscipline > 0) {
            $advice[] = ['tone' => 'warn', 'text' => 'موردِ انضباطیِ منفی ثبت شده است؛ گفت‌وگوی آرام در خانه بهترین قدم است.'];
        }
        if (! $advice) {
            $advice[] = ['tone' => 'ok', 'text' => 'وضعیتِ کلی خوب و پایدار است — همراهی‌تان را ادامه دهید. 🌟'];
        }

        return $advice;
    }

    /* ---------------- دانش‌آموز (خلاصه‌ی تحلیلی) ---------------- */
    public function studentSummary(User $student): array
    {
        $rows = DB::table('xp_ledger')
            ->leftJoin('activity_awards', function ($j) {
                $j->on('activity_awards.id', '=', 'xp_ledger.source_id')
                  ->where('xp_ledger.source_type', '=', ActivityAward::class);
            })
            ->leftJoin('class_activities', 'class_activities.id', '=', 'activity_awards.class_activity_id')
            ->where('xp_ledger.student_id', $student->id)
            ->select('class_activities.type as ctype', 'xp_ledger.reason', 'xp_ledger.amount')
            ->get();

        // سرفصل‌بندی: آزمون‌ها (آنلاین/هوشمند) زیرِ «📝 آزمون‌ها»، بقیه طبق نوع فعالیت
        $agg = [];
        foreach ($rows as $r) {
            if ($r->ctype) {
                $label = ClassActivity::typeLabel($r->ctype);
            } elseif (mb_strpos($r->reason ?? '', 'آزمون') !== false) {
                $label = '📝 آزمون‌ها';
            } elseif (mb_strpos($r->reason ?? '', 'بازی') !== false) {
                $label = '🎮 بازی‌ها';
            } else {
                $label = '✨ سایر';
            }
            $agg[$label] = ($agg[$label] ?? 0) + (int) $r->amount;
        }
        $byType = collect($agg)->map(fn ($points, $label) => ['label' => $label, 'points' => $points])
            ->sortByDesc('points')->values();

        // امتیاز ۷ روز اخیر (روند)
        $trend = DB::table('xp_ledger')->where('student_id', $student->id)
            ->where('created_at', '>=', now()->subDays(7))->sum('amount');

        return ['by_type' => $byType, 'week_points' => (int) $trend];
    }
}
