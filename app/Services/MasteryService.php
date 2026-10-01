<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * موتورِ «تسلط» — برآوردِ این‌که دانش‌آموز هر درس و هر مبحث را چقدر یاد گرفته.
 *
 * ── چرا بازنویسی شد ──────────────────────────────────────────────────
 * تسلطِ قبلی فقط از جدولِ قدیمیِ student_skill_mastery می‌آمد که هیچ‌کدام از
 * فعالیت‌های فعلی (آزمونِ هوشمند، بازیِ آموزشی، مأموریت، نمره‌ی معلم، تکلیف)
 * در آن نمی‌نوشتند؛ پس همه‌جا «۰٪» نشان داده می‌شد.
 *
 * ── منطقِ علمی ───────────────────────────────────────────────────────
 * ۱) شواهد: هر پاسخ یک «مشاهده» است (درست=۱، غلط=۰، تشریحی/نمره = کسرِ ۰ تا ۱)
 *    با درس، مبحث، تاریخ و منبع.
 * ۲) وزنِ منبع: نمره‌ی معلم (ارزشیابیِ مستقیم) قوی‌ترین است، آزمون بعد از آن،
 *    مأموریت و تکلیف، و بازی (با راهنما و تلاشِ دوباره) کمی سبک‌تر.
 * ۳) دشواری (ایده‌ی نظریه‌ی سؤال-پاسخ): درست‌جواب‌دادنِ سؤالِ سخت شاهدِ قوی‌ترِ
 *    تسلط است و غلط‌زدنِ سؤالِ آسان شاهدِ قوی‌ترِ ضعف.
 * ۴) فراموشی (منحنیِ ابینگهاوس): وزنِ هر مشاهده با نیمه‌عمرِ ۴۵ روز کم می‌شود؛
 *    کارِ امروز از کارِ دو ماه پیش گویاتر است، ولی هیچ‌وقت کمتر از ۱۵٪ نمی‌شود.
 * ۵) برآوردِ بیزی (توزیعِ بتا با پیشینِ یکنواخت): تسلط = (Σوزن×نتیجه + ۱) / (Σوزن + ۲).
 *    با شواهدِ کم، عدد به ۵۰٪ نزدیک می‌ماند و با شواهدِ بیشتر به عملکردِ واقعی
 *    می‌رسد؛ یک سؤالِ غلط کسی را «صفر» نمی‌کند و یک سؤالِ درست «۱۰۰».
 * ۶) اطمینان: هرچه شواهد بیشتر، اطمینان بیشتر. زیرِ ۳ مشاهده عددی نمایش
 *    داده نمی‌شود و «هنوز کافی نیست» گفته می‌شود (نه صفر).
 * ۷) سطح‌ها بر پایه‌ی «یادگیری در حدِ تسلط» (بلوم، معیارِ ۸۰–۹۰٪).
 */
class MasteryService
{
    public const MIN_OBS = 3;
    private const HALF_LIFE_DAYS = 45;
    private const SOURCE_W = ['grade' => 2.5, 'exam' => 1.3, 'homework' => 1.1, 'mission' => 1.0, 'legacy' => 1.0, 'game' => 0.8];
    public const SOURCE_LABELS = ['exam' => 'آزمون', 'game' => 'بازی', 'mission' => 'مأموریت', 'grade' => 'نمره‌ی معلم', 'homework' => 'تکلیف', 'legacy' => 'تمرین'];

    public const LEVELS = [
        ['key' => 'master', 'min' => 85, 'label' => 'مسلط', 'emoji' => '🏆', 'color' => '#1fa463',
            'kid' => 'این درس را خیلی خوب بلدی! حالا می‌توانی به دوستانت هم کمک کنی.'],
        ['key' => 'proficient', 'min' => 70, 'label' => 'نزدیک به تسلط', 'emoji' => '🚀', 'color' => '#3d7bf0',
            'kid' => 'خیلی خوب پیش می‌روی؛ با کمی تمرینِ بیشتر مسلط می‌شوی.'],
        ['key' => 'developing', 'min' => 50, 'label' => 'در حالِ یادگیری', 'emoji' => '🌱', 'color' => '#e8862e',
            'kid' => 'داری یاد می‌گیری! مبحث‌های ضعیف‌تر را دوباره تمرین کن.'],
        ['key' => 'beginning', 'min' => 0, 'label' => 'نیاز به تمرین', 'emoji' => '💪', 'color' => '#e8505b',
            'kid' => 'این درس تمرینِ بیشتری لازم دارد؛ از معلمت کمک بگیر و بازی‌ها و مأموریت‌هایش را انجام بده.'],
    ];

    public static function level(?int $m): ?array
    {
        if ($m === null) return null;
        foreach (self::LEVELS as $l) {
            if ($m >= $l['min']) return $l;
        }

        return end(self::LEVELS);
    }

    /* ================= API ================= */

    /** گزارشِ کاملِ یک دانش‌آموز (یک دقیقه کش). */
    public function forStudent(int $studentId): array
    {
        return Cache::remember("mastery:v1:$studentId", 60, fn () => $this->summarize($this->evidence([$studentId])[$studentId] ?? []));
    }

    /** خلاصه برای چند دانش‌آموز (فهرستِ کلاس) — شناسه => گزارش. */
    public function forStudents(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if (! $ids) return [];
        $ev = $this->evidence($ids);
        $out = [];
        foreach ($ids as $id) $out[$id] = $this->summarize($ev[$id] ?? []);

        return $out;
    }

    /** فقط عددِ کلی (یا null) — برای جاهایی که یک عدد لازم است. */
    public function overall(int $studentId): ?int
    {
        return $this->forStudent($studentId)['overall'];
    }

    public static function forget(int $studentId): void
    {
        Cache::forget("mastery:v1:$studentId");
    }

    /* ================= جمع‌آوریِ شواهد ================= */

    /** @return array<int, array<int, array{s:string,t:?string,c:float,w:float,at:Carbon,src:string}>> */
    private function evidence(array $ids): array
    {
        $E = [];
        $push = function ($sid, $subject, $topic, float $c, float $w, $at, string $src, ?string $diff = null) use (&$E) {
            $c = max(0.0, min(1.0, $c));
            $w *= self::SOURCE_W[$src] ?? 1.0;
            // دشواری: درستِ سخت سنگین‌تر، غلطِ آسان سنگین‌تر
            if ($diff === 'hard') $w *= $c >= 0.5 ? 1.25 : 0.8;
            elseif ($diff === 'easy') $w *= $c >= 0.5 ? 0.85 : 1.2;
            $E[$sid][] = ['s' => self::subject($subject), 't' => self::clean($topic), 'c' => $c, 'w' => $w, 'at' => Carbon::parse($at ?: now()), 'src' => $src];
        };

        // ۱) آزمونِ هوشمند — هر سؤال یک مشاهده
        if (Schema::hasTable('smart_exam_answers')) {
            DB::table('smart_exam_answers as a')
                ->join('smart_exam_attempts as t', 't.id', '=', 'a.attempt_id')
                ->join('smart_exams as e', 'e.id', '=', 't.smart_exam_id')
                ->leftJoin('smart_exam_questions as q', 'q.id', '=', 'a.question_id')
                ->whereIn('t.student_id', $ids)
                ->select('t.student_id', 'e.subject', 'e.topic as etopic', 'q.topic', 'q.difficulty', 'q.points', 'a.correct', 'a.awarded', 'a.created_at', 't.finished_at')
                ->orderBy('a.id')->get()
                ->each(function ($r) use ($push) {
                    if ($r->correct !== null) $c = (float) $r->correct;
                    elseif ($r->awarded !== null && $r->points > 0) $c = $r->awarded / $r->points;
                    else return; // تشریحیِ تصحیح‌نشده
                    $push($r->student_id, $r->subject, $r->topic ?: $r->etopic, $c, 1.0, $r->finished_at ?: $r->created_at, 'exam', $r->difficulty);
                });
        }

        // ۲) بازیِ آموزشی — پاسخِ هر سؤال در progress ذخیره است
        $attempts = DB::table('edu_game_attempts as a')->join('edu_games as g', 'g.id', '=', 'a.edu_game_id')
            ->whereIn('a.student_id', $ids)->select('a.student_id', 'a.edu_game_id', 'a.progress', 'a.score', 'a.max_score', 'a.completed_at', 'a.updated_at', 'g.subject', 'g.topic', 'g.difficulty')->get();
        $qs = DB::table('edu_game_questions')->whereIn('edu_game_id', $attempts->pluck('edu_game_id')->unique())
            ->orderBy('sort')->orderBy('id')->get(['edu_game_id', 'topic', 'difficulty'])->groupBy('edu_game_id');
        foreach ($attempts as $a) {
            $answers = json_decode((string) $a->progress, true)['answers'] ?? null;
            $gq = ($qs[$a->edu_game_id] ?? collect())->values();
            if (is_array($answers) && $answers) {
                foreach ($answers as $i => $ans) {
                    if (! isset($ans['correct'])) continue;
                    $q = $gq[$i] ?? null;
                    $push($a->student_id, $a->subject, $q->topic ?? $a->topic, $ans['correct'] ? 1 : 0, 1.0, $a->completed_at ?: $a->updated_at, 'game', $q->difficulty ?? $a->difficulty);
                }
            } elseif ($a->max_score > 0 && $a->completed_at) {
                // تلاش‌های قدیمی که جزئیات ندارند: کسرِ امتیاز با وزنِ تعدادِ سؤال
                $n = max(1, min(10, $gq->count()));
                $push($a->student_id, $a->subject, $a->topic, $a->score / $a->max_score, $n, $a->completed_at, 'game', $a->difficulty);
            }
        }

        // ۳) مأموریت‌ها — نتیجه‌ی هر روز (score از total)
        DB::table('mission_completions as c')->join('missions as m', 'm.id', '=', 'c.mission_id')
            ->whereIn('c.student_id', $ids)->where('c.total', '>', 0)
            ->select('c.student_id', 'c.score', 'c.total', 'c.created_at', 'm.subject', 'm.lesson_no', 'm.difficulty', 'm.title')->get()
            ->each(fn ($r) => $push($r->student_id, $r->subject ?: self::guessSubject($r->title), $r->lesson_no ? 'درسِ ' . $r->lesson_no : null,
                $r->score / $r->total, min(10, (int) $r->total), $r->created_at, 'mission', $r->difficulty));

        // ۴) نمره‌ی معلم در دفترِ نمره
        $desc = ['خیلی خوب' => 1.0, 'خوب' => 0.8, 'قابل قبول' => 0.6, 'نیاز به تلاش' => 0.35];
        DB::table('grades as g')->join('grade_columns as c', 'c.id', '=', 'g.grade_column_id')
            ->whereIn('g.student_id', $ids)
            ->select('g.student_id', 'g.score', 'g.text', 'g.created_at', 'c.max', 'c.type', 'c.score_type', 'c.lesson', 'c.topic', 'c.title', 'c.graded_at')->get()
            ->each(function ($r) use ($push, $desc) {
                $type = $r->score_type ?: $r->type;
                if ($type === 'numeric') {
                    if ($r->score === null || ! ($r->max > 0)) return;
                    $c = $r->score / $r->max;
                } else {
                    if (! isset($desc[$r->text])) return; // «غایب» یا خالی شاهدِ یادگیری نیست
                    $c = $desc[$r->text];
                }
                $push($r->student_id, $r->lesson ?: self::guessSubject($r->title), $r->topic, $c, 1.0, $r->graded_at ?: $r->created_at, 'grade');
            });

        // ۵) تکلیف و آزمونِ کلاسی
        DB::table('assignment_submissions as s')->join('assignments as a', 'a.id', '=', 's.assignment_id')
            ->whereIn('s.student_id', $ids)->whereNotNull('s.submitted_at')
            ->select('s.student_id', 's.score', 's.max_score', 's.accuracy', 's.submitted_at', 'a.title', 'a.config', 'a.question_count')->get()
            ->each(function ($r) use ($push) {
                $c = $r->max_score > 0 ? $r->score / $r->max_score : ($r->accuracy !== null ? $r->accuracy / 100 : null);
                if ($c === null) return;
                $cfg = json_decode((string) $r->config, true) ?: [];
                $push($r->student_id, $cfg['subject'] ?? self::guessSubject($r->title), $cfg['topic'] ?? null, $c, max(1, min(10, (int) $r->question_count)), $r->submitted_at, 'homework');
            });

        // ۶) تمرین‌های قدیمیِ مهارت‌محور
        if (Schema::hasTable('activity_results') && Schema::hasTable('skills')) {
            $q = DB::table('activity_results as r')->leftJoin('skills as k', 'k.id', '=', 'r.skill_id')->leftJoin('topics as tp', 'tp.id', '=', 'k.topic_id');
            $hasSubjects = Schema::hasTable('subjects');
            if ($hasSubjects) $q->leftJoin('subjects as sb', 'sb.id', '=', 'tp.subject_id');
            $q->whereIn('r.student_id', $ids)
                ->select('r.student_id', 'r.score', 'r.max_score', 'r.accuracy', 'r.created_at', 'k.name as skill', 'tp.name as topic', $hasSubjects ? 'sb.name as subject' : DB::raw('NULL as subject'))
                ->get()->each(function ($r) use ($push) {
                    $c = $r->max_score > 0 ? $r->score / $r->max_score : ($r->accuracy !== null ? $r->accuracy / 100 : null);
                    if ($c !== null) $push($r->student_id, $r->subject ?: self::guessSubject($r->topic . ' ' . $r->skill), $r->skill ?: $r->topic, $c, 1.0, $r->created_at, 'legacy');
                });
        }

        return $E;
    }

    /* ================= محاسبه ================= */

    private function decay(Carbon $at): float
    {
        $age = max(0, $at->diffInDays(now(), true));

        return max(0.15, 0.5 ** ($age / self::HALF_LIFE_DAYS));
    }

    /** برآوردِ بیزی + اطمینان برای یک دسته مشاهده. */
    private function estimate(array $obs): array
    {
        $sw = 0.0; $swc = 0.0; $raw = 0.0;
        foreach ($obs as $o) {
            $w = $o['w'] * $this->decay($o['at']);
            $sw += $w; $swc += $w * $o['c']; $raw += $o['c'];
        }
        $n = count($obs);
        $m = $n ? ($swc + 1) / ($sw + 2) : null;

        return [
            'mastery' => $n >= self::MIN_OBS ? (int) round($m * 100) : null,
            'raw' => $n ? (int) round($raw / $n * 100) : null, // درصدِ ساده‌ی درست (برای شفافیت)
            'n' => $n,
            'weight' => round($sw, 2),
            'confidence' => $n >= self::MIN_OBS ? (int) round($sw / ($sw + 6) * 100) : 0,
        ];
    }

    private function summarize(array $obs): array
    {
        $bySubject = [];
        foreach ($obs as $o) $bySubject[$o['s']][] = $o;

        $subjects = [];
        foreach ($bySubject as $name => $list) {
            $e = $this->estimate($list);
            // روند: دو هفته‌ی اخیر در برابرِ قبل از آن
            $recent = array_filter($list, fn ($o) => $o['at']->gte(now()->subDays(14)));
            $older = array_filter($list, fn ($o) => $o['at']->lt(now()->subDays(14)));
            $trend = null;
            if (count($recent) >= self::MIN_OBS && count($older) >= self::MIN_OBS) {
                $trend = $this->estimate(array_values($recent))['mastery'] - $this->estimate(array_values($older))['mastery'];
            }
            $topics = [];
            foreach (collect($list)->filter(fn ($o) => $o['t'])->groupBy('t') as $t => $tl) {
                $te = $this->estimate($tl->all());
                $topics[] = ['name' => $t, 'mastery' => $te['mastery'], 'raw' => $te['raw'], 'n' => $te['n'], 'level' => self::level($te['mastery'])];
            }
            usort($topics, fn ($a, $b) => ($a['mastery'] ?? 101) <=> ($b['mastery'] ?? 101));
            $sources = collect($list)->groupBy('src')->map(fn ($g) => count($g))->all();
            $last = collect($list)->max('at');

            $subjects[] = $e + [
                'name' => $name, 'level' => self::level($e['mastery']), 'trend' => $trend,
                'topics' => $topics, 'sources' => $sources, 'last' => $last?->toDateString(),
            ];
        }
        usort($subjects, fn ($a, $b) => ($b['mastery'] ?? -1) <=> ($a['mastery'] ?? -1) ?: $b['n'] <=> $a['n']);

        $rated = array_filter($subjects, fn ($s) => $s['mastery'] !== null);
        $overall = null;
        if ($rated) {
            $w = array_sum(array_map(fn ($s) => $s['weight'], $rated));
            $overall = (int) round(array_sum(array_map(fn ($s) => $s['mastery'] * $s['weight'], $rated)) / max(0.0001, $w));
        }
        $topicsAll = collect($subjects)->flatMap(fn ($s) => collect($s['topics'])->map(fn ($t) => $t + ['subject' => $s['name']]))
            ->filter(fn ($t) => $t['mastery'] !== null);

        return [
            'overall' => $overall,
            'level' => self::level($overall),
            'observations' => count($obs),
            'confidence' => $rated ? (int) round(array_sum(array_column($rated, 'confidence')) / count($rated)) : 0,
            'subjects' => $subjects,
            'strengths' => array_values(array_filter($subjects, fn ($s) => ($s['mastery'] ?? 0) >= 70)),
            'weaknesses' => array_values(array_reverse(array_filter($subjects, fn ($s) => $s['mastery'] !== null && $s['mastery'] < 70))),
            'weakTopics' => $topicsAll->sortBy('mastery')->filter(fn ($t) => $t['mastery'] < 70)->take(5)->values()->all(),
            'strongTopics' => $topicsAll->sortByDesc('mastery')->filter(fn ($t) => $t['mastery'] >= 85)->take(5)->values()->all(),
            'pending' => array_values(array_map(fn ($s) => ['name' => $s['name'], 'n' => $s['n']], array_filter($subjects, fn ($s) => $s['mastery'] === null))),
        ];
    }

    /* ================= کمکی ================= */

    private static function clean(?string $s): ?string
    {
        $s = trim(str_replace(["\u{200f}", "ي", "ك"], ["", "ی", "ک"], (string) $s));
        $s = preg_replace('/\s+/u', ' ', $s);

        return $s === '' ? null : $s;
    }

    /** یکسان‌سازیِ نامِ درس (مثلاً «مطالعات اجتماعی» و «اجتماعی» یکی‌اند). */
    public static function subject(?string $s): string
    {
        $s = self::clean($s);
        if (! $s) return 'سایرِ فعالیت‌ها';
        $map = [
            '/ریاض|حساب|هندسه/u' => 'ریاضی', '/علوم|تجربی/u' => 'علوم', '/املا/u' => 'املا', '/نگارش|انشا/u' => 'نگارش',
            '/فارسی|بخوانیم|خوانداری/u' => 'فارسی', '/اجتماع|مطالعات/u' => 'مطالعات اجتماعی', '/قرآن/u' => 'قرآن',
            '/هدیه|دینی|پیامها/u' => 'هدیه‌های آسمانی', '/انگلیس/u' => 'انگلیسی', '/عربی/u' => 'عربی',
            '/تفکر|پژوهش/u' => 'تفکر و پژوهش', '/هنر/u' => 'هنر', '/ورزش|تربیت بدنی/u' => 'ورزش', '/فناوری|رایانه/u' => 'کار و فناوری',
        ];
        foreach ($map as $re => $name) {
            if (preg_match($re, $s)) return $name;
        }

        return mb_substr($s, 0, 40);
    }

    private static function guessSubject(?string $title): ?string
    {
        $t = self::clean($title);
        if (! $t) return null;
        $s = self::subject($t);

        // اگر از عنوان درسی شناخته نشد، در «سایر» حساب شود نه با نامِ خودِ فعالیت
        return $s === mb_substr($t, 0, 40) ? null : $s;
    }
}
