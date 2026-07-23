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
