<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Support\Jalali;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** مدیر مدرسه: گزارش عملکردِ آزمون‌های همه‌ی کلاس‌ها. */
class ExamOverviewController extends Controller
{
    public function index(Request $request): Response
    {
        $classes = Classroom::with('teacher:id,name')->get();

        $exams = Assignment::whereIn('classroom_id', $classes->pluck('id'))
            ->whereIn('type', ['exam', 'quiz'])->with('classroom:id,name')->latest()->get()
            ->map(function ($a) use ($classes) {
                $subs = $a->submissions()->get();
                $percents = $subs->map(fn ($s) => $s->max_score ? $s->score / $s->max_score * 100 : 0);
                $roster = $classes->firstWhere('id', $a->classroom_id);
                $totalStudents = $roster ? $roster->students()->count() : 0;
                return [
                    'id' => $a->id, 'title' => $a->title,
                    'class' => $a->classroom?->name,
                    'teacher' => $roster?->teacher?->name,
                    'jdate' => Jalali::format($a->created_at),
                    'total' => $totalStudents,
                    'taken' => $subs->count(),
                    'avg' => $percents->count() ? (int) round($percents->avg()) : null,
                    'pass' => $percents->count() ? (int) round($percents->filter(fn ($p) => $p >= 50)->count() / $percents->count() * 100) : null,
                ];
            })->values();

        $summary = [
            'exams' => $exams->count(),
            'submissions' => $exams->sum('taken'),
            'avg' => $exams->whereNotNull('avg')->avg('avg') ? (int) round($exams->whereNotNull('avg')->avg('avg')) : 0,
        ];

        return Inertia::render('SchoolAdmin/ExamReports', ['exams' => $exams, 'summary' => $summary]);
    }
}
