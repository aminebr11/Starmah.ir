<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\User;
use App\Services\VisitAnalytics;
use App\Support\Roles;
use App\Support\VisitTracker;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** گزارشِ بازدید و مشارکتِ معلم‌ها، دانش‌آموزان و والدینِ یک مدرسه. */
class VisitReportController extends Controller
{
    public function index(Request $request, VisitAnalytics $va): Response
    {
        $school = $request->user()->school;
        abort_unless($school, 404);
        $days = VisitAnalytics::period($request->query('days', 30));

        $students = $va->studentRows($va->studentsOf(collect(), $school->id), $days);
        $teachers = $va->teacherRows($school, $days, $students);
        $parents = $va->parentRows($students->pluck('id'), $days);
        $admins = User::role(Roles::SCHOOL_ADMIN)->where('school_id', $school->id)->get();

        $online = now()->subMinutes(VisitTracker::ONLINE_MINUTES);
        $onlineNow = $students->where('online', true)->map(fn ($r) => ['name' => $r['name'], 'role' => 'دانش‌آموز', 'where' => $r['class']])
            ->concat($teachers->where('online', true)->map(fn ($r) => ['name' => $r['name'], 'role' => 'معلم', 'where' => $r['classes']]))
            ->concat($parents->where('online', true)->map(fn ($r) => ['name' => $r['name'], 'role' => 'ولی', 'where' => $r['children']]))
            ->concat($admins->filter(fn ($a) => $a->last_seen_at && $a->last_seen_at->gte($online))->map(fn ($a) => ['name' => $a->name, 'role' => 'مدیر', 'where' => '']))
            ->values();

        return Inertia::render('SchoolAdmin/Visits', [
            'days' => $days, 'periods' => VisitAnalytics::PERIODS,
            'summary' => [
                'students' => $va->summarize($students, $days),
                'teachers' => $va->summarize($teachers, $days),
                'parents' => $va->summarize($parents, $days),
            ],
            'students' => $students, 'teachers' => $teachers, 'parents' => $parents,
            'classes' => $va->groupRanking($students, 'class_id', 'class'),
            'teams' => $va->groupRanking($students, 'theme_id', 'team', 'team_emoji'),
            'onlineNow' => $onlineNow,
            'series' => $va->dailySeries($days, null, false, $school->id),
            'hours' => $va->hours($days, $school->id),
            'sections' => $va->topSections($days, $school->id),
            'devices' => $va->devices($days, $school->id, false),
            'classOptions' => Classroom::where('school_id', $school->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
