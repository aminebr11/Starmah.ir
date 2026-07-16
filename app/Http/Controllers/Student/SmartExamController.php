<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\SmartExam;
use App\Models\SmartExamAnswer;
use App\Models\SmartExamAttempt;
use App\Models\SmartExamReward;
use App\Models\User;
use App\Services\GamificationService;
use App\Services\SmartExamAnalyticsService;
use App\Support\Jalali;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** آزمایشگاه هوشمند آزمون — سمت دانش‌آموز (اجرای امنِ آزمون + تلاش‌های مستقل). */
class SmartExamController extends Controller
{
    /** آزمون‌هایی که این دانش‌آموز مجاز به دیدن آن‌هاست. */
    private function visibleFor(User $user)
    {
        $classroomIds = $user->classrooms()->pluck('classrooms.id');
        $teacherIds = Classroom::whereIn('id', $classroomIds)->pluck('teacher_id')->filter()->unique();

        return SmartExam::whereIn('teacher_id', $teacherIds)
            ->where('status', 'published')
            ->with(['targets', 'questions'])
            ->withCount('questions')
            ->get()
            ->filter(fn ($e) => $this->targeted($e, $user, $classroomIds));
    }

    private function targeted(SmartExam $exam, User $user, $classroomIds): bool
    {
        if ($exam->targets->isEmpty()) {
            // بدون هدفِ صریح → همه‌ی دانش‌آموزانِ کلاسِ همان معلم
            return true;
        }
        return $exam->targets->contains(fn ($t) => $t->student_id === $user->id)
            || $exam->targets->contains(fn ($t) => $t->theme_id && $t->theme_id === $user->theme_id)
            || $exam->targets->contains(fn ($t) => $t->classroom_id && $classroomIds->contains($t->classroom_id));
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $exams = $this->visibleFor($user);
        $attempts = SmartExamAttempt::where('student_id', $user->id)
            ->whereIn('smart_exam_id', $exams->pluck('id'))->get()->groupBy('smart_exam_id');

        $cards = $exams->map(function ($e) use ($attempts) {
            $mine = $attempts->get($e->id) ?? collect();
            $best = $mine->sortByDesc('score')->first();
            $lastDone = $mine->whereIn('status', ['completed', 'needs_review'])->sortByDesc('id')->first();
            $inProgress = $mine->firstWhere('status', 'in_progress');
            $rules = $e->rules ?? [];
            $maxAttempts = (int) ($rules['attempts'] ?? 1);
            $completedCount = $mine->where('status', 'completed')->count();
            $live = $e->isLive();
            $notYet = $e->opens_at && now()->lessThan($e->opens_at); // زمان‌بندی‌شده برای آینده
            $status = ! $live
                ? ($e->closes_at && now()->greaterThan($e->closes_at) ? 'expired' : ($notYet ? 'scheduled' : 'locked'))
                : ($inProgress ? 'in_progress' : ($completedCount ? 'done' : 'new'));
            return [
                'id' => $e->id, 'title' => $e->title, 'subject' => $e->subject, 'topic' => $e->topic,
                'grade' => $e->grade, 'kind' => $e->kind, 'adaptive' => $e->adaptive,
                'count' => $e->questions_count, 'duration' => $rules['duration'] ?? null,
                'status' => $status,
                'attemptsLeft' => max(0, $maxAttempts - $completedCount),
                'lastScore' => $best?->score, 'lastMax' => $best?->max_score,
                'lastAttemptId' => $lastDone?->id,
                'opens' => $notYet ? Jalali::format($e->opens_at, true) : null,
                'closes' => $e->closes_at ? Jalali::format($e->closes_at, true) : null,
                'showResult' => (bool) ($rules['show_result'] ?? true),
            ];
        })->sortBy(fn ($c) => ['in_progress' => 0, 'new' => 1, 'done' => 2, 'locked' => 3, 'expired' => 4][$c['status']] ?? 9)->values();

        return Inertia::render('Student/SmartExams', ['exams' => $cards]);
    }

    /** کارنامه‌ی هوشمند: امتیاز و پیشرفتِ دانش‌آموز به تفکیک درس و سرفصل (نقاط قوت/ضعف). */
    public function performance(Request $request): Response
    {
        $user = $request->user();
        $attemptIds = SmartExamAttempt::where('student_id', $user->id)->where('status', '!=', 'in_progress')->pluck('id');

        $rows = SmartExamAnswer::whereIn('attempt_id', $attemptIds)
            ->join('smart_exam_questions as q', 'q.id', '=', 'smart_exam_answers.question_id')
            ->join('smart_exam_attempts as a', 'a.id', '=', 'smart_exam_answers.attempt_id')
            ->join('smart_exams as e', 'e.id', '=', 'a.smart_exam_id')
            ->selectRaw("COALESCE(NULLIF(e.subject,''),'عمومی') as subject, COALESCE(NULLIF(q.topic,''), NULLIF(e.topic,''),'عمومی') as topic, smart_exam_answers.correct as correct")
            ->get();

        // درس → سرفصل → درصد
        $subjects = [];
        foreach ($rows as $r) {
            $subjects[$r->subject] ??= ['name' => $r->subject, 'correct' => 0, 'total' => 0, 'topics' => []];
            $subjects[$r->subject]['total']++;
            $subjects[$r->subject]['topics'][$r->topic] ??= ['topic' => $r->topic, 'correct' => 0, 'total' => 0];
            $subjects[$r->subject]['topics'][$r->topic]['total']++;
            if ($r->correct) {
                $subjects[$r->subject]['correct']++;
                $subjects[$r->subject]['topics'][$r->topic]['correct']++;
            }
        }
        $out = collect($subjects)->map(function ($s) {
            $topics = collect($s['topics'])->map(fn ($t) => [
                'topic' => $t['topic'], 'pct' => $t['total'] ? (int) round($t['correct'] / $t['total'] * 100) : 0, 'total' => $t['total'],
            ])->sortByDesc('pct')->values();
            return [
                'name' => $s['name'],
                'pct' => $s['total'] ? (int) round($s['correct'] / $s['total'] * 100) : 0,
                'total' => $s['total'],
                'topics' => $topics,
                'strengths' => $topics->where('pct', '>=', 70)->pluck('topic')->take(4)->values(),
                'weaknesses' => $topics->where('pct', '<', 60)->sortBy('pct')->pluck('topic')->take(4)->values(),
            ];
        })->sortByDesc('total')->values();

        $overall = $rows->count() ? (int) round($rows->where('correct', true)->count() / $rows->count() * 100) : 0;
        $examCount = SmartExamAttempt::where('student_id', $user->id)->where('status', 'completed')->count();

        // روند: آزمون‌های انجام‌شده به‌تفکیک درس (برای دیدنِ سیرِ پیشرفت)
        $history = SmartExamAttempt::where('student_id', $user->id)
            ->whereIn('status', ['completed', 'needs_review'])
            ->with('exam:id,title,subject')
            ->latest('finished_at')->limit(40)->get()
            ->map(fn ($a) => [
                'exam' => $a->exam?->title,
                'subject' => $a->exam?->subject ?: 'عمومی',
                'percent' => $a->max_score ? (int) round($a->auto_score / $a->max_score * 100) : 0,
                'date' => Jalali::format($a->finished_at ?? $a->created_at),
            ])->values();
        $trend = $history->groupBy('subject')->map(fn ($g, $s) => [
            'subject' => $s,
            'exams' => $g->reverse()->values(), // قدیمی → جدید برای نمودار روند
            'avg' => (int) round($g->avg('percent')),
        ])->values();

        return Inertia::render('Student/SmartPerformance', [
            'student' => ['name' => $user->name],
            'subjects' => $out,
            'totalAnswered' => $rows->count(),
            'examCount' => $examCount,
            'overallPct' => $overall,
            'trend' => $trend,
            'aiSummary' => $this->aiSummary($user->name, $out, $overall, $examCount),
            'parent' => $this->parentGuidance($out, $overall, $examCount),
        ]);
    }

    /** خلاصه‌ی هوش مصنوعی از عملکرد — با کلیدِ ادمین از AI واقعی، وگرنه خلاصه‌ی محلیِ هوشمند. */
    private function aiSummary(string $name, $subjects, int $overall, int $examCount): string
    {
        if ($examCount === 0) {
            return "{$name} هنوز آزمونی نداده است. با شرکت در چند آزمونِ کوتاه، تحلیلِ دقیقی از نقاط قوت و ضعف در این‌جا نمایش داده می‌شود.";
        }
        $strong = collect($subjects)->flatMap(fn ($s) => collect($s['strengths'])->map(fn ($t) => "{$s['name']}:{$t}"))->take(4)->implode('، ');
        $weak = collect($subjects)->flatMap(fn ($s) => collect($s['weaknesses'])->map(fn ($t) => "{$s['name']}:{$t}"))->take(4)->implode('، ');

        // اگر کلیدِ هوش مصنوعی تنظیم شده باشد، تحلیلِ واقعی؛ وگرنه خلاصه‌ی محلیِ هوشمندِ متناسب با عملکرد
        $ai = app(\App\Services\AiContentService::class);
        if ($ai->isConfigured()) {
            $prompt = "دانش‌آموزی به نام «{$name}» در {$examCount} آزمونِ هوشمند شرکت کرده و میانگین درستیِ او {$overall}٪ است. "
                . ($strong ? "نقاط قوت: {$strong}. " : '')
                . ($weak ? "نقاط نیازمندِ تمرین: {$weak}. " : '')
                . 'یک تحلیلِ کوتاه، دلگرم‌کننده و راه‌گشا (۳ تا ۴ جمله، خطاب به خودِ دانش‌آموز) بنویس؛ فقط متنِ تحلیل، بدون عنوان و امضا.';
            try {
                $out = trim($ai->generate($prompt, "تحلیل عملکرد {$name}"));
                if ($out !== '') {
                    return $out;
                }
            } catch (\Throwable $e) {
                // به خلاصه‌ی محلی می‌رویم
            }
        }

        $tone = $overall >= 80 ? 'عملکردت واقعاً درخشان است' : ($overall >= 50 ? 'در مسیرِ خوبی هستی' : 'با کمی تمرینِ بیشتر پیشرفت می‌کنی');
        return "{$name} عزیز، {$tone}! میانگین درستیِ تو {$overall}٪ است."
            . ($weak ? " برای این هفته روی «{$weak}» تمرکز کن." : ' همین‌طور عالی ادامه بده!')
            . ($strong ? " نقطه‌ی قوتت ({$strong}) را هم قوی‌تر نگه‌دار." : '');
    }

    /** راهنمای والدین: ارزیابی کلی + توصیه‌های عملیِ حمایت از مسیر آموزشی. */
    private function parentGuidance($subjects, int $overall, int $examCount): array
    {
        $strong = collect($subjects)->flatMap(fn ($s) => collect($s['strengths'])->map(fn ($t) => "{$s['name']}: {$t}"))->take(6)->values();
        $weak = collect($subjects)->flatMap(fn ($s) => collect($s['weaknesses'])->map(fn ($t) => "{$s['name']}: {$t}"))->take(6)->values();

        $tips = [];
        if ($examCount === 0) {
            $tips[] = ['icon' => '🌱', 'tone' => 'mid', 'text' => 'فرزندتان هنوز آزمونی نداده است. با یک برنامه‌ی کوتاه و منظم، او را به شرکت در آزمون‌ها تشویق کنید.'];
        } else {
            if ($overall >= 80) {
                $tips[] = ['icon' => '🌟', 'tone' => 'good', 'text' => 'عملکرد کلی فرزندتان عالی است. با تشویق و هدف‌گذاریِ تازه، این انگیزه را زنده نگه دارید و او را به کمک به هم‌کلاسی‌ها ترغیب کنید.'];
            } elseif ($overall >= 50) {
                $tips[] = ['icon' => '📈', 'tone' => 'mid', 'text' => 'فرزندتان در مسیر پیشرفت است. ۱۵ تا ۲۰ دقیقه مرور روزانه و مرور اشتباهاتِ هر آزمون، تفاوت بزرگی ایجاد می‌کند.'];
            } else {
                $tips[] = ['icon' => '🤝', 'tone' => 'low', 'text' => 'فرزندتان به همراهیِ بیشتری نیاز دارد. کنارش بنشینید، بدون سرزنش سؤال‌های اشتباه را با هم مرور کنید و پیشرفت‌های کوچک را جشن بگیرید.'];
            }
        }
        if ($weak->isNotEmpty()) {
            $tips[] = ['icon' => '🎯', 'tone' => 'mid', 'text' => 'تمرکز این هفته را روی این حوزه‌ها بگذارید: ' . $weak->take(3)->implode('، ') . '. برای هرکدام چند تمرینِ ساده و کوتاه انتخاب کنید.'];
        }
        if ($strong->isNotEmpty()) {
            $tips[] = ['icon' => '💪', 'tone' => 'good', 'text' => 'نقاط قوت فرزندتان (' . $strong->take(3)->implode('، ') . ') را یادآوری کنید؛ اعتمادبه‌نفسِ ناشی از آن به بهبودِ حوزه‌های ضعیف کمک می‌کند.'];
        }
        $tips[] = ['icon' => '🗣️', 'tone' => 'mid', 'text' => 'به‌جای تمرکز روی «نمره»، درباره‌ی «چه چیزی یاد گرفتی؟» صحبت کنید تا یادگیری برایش لذت‌بخش بماند.'];
        $tips[] = ['icon' => '⏰', 'tone' => 'mid', 'text' => 'یک زمان و مکانِ آرامِ ثابت برای مطالعه تعیین کنید و از پاداش‌های کوچک (نه لزوماً مادی) برای تلاش — نه فقط نتیجه — استفاده کنید.'];

        return [
            'assessment' => $examCount === 0 ? 'شروع مسیر' : ($overall >= 80 ? 'عالی' : ($overall >= 50 ? 'رو به رشد' : 'نیازمند حمایت')),
            'strong' => $strong, 'weak' => $weak, 'tips' => $tips,
        ];
    }

    public function take(Request $request, SmartExam $smartExam): Response|RedirectResponse
    {
        $user = $request->user();
        $classroomIds = $user->classrooms()->pluck('classrooms.id');
        $smartExam->load('targets', 'questions');

        // ── امنیت دسترسی ──
        abort_unless($this->canAccess($smartExam, $user, $classroomIds), 403, 'این آزمون برای شما نیست.');
        if (! $smartExam->isLive()) {
            return redirect()->route('student.smart.index')->with('flash', 'این آزمون هم‌اکنون در دسترس نیست.');
        }

        $rules = $smartExam->rules ?? [];
        $maxAttempts = (int) ($rules['attempts'] ?? 1);
        $mine = SmartExamAttempt::where('smart_exam_id', $smartExam->id)->where('student_id', $user->id)->get();
        $completed = $mine->where('status', 'completed')->count();
        $open = $mine->firstWhere('status', 'in_progress');

        if (! $open && $completed >= $maxAttempts) {
            return redirect()->route('student.smart.index')->with('flash', 'سقفِ تعدادِ تلاش‌های مجاز تمام شده است.');
        }

        // تلاشِ باز (ادامه) یا تلاشِ جدید — هر تلاش رکوردِ مستقل
        $attempt = $open ?: SmartExamAttempt::create([
            'smart_exam_id' => $smartExam->id, 'student_id' => $user->id,
            'attempt_no' => $mine->count() + 1,
            'token' => (string) Str::uuid(),
            'token_expires_at' => now()->addMinutes(($rules['duration'] ?? 60) + 30),
            'started_at' => now(), 'status' => 'in_progress',
            'max_score' => (int) $smartExam->questions->sum('points'),
            'ip' => $request->ip(),
        ]);
        // توکن برای تلاشِ در حال انجام تازه شود
        if ($open && (! $attempt->token || now()->greaterThan($attempt->token_expires_at))) {
            $attempt->update(['token' => (string) Str::uuid(), 'token_expires_at' => now()->addMinutes(($rules['duration'] ?? 60) + 30)]);
        }

        // ترتیبِ ارائه: در حالتِ تطبیقی از آسان به دشوار؛ نگاشتِ i همیشه به سؤالِ اصلی گره خورده است
        $ordered = $smartExam->questions->values();
        $questions = $ordered->map(function ($q, $i) use ($rules) {
            $choices = collect($q->choices ?? [])->map(fn ($c) => ['value' => $c['value'] ?? ''])->values();
            if (! empty($rules['shuffle_choices'])) {
                $choices = $choices->shuffle()->values();
            }
            return ['i' => $i, 'type' => $q->type, 'prompt' => $q->prompt, 'media' => $q->media_path,
                'choices' => $choices, 'points' => $q->points,
                'diffRank' => ['easy' => 0, 'medium' => 1, 'hard' => 2][$q->difficulty] ?? 1];
        });
        if ($smartExam->adaptive) {
            // تطبیقی: آسان → دشوار (آزمون از سؤال‌های ساده شروع و به‌تدریج دشوارتر می‌شود)
            $questions = $questions->sortBy('diffRank')->values();
        } elseif (! empty($rules['shuffle'])) {
            $questions = $questions->shuffle()->values();
        }
        $questions = $questions->map(fn ($q) => collect($q)->except('diffRank'))->values();

        return Inertia::render('Student/SmartExamTake', [
            'exam' => ['id' => $smartExam->id, 'title' => $smartExam->title, 'subject' => $smartExam->subject,
                'rules' => $rules, 'onePerPage' => (bool) ($rules['one_per_page'] ?? true)],
            'token' => $attempt->token,
            'attemptId' => $attempt->id,
            'questions' => $questions,
            'saved' => $attempt->progress ?? [],
        ]);
    }

    /** ذخیره‌ی خودکار (ادامه‌ی آزمون بعد از قطع اینترنت). */
    public function save(Request $request, SmartExam $smartExam): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate(['token' => ['required', 'string'], 'progress' => ['required', 'array']]);
        $attempt = $this->tokenAttempt($smartExam, $user, $data['token']);
        abort_if(! $attempt, 403);
        if ($attempt->status === 'in_progress') {
            $attempt->update(['progress' => $data['progress']]);
        }
        return back();
    }

    public function submit(Request $request, SmartExam $smartExam, GamificationService $game): RedirectResponse
    {
        $user = $request->user();
        $smartExam->load('questions');
        $data = $request->validate([
            'token' => ['required', 'string'],
            'answers' => ['required', 'array'],
            'duration_sec' => ['nullable', 'integer', 'min:0'],
        ]);

        $attempt = $this->tokenAttempt($smartExam, $user, $data['token']);
        abort_if(! $attempt, 403, 'توکن معتبر نیست.');
        abort_if($attempt->status === 'completed', 409, 'این تلاش قبلاً ثبت شده است.'); // جلوگیری از ارسال دوباره

        $rules = $smartExam->rules ?? [];
        $answersById = collect($data['answers'])->keyBy('i');
        $questions = $smartExam->questions->values();

        $auto = 0; $autoMax = 0; $score = 0; $hasDesc = false;
        $attempt->answers()->delete();
        foreach ($questions as $i => $q) {
            $given = $answersById[$i]['value'] ?? null;
            $correct = null; $awarded = 0;
            if (in_array($q->type, ['mc', 'tf'], true)) {
                $autoMax += $q->points;
                $correctIdx = collect($q->choices ?? [])->search(fn ($c) => ! empty($c['correct']));
                $correctVal = $correctIdx !== false ? ($q->choices[$correctIdx]['value'] ?? null) : null;
                $correct = $given !== null && (string) $given === (string) $correctVal;
                if ($correct) { $auto += $q->points; $awarded = $q->points; $score += $q->points; }
            } elseif ($q->type === 'blank') {
                $autoMax += $q->points;
                $expected = is_array($q->answer) ? ($q->answer[0] ?? '') : (string) $q->answer;
                $correct = $given !== null && trim((string) $given) !== '' && mb_strtolower(trim((string) $given)) === mb_strtolower(trim($expected));
                if ($correct) { $auto += $q->points; $awarded = $q->points; $score += $q->points; }
            } else { // desc → تصحیح دستی
                $hasDesc = true;
            }
            SmartExamAnswer::create([
                'attempt_id' => $attempt->id, 'question_id' => $q->id, 'q_index' => $i,
                'value' => ['value' => $given], 'correct' => $correct, 'awarded' => $awarded,
            ]);
        }

        $maxScore = (int) $questions->sum('points');
        $attempt->update([
            'finished_at' => now(), 'duration_sec' => $data['duration_sec'] ?? 0,
            'score' => $score, 'auto_score' => $auto, 'max_score' => $maxScore,
            'status' => $hasDesc ? 'needs_review' : 'completed',
            'progress' => null,
        ]);

        // ── XP فقط یک‌بار (بهترین نتیجه) — idempotent با smart_exam_rewards ──
        $bestSoFar = SmartExamAttempt::where('smart_exam_id', $smartExam->id)->where('student_id', $user->id)
            ->where('status', 'completed')->max('score');
        $alreadyRewarded = SmartExamReward::whereIn('attempt_id',
            SmartExamAttempt::where('smart_exam_id', $smartExam->id)->where('student_id', $user->id)->pluck('id'))->exists();

        $xp = $autoMax ? (int) round($auto / $autoMax * 100) : 0;
        if (! $alreadyRewarded && ! $hasDesc && $xp > 0) {
            SmartExamReward::create(['attempt_id' => $attempt->id, 'student_id' => $user->id, 'xp' => $xp]);
            $attempt->update(['rewarded' => true]);
            $game->award($user, $xp, '🧪 آزمون هوشمند — ' . $smartExam->title, $smartExam->teacher, SmartExamReward::class, $attempt->id);
        }

        $msg = $hasDesc
            ? 'پاسخ‌هایت ثبت شد؛ بخشِ تشریحی توسط معلم بررسی می‌شود.'
            : "آزمون تمام شد — نتیجه: {$auto} از {$autoMax}" . ($alreadyRewarded ? ' (XP قبلاً محاسبه شده)' : " (+{$xp} امتیاز)");

        return redirect()->route('student.smart.result', [$smartExam->id, $attempt->id])->with('flash', $msg);
    }

    public function result(Request $request, SmartExam $smartExam, SmartExamAttempt $attempt, SmartExamAnalyticsService $analytics): Response|RedirectResponse
    {
        $user = $request->user();
        abort_unless($attempt->student_id === $user->id && $attempt->smart_exam_id === $smartExam->id, 403);
        $rules = $smartExam->rules ?? [];
        if (empty($rules['show_result'] ?? true)) {
            return redirect()->route('student.smart.index')->with('flash', 'نمایش نتیجه توسط معلم غیرفعال شده است.');
        }

        $analysisOn = \App\Support\SmartLab::flag('smart_analysis_enabled');
        return Inertia::render('Student/SmartExamResult', [
            'exam' => ['id' => $smartExam->id, 'title' => $smartExam->title, 'subject' => $smartExam->subject],
            'attempt' => [
                'score' => $attempt->auto_score, 'max' => $attempt->max_score,
                'percent' => $attempt->max_score ? (int) round($attempt->auto_score / $attempt->max_score * 100) : 0,
                'status' => $attempt->status, 'duration' => $attempt->duration_sec,
                'xp' => optional(SmartExamReward::where('attempt_id', $attempt->id)->first())->xp,
            ],
            'analysis' => $analysisOn ? $analytics->studentAnalysis($attempt) : null,
            'showAnswers' => (bool) ($rules['show_answer'] ?? true),
        ]);
    }

    /** توکنِ گره‌خورده به آزمون + دانش‌آموز + تلاش. */
    private function tokenAttempt(SmartExam $exam, User $user, string $token): ?SmartExamAttempt
    {
        $attempt = SmartExamAttempt::where('smart_exam_id', $exam->id)
            ->where('student_id', $user->id)->where('token', $token)->first();
        if (! $attempt || ($attempt->token_expires_at && now()->greaterThan($attempt->token_expires_at))) {
            return null;
        }
        return $attempt;
    }

    private function canAccess(SmartExam $exam, User $user, $classroomIds): bool
    {
        $teacherIds = Classroom::whereIn('id', $classroomIds)->pluck('teacher_id');
        if (! $teacherIds->contains($exam->teacher_id)) {
            return false;
        }
        return $this->targeted($exam, $user, $classroomIds);
    }
}
