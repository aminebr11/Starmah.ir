<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Services\VisitAnalytics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** گزارشِ بازدید و مشارکتِ دانش‌آموزان (و والدینِ) کلاس‌های همین معلم. */
class VisitReportController extends Controller
{
    public function index(Request $request, VisitAnalytics $va): Response
    {
        $teacher = $request->user();
        $days = VisitAnalytics::period($request->query('days', 30));
        $classes = Classroom::where('teacher_id', $teacher->id)->orderBy('name')->get(['id', 'name']);

        $students = $va->studentRows($va->studentsOf($classes->pluck('id')), $days);
        $parents = $va->parentRows($students->pluck('id'), $days);
        $ids = $students->pluck('id');

        return Inertia::render('Teacher/Visits', [
            'days' => $days, 'periods' => VisitAnalytics::PERIODS,
            'summary' => ['students' => $va->summarize($students, $days), 'parents' => $va->summarize($parents, $days)],
            'students' => $students, 'parents' => $parents,
            'classes' => $va->groupRanking($students, 'class_id', 'class'),
            'teams' => $va->groupRanking($students, 'theme_id', 'team', 'team_emoji'),
            'series' => $va->dailySeries($days, $ids),
            'classOptions' => $classes,
        ]);
    }
}
