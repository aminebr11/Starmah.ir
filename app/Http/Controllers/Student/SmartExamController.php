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
            $inProgress = $mine->firstWhere('status', 'in_progress');
            $rules = $e->rules ?? [];
            $maxAttempts = (int) ($rules['attempts'] ?? 1);
            $completedCount = $mine->where('status', 'completed')->count();
            $live = $e->isLive();
            $status = ! $live ? ($e->closes_at && now()->greaterThan($e->closes_at) ? 'expired' : 'locked')
                : ($inProgress ? 'in_progress' : ($completedCount ? 'done' : 'new'));
            return [
                'id' => $e->id, 'title' => $e->title, 'subject' => $e->subject, 'topic' => $e->topic,
                'grade' => $e->grade, 'kind' => $e->kind, 'adaptive' => $e->adaptive,
                'count' => $e->questions_count, 'duration' => $rules['duration'] ?? null,
                'status' => $status,
                'attemptsLeft' => max(0, $maxAttempts - $completedCount),
                'lastScore' => $best?->score, 'lastMax' => $best?->max_score,
                'closes' => $e->closes_at ? Jalali::format($e->closes_at, true) : null,
                'showResult' => (bool) ($rules['show_result'] ?? true),
            ];
        })->sortBy(fn ($c) => ['in_progress' => 0, 'new' => 1, 'done' => 2, 'locked' => 3, 'expired' => 4][$c['status']] ?? 9)->values();

        return Inertia::render('Student/SmartExams', ['exams' => $cards]);
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

        $questions = $smartExam->questions->values()->map(function ($q, $i) use ($rules) {
            $choices = collect($q->choices ?? [])->map(fn ($c) => ['value' => $c['value'] ?? ''])->values();
            if (! empty($rules['shuffle_choices'])) {
                $choices = $choices->shuffle()->values();
            }
            return ['i' => $i, 'type' => $q->type, 'prompt' => $q->prompt, 'media' => $q->media_path,
                'choices' => $choices, 'points' => $q->points];
        });
        if (! empty($rules['shuffle'])) {
            // ترتیب تصادفی — ولی نگاشتِ i حفظ شود
            $questions = $questions->shuffle()->values();
        }

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
