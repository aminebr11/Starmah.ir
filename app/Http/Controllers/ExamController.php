<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Skill;
use App\Services\GamificationService;
use App\Services\ThemeEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** آزمون آنلاین — دانش‌آموز آزمون‌های منتشرشده‌ی کلاسش را می‌دهد. */
class ExamController extends Controller
{
    public function __construct(private ThemeEngine $engine, private GamificationService $game) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $classroomId = $user->classrooms()->value('classrooms.id');

        $assignments = Assignment::where('classroom_id', $classroomId)
            ->where('is_published', true)->latest()->get();

        $subs = AssignmentSubmission::where('student_id', $user->id)
            ->whereIn('assignment_id', $assignments->pluck('id'))->get()->keyBy('assignment_id');

        $list = $assignments->map(fn ($a) => [
            'id' => $a->id, 'title' => $a->title, 'type' => $a->type,
            'count' => $a->question_count,
            'done' => isset($subs[$a->id]),
            'score' => $subs[$a->id]->score ?? null,
            'max' => $subs[$a->id]->max_score ?? null,
        ]);

        return Inertia::render('Student/Exams', ['exams' => $list]);
    }

    public function take(Request $request, Assignment $assignment): Response
    {
        $user = $request->user();
        abort_unless($assignment->is_published, 404);

        // ── امنیت: آزمون باید متعلق به مدرسه و کلاسِ خودِ دانش‌آموز باشد ──
        abort_unless($assignment->school_id === $user->school_id, 403);
        $inClass = $user->classrooms()->where('classrooms.id', $assignment->classroom_id)->exists();
        abort_unless($inClass, 403, 'این آزمون برای کلاس شما نیست.');

        // ── بازه‌ی زمانیِ مجاز ──
        $cfg = $assignment->config ?? [];
        if (! empty($cfg['scheduled_at']) && now()->lessThan($cfg['scheduled_at'])) {
            abort(403, 'زمان شروع آزمون فرا نرسیده است.');
        }
        if (! empty($cfg['close_at']) && now()->greaterThan($cfg['close_at'])) {
            abort(403, 'مهلت این آزمون به پایان رسیده است.');
        }

        // ── محدودیت تلاش: اگر قبلاً کامل کرده و آزمون اجازه‌ی تکرار نداده ──
        $prior = AssignmentSubmission::where('assignment_id', $assignment->id)
            ->where('student_id', $user->id)->first();
        $allowRetake = (bool) ($cfg['allow_retake'] ?? false);
        if ($prior && $prior->status === 'completed' && ! $allowRetake) {
            abort(403, 'شما این آزمون را قبلاً انجام داده‌اید.');
        }

        $theme = $this->engine->for($user);

        $token = (string) Str::uuid();
        $questions = []; $key = [];

        // اگر آزمون سؤال‌های ساخته‌شده (دستی/AI) دارد، از همان‌ها استفاده کن
        $built = $assignment->config['questions'] ?? null;
        if (is_array($built) && $built) {
            foreach ($built as $i => $q) {
                $type = $q['type'] ?? 'mc';
                if ($type === 'desc') {
                    $questions[] = ['i' => $i, 'type' => 'desc', 'skill' => $assignment->title, 'prompt' => $q['prompt'], 'choices' => []];
                    $key[$i] = ['type' => 'desc', 'answer' => null];
                    continue;
                }
                $choices = $type === 'tf'
                    ? [['value' => 'درست'], ['value' => 'نادرست']]
                    : array_map(fn ($c) => ['value' => (string) $c['value']], $q['choices'] ?? []);
                $correct = collect($q['choices'] ?? [])->firstWhere('correct', true);
                $questions[] = ['i' => $i, 'type' => $type, 'skill' => $assignment->title, 'prompt' => $q['prompt'], 'choices' => $choices];
                $key[$i] = ['type' => $type, 'answer' => (string) ($correct['value'] ?? '')];
            }
        } else {
            // در غیر این صورت از بانک سؤال مهارت‌محور
            $skills = Skill::with('questions')->whereHas('questions')
                ->when($assignment->skill_ids, fn ($q) => $q->whereIn('id', $assignment->skill_ids))->get();
            abort_if($skills->isEmpty(), 404, 'سؤالی موجود نیست');
            $count = min($assignment->question_count, 12);
            for ($i = 0; $i < $count; $i++) {
                $s = $skills->random(); $q = $s->questions->random();
                $r = $this->engine->renderQuestion($q, $theme);
                $questions[] = ['i' => $i, 'type' => 'mc', 'skill' => $s->name, 'prompt' => $r['prompt'],
                    'choices' => array_map(fn ($c) => ['value' => $c['value']], $r['choices'])];
                $key[$i] = ['type' => 'mc', 'answer' => $r['answer']];
            }
        }
        // توکن به آزمون + دانش‌آموز + زمان انقضا گره می‌خورد (جلوگیری از استفاده در آزمون دیگر)
        $request->session()->put("exam.$token", [
            'assignment_id' => $assignment->id,
            'student_id'    => $user->id,
            'created_at'    => now()->timestamp,
            'expires_at'    => now()->addHours(3)->timestamp,
            'key'           => $key,
        ]);

        return Inertia::render('Student/ExamTake', [
            'assignment' => $assignment->only('id', 'title'),
            'token' => $token, 'questions' => $questions,
        ]);
    }

    public function submit(Request $request, Assignment $assignment)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'answers' => ['required', 'array'],
        ]);
        $user = $request->user();
        $sess = $request->session()->pull("exam.{$data['token']}");
        abort_if(! $sess, 419, 'جلسه منقضی شد');

        // ── توکن فقط برای همین آزمون و همین دانش‌آموز معتبر است ──
        abort_unless(($sess['assignment_id'] ?? null) === $assignment->id, 403, 'توکن برای این آزمون معتبر نیست.');
        abort_unless(($sess['student_id'] ?? null) === $user->id, 403);
        abort_if(now()->timestamp > ($sess['expires_at'] ?? 0), 419, 'مهلت پاسخ‌گویی تمام شد.');
        $key = $sess['key'] ?? [];

        $answersById = collect($data['answers'])->keyBy('i');
        $correct = 0; $auto = 0; $descCount = 0; $stored = [];
        foreach ($key as $i => $k) {
            $given = (string) ($answersById[$i]['value'] ?? '');
            $stored[] = ['i' => $i, 'type' => $k['type'] ?? 'mc', 'value' => $given];
            if (($k['type'] ?? 'mc') === 'desc') {
                $descCount++;
                continue; // تشریحی: خودکار تصحیح نمی‌شود
            }
            $auto++;
            if ($given === (string) $k['answer']) {
                $correct++;
            }
        }
        $total = $auto ?: count($key);
        $accuracy = $auto ? round($correct / $auto * 100, 2) : 0;
        $points = (int) round($accuracy);

        // ── جلوگیری از سوءاستفاده‌ی XP: فقط بارِ اول امتیاز داده می‌شود ──
        $existing = AssignmentSubmission::where('assignment_id', $assignment->id)
            ->where('student_id', $user->id)->first();
        $firstCompletion = ! $existing || $existing->status !== 'completed';
        // در صورت تکرار، فقط اگر نتیجه‌ی بهتر شد ثبت می‌شود (بهترین نتیجه)
        $keepBest = $existing && $existing->status === 'completed' && $existing->max_score
            && ($existing->score / max(1, $existing->max_score)) >= ($correct / max(1, $auto));

        if (! $keepBest) {
            AssignmentSubmission::updateOrCreate(
                ['assignment_id' => $assignment->id, 'student_id' => $user->id],
                ['score' => $correct, 'max_score' => $auto, 'accuracy' => $accuracy,
                 'auto_score' => $correct, 'auto_max' => $auto, 'desc_graded' => false,
                 'answers' => $stored, 'status' => 'completed', 'submitted_at' => now()]
            );
        }

        // XP فقط یک‌بار (اولین تکمیل) — تلاش‌های تکراری امتیاز اضافه نمی‌گیرند
        if ($firstCompletion) {
            $this->game->awardXp($user, $points, '💻 آزمون آنلاین — ' . $assignment->title);
        }

        // اعلانِ نتیجه در کارتابلِ دانش‌آموز
        $body = "آزمون «{$assignment->title}» را دادی.\nنتیجه: {$correct} از {$auto} درست ({$accuracy}٪)";
        if ($descCount > 0) {
            $body .= "\n{$descCount} سؤال تشریحی توسط معلم بررسی می‌شود.";
        }
        $ann = \App\Models\Announcement::create([
            'school_id' => $assignment->school_id, 'sender_id' => $assignment->teacher_id,
            'title' => '💻 نتیجه‌ی آزمون — ' . $assignment->title, 'audience' => 'personal', 'body' => $body,
        ]);
        $ann->recipients()->sync([$user->id]);

        return response()->json(['correct' => $correct, 'total' => $auto, 'points' => $points, 'desc' => $descCount]);
    }
}
