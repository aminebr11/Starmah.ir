<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\StudentGradesController;
use App\Services\AnalyticsService;
use App\Services\CrossSubjectService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * کارنامه‌ی یکپارچه‌ی دانش‌آموز — چهار بخشِ قبلاً پراکنده (خلاصه/تحلیل، درس‌به‌درس،
 * نمرات کلاسی، آزمون هوشمند) در یک صفحه‌ی تب‌دار. منطقِ هر بخش از کنترلر/سرویسِ
 * موجودِ خودش بازاستفاده می‌شود تا تکراری ساخته نشود.
 */
class ReportHubController extends Controller
{
    public function __invoke(
        Request $request,
        AnalyticsService $analytics,
        CrossSubjectService $cross,
        StudentReportController $report,
        StudentGradesController $grades,
        SmartExamController $smart,
    ): Response {
        $user = $request->user();

        return Inertia::render('Student/Report', [
            'tab'          => $request->query('tab', 'overview'),
            'report'       => $report->buildReport($user, $analytics),
            'crossSubject' => $cross->forStudent($user),
            'grades'       => $grades->gradesData($user),
            'smart'        => $smart->performanceData($user, false), // بدونِ فراخوانیِ AI روی هر بار بارگذاری
        ]);
    }
}
