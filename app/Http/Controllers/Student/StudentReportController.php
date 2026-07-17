<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use App\Models\DisciplineRecord;
use App\Models\User;
use App\Models\XpEntry;
use App\Support\Jalali;
use App\Support\LevelConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * صفحه‌ی گزارش‌ها و نمودارها — تحلیل کامل عملکرد دانش‌آموز
 * برای خودِ دانش‌آموز و برای والدین (آگاهی از وضعیت فرزند و کمک به پیشرفت).
 */
class StudentReportController extends Controller
{
    public function __invoke(Request $request, \App\Services\AnalyticsService $analytics, \App\Services\CrossSubjectService $cross): Response
    {
        $user = $request->user();

        return Inertia::render('Student/Reports', array_merge(
            $this->buildReport($user, $analytics),
            ['crossSubject' => $cross->forStudent($user)],
        ));
    }

    /** بستهٔ کامل داده‌های تحلیلی یک دانش‌آموز. */
    public function buildReport(User $user, \App\Services\AnalyticsService $analytics): array
    {
        $classroom = $user->classrooms()->with('teacher')->first();
        $xp = $user->totalXp();
        $levelXp = LevelConfig::xpPerLevel($user->school_id);
        $level = LevelConfig::levelOf($xp, $levelXp);

        // ---- تسلط بر مهارت‌ها (نقاط قوت/ضعف) ----
        $skills = $user->skillMastery()->with('skill')->get()
            ->map(fn ($m) => [
                'name' => $m->skill?->name ?? 'مهارت',
                'mastery' => (int) $m->mastery,
                'attempts' => (int) $m->attempts,
            ])->filter(fn ($s) => $s['attempts'] > 0)->sortByDesc('mastery')->values();

        $strengths = $skills->take(4)->values();
        $weaknesses = $skills->reverse()->take(4)->filter(fn ($s) => $s['mastery'] < 70)->values();
        $avgMastery = $skills->count() ? (int) round($skills->avg('mastery')) : 0;

        // ---- امتیاز بر اساس نوع فعالیت (بازی/آزمون/تکلیف/پادکست) ----
        $summary = $analytics->studentSummary($user);

        // ---- نتایج آزمون‌ها ----
        $exams = AssignmentSubmission::where('student_id', $user->id)
            ->with('assignment:id,title,type')
            ->whereHas('assignment', fn ($q) => $q->whereIn('type', ['exam', 'quiz']))
            ->latest('submitted_at')->limit(20)->get()
            ->map(fn ($s) => [
                'title' => $s->assignment?->title ?? 'آزمون',
                'percent' => $s->max_score ? (int) round($s->score / $s->max_score * 100) : null,
                'score' => $s->score, 'max' => $s->max_score,
                'date' => $s->submitted_at ? Jalali::format($s->submitted_at) : null,
            ])->filter(fn ($e) => $e['percent'] !== null)->values();
        $examAvg = $exams->count() ? (int) round($exams->avg('percent')) : null;

        // ---- انضباط ----
        $stars = (int) DisciplineRecord::where('student_id', $user->id)->where('points', '>', 0)->sum('points');
        $warns = (int) abs(DisciplineRecord::where('student_id', $user->id)->where('points', '<', 0)->sum('points'));

        // ---- روند امتیاز ۶ هفته‌ی اخیر ----
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $start = now()->subWeeks($i)->startOfWeek();
            $end = now()->subWeeks($i)->endOfWeek();
            $sum = (int) XpEntry::where('student_id', $user->id)
                ->whereBetween('created_at', [$start, $end])->sum('amount');
            $trend[] = [
                'label' => $i === 0 ? 'این هفته' : ($i === 1 ? 'هفته‌ی قبل' : Jalali::format($start)),
                'value' => $sum,
            ];
        }

        // ---- توصیه‌های والدین (متن پویا بر اساس داده) ----
        $tips = $this->parentTips($avgMastery, $examAvg, $warns, $summary['week_points'] ?? 0, $weaknesses);

        return [
            'student'   => ['name' => $user->name, 'classroom' => $classroom?->name, 'teacher' => $classroom?->teacher?->name],
            'overview'  => [
                'xp' => $xp, 'level' => $level, 'levelXp' => $levelXp,
                'avgMastery' => $avgMastery, 'examAvg' => $examAvg,
                'stars' => $stars, 'warns' => $warns, 'badges' => $user->badges()->count(),
                'weekPoints' => $summary['week_points'] ?? 0,
                'examCount' => $exams->count(),
            ],
            'skills'     => $skills,
            'strengths'  => $strengths,
            'weaknesses' => $weaknesses,
            'byType'     => $summary['by_type'] ?? [],
            'exams'      => $exams,
            'trend'      => $trend,
            'tips'       => $tips,
            'printedAt'  => Jalali::format(now(), true),
        ];
    }

    private function parentTips(int $avgMastery, ?int $examAvg, int $warns, int $weekPoints, $weaknesses): array
    {
        $tips = [];

        if ($avgMastery >= 80) {
            $tips[] = ['icon' => '🌟', 'tone' => 'good', 'text' => 'تسلط فرزند شما بر مهارت‌ها بسیار خوب است. با تشویق و هدف‌گذاری تازه، این روند را حفظ کنید.'];
        } elseif ($avgMastery >= 50) {
            $tips[] = ['icon' => '📈', 'tone' => 'mid', 'text' => 'تسلط در سطح متوسط است. تمرین منظم روزانه (۱۵ دقیقه) به پیشرفت محسوس کمک می‌کند.'];
        } elseif ($avgMastery > 0) {
            $tips[] = ['icon' => '🤝', 'tone' => 'low', 'text' => 'فرزند شما به همراهی بیشتر نیاز دارد. کنارش بنشینید و تمرین‌ها را با هم مرور کنید.'];
        }

        if ($weaknesses->isNotEmpty()) {
            $names = $weaknesses->pluck('name')->take(3)->implode('، ');
            $tips[] = ['icon' => '🎯', 'tone' => 'mid', 'text' => "تمرکز این هفته روی این مهارت‌ها مفید است: {$names}."];
        }

        if ($examAvg !== null && $examAvg < 50) {
            $tips[] = ['icon' => '📚', 'tone' => 'low', 'text' => 'میانگین آزمون‌ها پایین است؛ مرور درس پیش از هر آزمون و رفع اشکال با معلم را توصیه می‌کنیم.'];
        } elseif ($examAvg !== null && $examAvg >= 80) {
            $tips[] = ['icon' => '🏆', 'tone' => 'good', 'text' => 'عملکرد آزمون‌ها عالی است — آفرین! می‌توانید آزمون‌های سطح بالاتر را امتحان کنید.'];
        }

        if ($warns > 0) {
            $tips[] = ['icon' => '💬', 'tone' => 'low', 'text' => 'چند مورد انضباطی ثبت شده؛ گفت‌وگوی دوستانه درباره‌ی رفتار کلاسی می‌تواند کمک‌کننده باشد.'];
        }

        if ($weekPoints > 0) {
            $tips[] = ['icon' => '⚡', 'tone' => 'good', 'text' => "این هفته {$weekPoints} امتیاز کسب شده — فعالیت خوبی داشته! تشویقش کنید."];
        } else {
            $tips[] = ['icon' => '⏰', 'tone' => 'mid', 'text' => 'این هفته فعالیتی ثبت نشده؛ یک یادآوری کوتاه برای ورود و تمرین انگیزه‌بخش است.'];
        }

        return $tips;
    }
}
