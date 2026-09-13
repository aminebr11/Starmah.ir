<?php

namespace App\Support;

use App\Models\AttendanceRecord;
use App\Models\Classroom;
use App\Models\DisciplineRecord;
use App\Models\Grade;
use App\Models\SmartExamAttempt;
use App\Models\User;
use App\Models\WorksheetSubmission;
use App\Models\XpEntry;
use App\Services\AnalyticsService;
use App\Services\CrossSubjectService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * پرونده‌ی تحلیلیِ یک دانش‌آموز — همه‌ی چیزی که «معلمِ راهنما» باید بداند.
 *
 * چرا جدا از دستیار: دستیار فقط باید حرف بزند؛ داده و تحلیل جای دیگری
 * ساخته می‌شود تا هم با کلیدِ هوش مصنوعی و هم بدونِ آن یک عددِ واحد
 * گزارش شود، و هر جای دیگرِ سایت هم بتواند از همین استفاده کند.
 *
 * هیچ‌جا داده‌ی دانش‌آموزِ دیگری برنمی‌گردد جز رتبه (که عددِ خودِ کاربر است).
 */
class StudentInsight
{
    public function __construct(
        private CrossSubjectService $cross,
        private AnalyticsService $analytics,
    ) {}

    /** @return array<string,mixed> */
    public function profile(User $s): array
    {
        $xp = $s->totalXp();
        $per = LevelConfig::xpPerLevel($s->school_id);
        $level = LevelConfig::levelOf($xp, $per);
        $intoLevel = $per > 0 ? $xp % $per : 0;
        $classroom = $s->classrooms()->with('teacher')->first();

        return [
            'name'       => $s->name,
            'classroom'  => $classroom?->name,
            'teacher'    => $classroom?->teacher?->name,
            'team'       => $s->theme?->name,
            'xp'         => $xp,
            'level'      => $level,
            'to_next'    => max(0, $per - $intoLevel),
            'week_xp'    => (int) XpEntry::where('student_id', $s->id)->where('created_at', '>=', now()->subDays(7))->sum('amount'),
            'prev_week_xp' => (int) XpEntry::where('student_id', $s->id)
                ->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])->sum('amount'),
            'badges'     => $s->badges()->count(),
            'rank'       => $this->rank($s, $classroom),
            'subjects'   => $this->subjects($s),
            'grades'     => $this->classGrades($s),
            'exams'      => $this->exams($s),
            'missions'   => $this->missions($s),
            'attendance' => $this->attendance($s),
            'discipline' => $this->discipline($s),
            'worksheets' => WorksheetSubmission::where('student_id', $s->id)->whereNotNull('file_path')->count(),
            'xp_by_type' => ($this->analytics->studentSummary($s)['by_type'] ?? collect())->take(5)->all(),
        ];
    }

    /** رتبه‌ی خودِ کاربر در کلاس — فقط عددِ خودش، نه فهرستِ بقیه. */
    private function rank(User $s, ?Classroom $classroom): array
    {
        if (! $classroom) {
            return ['in_class' => null, 'of' => 0];
        }
        $scores = $classroom->students()->get()->map(fn ($x) => ['id' => $x->id, 'xp' => $x->totalXp()])
            ->sortByDesc('xp')->values();
        $i = $scores->search(fn ($r) => $r['id'] === $s->id);

        return ['in_class' => $i === false ? null : $i + 1, 'of' => $scores->count()];
    }

    /** درصدِ هر درس از آزمون/بازی/مأموریت. */
    private function subjects(User $s): array
    {
        $data = $this->cross->forStudent($s);

        return [
            'overall' => (int) ($data['overall'] ?? 0),
            'rows'    => collect($data['subjects'] ?? [])
                ->filter(fn ($r) => $r['pct'] !== null)
                ->map(fn ($r) => ['subject' => $r['subject'], 'pct' => (int) $r['pct']])
                ->sortBy('pct')->values()->all(),
        ];
    }

    /** نمره‌های دفترِ نمره (کلاسی). */
    private function classGrades(User $s): array
    {
        if (! Schema::hasTable('grades')) {
            return [];
        }

        return Grade::with('column:id,title,lesson,max,score_type,type')
            ->where('student_id', $s->id)->latest()->limit(12)->get()
            ->map(function ($g) {
                $c = $g->column;
                $type = $c?->score_type ?: $c?->type;

                return [
                    'title'  => trim(($c?->lesson ? $c->lesson . ' — ' : '') . ($c?->title ?? 'نمره')),
                    'value'  => $type === 'numeric'
                        ? ($g->score !== null ? $g->score . ' از ' . ($c?->max ?: 20) : '—')
                        : ($g->text ?: '—'),
                    'pct'    => ($type === 'numeric' && $g->score !== null && ($c?->max ?: 0) > 0)
                        ? (int) round($g->score / $c->max * 100) : null,
                    'date'   => Jalali::format($g->created_at),
                ];
            })->all();
    }

    /** آزمون‌های هوشمندِ تمام‌شده. */
    private function exams(User $s): array
    {
        if (! Schema::hasTable('smart_exam_attempts')) {
            return [];
        }

        return SmartExamAttempt::with('exam:id,title,subject')
            ->where('student_id', $s->id)->where('status', 'completed')->where('max_score', '>', 0)
            ->latest()->limit(6)->get()
            ->map(fn ($a) => [
                'title'   => $a->exam?->title ?? 'آزمون',
                'subject' => $a->exam?->subject,
                'pct'     => (int) round($a->score / max(1, $a->max_score) * 100),
                'date'    => Jalali::format($a->created_at),
            ])->all();
    }

    /** مأموریت‌های امروز + کارنامه‌ی ۳۰ روزِ اخیر. */
    private function missions(User $s): array
    {
        $pending = MissionAccess::pendingToday($s);
        $done30 = DB::table('mission_completions')->where('student_id', $s->id)
            ->where('play_date', '>=', now()->subDays(30)->toDateString())->count();

        return [
            'pending_today' => $pending->count(),
            'pending_titles' => $pending->take(3)->pluck('title')->all(),
            'pending_xp'    => (int) $pending->sum('xp_reward'),
            'done_30d'      => $done30,
        ];
    }

    /** حضور و غیابِ ۳۰ روزِ اخیر. */
    private function attendance(User $s): array
    {
        if (! Schema::hasTable('attendance_records')) {
            return [];
        }
        $rows = AttendanceRecord::where('student_id', $s->id)
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->get()->groupBy('status')->map->count();

        return [
            'present' => (int) ($rows['present'] ?? 0),
            'absent'  => (int) ($rows['absent'] ?? 0),
            'late'    => (int) ($rows['late'] ?? 0),
            'excused' => (int) ($rows['excused'] ?? 0),
        ];
    }

    /** تشویق/تذکرِ ۳۰ روزِ اخیر. */
    private function discipline(User $s): array
    {
        $rows = DisciplineRecord::where('student_id', $s->id)
            ->where('created_at', '>=', now()->subDays(30))->get();

        return [
            'plus'  => (int) $rows->where('points', '>=', 0)->sum('points'),
            'minus' => (int) $rows->where('points', '<', 0)->sum('points'),
            'count' => $rows->count(),
        ];
    }

    /**
     * تحلیل و پیشنهاد — همان چیزی که یک معلمِ راهنما می‌گوید.
     *
     * @return array{headline:string,strengths:array<int,string>,weak:array<int,array{subject:string,pct:int,tip:string}>,actions:array<int,string>,trend:string}
     */
    public function analysis(array $p): array
    {
        $rows = $p['subjects']['rows'] ?? [];
        $weakRows = collect($rows)->filter(fn ($r) => $r['pct'] < 60)->take(3);
        $strong = collect($rows)->filter(fn ($r) => $r['pct'] >= 75)->sortByDesc('pct')->take(3)
            ->map(fn ($r) => $r['subject'] . ' (' . Jalali::fa((string) $r['pct']) . '٪)')->values()->all();

        $trend = match (true) {
            $p['week_xp'] > $p['prev_week_xp'] => 'صعودی',
            $p['week_xp'] < $p['prev_week_xp'] => 'نزولی',
            default => 'ثابت',
        };

        $overall = $p['subjects']['overall'] ?? 0;
        $headline = match (true) {
            $overall >= 85 => 'عالی کار می‌کنی! سطحت بالاست و فقط باید همین ریتم را نگه داری.',
            $overall >= 70 => 'وضعیتت خوب است؛ با کمی تمرینِ هدفمند می‌توانی عالی شوی.',
            $overall >= 50 => 'وضعیتت متوسط است؛ چند درس با تمرینِ منظم زود بالا می‌آیند.',
            $overall > 0   => 'فعلاً عقب‌تری، ولی با برنامه‌ی کوچکِ روزانه جبران می‌شود — قدم‌به‌قدم.',
            default        => 'هنوز فعالیتِ نمره‌داری ثبت نشده؛ با اولین مأموریت یا آزمون شروع کن.',
        };

        // پیشنهادِ هر درسِ ضعیف را به ابزارِ واقعیِ همین سایت گره می‌زنیم
        $weak = $weakRows->map(fn ($r) => [
            'subject' => $r['subject'],
            'pct'     => $r['pct'],
            'tip'     => "برای «{$r['subject']}»: اول پادکست/ویدیوی همان درس را در «محتوای کلاس» کامل ببین، "
                . 'بعد یک بازی یا آزمونِ هوشمندِ همان درس را بزن و در پایان کاربرگش را پر کن و بفرست. '
                . 'همین سه قدم هم درصدت را بالا می‌برد هم امتیاز می‌دهد.',
        ])->values()->all();

        $actions = [];
        if (($p['missions']['pending_today'] ?? 0) > 0) {
            $actions[] = 'امروز ' . Jalali::fa((string) $p['missions']['pending_today']) . ' مأموریت انجام‌نشده داری — '
                . Jalali::fa((string) $p['missions']['pending_xp']) . ' امتیاز روی زمین مانده.';
        }
        if ($weak) {
            $actions[] = 'ضعیف‌ترین درست «' . $weak[0]['subject'] . '» است؛ امروز فقط ۲۰ دقیقه روی همان بگذار.';
        }
        if (($p['to_next'] ?? 0) > 0) {
            $actions[] = 'تا سطحِ بعدی ' . Jalali::fa((string) $p['to_next']) . ' امتیاز فاصله داری.';
        }
        if (($p['attendance']['absent'] ?? 0) >= 3) {
            $actions[] = 'در ۳۰ روزِ اخیر ' . Jalali::fa((string) $p['attendance']['absent']) . ' غیبت داشتی؛ جبرانِ درس‌های آن روزها را از «محتوای کلاس» بگیر.';
        }
        if (! $actions) {
            $actions[] = 'همه‌چیز مرتب است — یک آزمونِ هوشمند بزن تا نقاطِ ضعفت دقیق‌تر مشخص شود.';
        }

        return compact('headline', 'strong', 'weak', 'actions', 'trend') + ['strengths' => $strong];
    }

    /** متنِ خوانا از پرونده — هم برای LLM، هم برای پاسخِ محلی. */
    public function asText(array $p, array $a): string
    {
        $fa = fn ($n) => Jalali::fa((string) $n);
        $L = [];
        $L[] = "نام: {$p['name']}" . ($p['classroom'] ? " · کلاس: {$p['classroom']}" : '')
            . ($p['teacher'] ? " · معلم: {$p['teacher']}" : '') . ($p['team'] ? " · تیم: {$p['team']}" : '');
        $L[] = "امتیاز کل: {$fa($p['xp'])} · سطح: {$fa($p['level'])} · تا سطحِ بعد: {$fa($p['to_next'])} امتیاز";
        $L[] = "امتیازِ این هفته: {$fa($p['week_xp'])} (هفته‌ی پیش: {$fa($p['prev_week_xp'])}) — روند: {$a['trend']}";
        if ($p['rank']['in_class']) {
            $L[] = "رتبه در کلاس: {$fa($p['rank']['in_class'])} از {$fa($p['rank']['of'])}";
        }
        $L[] = "نشان‌ها: {$fa($p['badges'])} · کاربرگِ فرستاده‌شده: {$fa($p['worksheets'])}";

        if ($p['subjects']['rows']) {
            $L[] = 'میانگینِ کلِ درس‌ها: ' . $fa($p['subjects']['overall']) . '٪';
            $L[] = 'درس‌به‌درس: ' . collect($p['subjects']['rows'])
                ->map(fn ($r) => $r['subject'] . ' ' . $fa($r['pct']) . '٪')->implode('، ');
        } else {
            $L[] = 'هنوز فعالیتِ نمره‌داری (آزمون/بازی/مأموریت) ثبت نشده.';
        }
        if ($p['grades']) {
            $L[] = 'نمراتِ کلاسیِ اخیر: ' . collect($p['grades'])->take(5)
                ->map(fn ($g) => $g['title'] . ': ' . $g['value'])->implode('، ');
        }
        if ($p['exams']) {
            $L[] = 'آزمون‌های هوشمندِ اخیر: ' . collect($p['exams'])
                ->map(fn ($e) => $e['title'] . ' ' . $fa($e['pct']) . '٪')->implode('، ');
        }
        $L[] = 'مأموریتِ امروز: ' . ($p['missions']['pending_today'] > 0
            ? $fa($p['missions']['pending_today']) . ' مانده (' . implode('، ', $p['missions']['pending_titles']) . ')'
            : 'همه انجام شده ✅')
            . ' · ۳۰ روزِ اخیر: ' . $fa($p['missions']['done_30d']) . ' مأموریت';
        if ($p['attendance']) {
            $at = $p['attendance'];
            $L[] = "حضور و غیابِ ۳۰ روز: حاضر {$fa($at['present'])} · غایب {$fa($at['absent'])} · تأخیر {$fa($at['late'])}";
        }
        if ($p['discipline']['count'] > 0) {
            $L[] = "انضباط ۳۰ روز: تشویق +{$fa($p['discipline']['plus'])} · تذکر {$fa($p['discipline']['minus'])}";
        }
        $L[] = 'ارزیابی: ' . $a['headline'];
        if ($a['strengths']) {
            $L[] = 'نقاطِ قوت: ' . implode('، ', $a['strengths']);
        }
        if ($a['weak']) {
            $L[] = 'نیاز به تمرین: ' . collect($a['weak'])->map(fn ($w) => $w['subject'] . ' ' . $fa($w['pct']) . '٪')->implode('، ');
        }

        return implode("\n", $L);
    }
}
