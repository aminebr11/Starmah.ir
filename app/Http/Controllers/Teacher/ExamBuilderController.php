<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\ExamQuestion;
use App\Services\AiExamService;
use App\Services\GamificationService;
use App\Support\Jalali;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

/** آزمون‌ساز حرفه‌ای: ساخت/ویرایش/حذف، زمان‌بندی، مدت، سطح دشواری، بانک آزمون و بازاستفاده. */
class ExamBuilderController extends Controller
{
    public function index(Request $request): Response
    {
        $teacher = $request->user();
        $classroom = Classroom::where('teacher_id', $teacher->id)->first();

        $exams = Assignment::where('classroom_id', $classroom?->id)
            ->whereIn('type', ['exam', 'quiz'])->latest()->get()
            ->map(function ($a) {
                // اگر داده‌ی یک آزمون خراب بود، کل صفحه نباید ۵۰۰ شود
                try {
                    return $this->examSummary($a);
                } catch (\Throwable $e) {
                    report($e);
                    return [
                        'id' => $a->id, 'title' => $a->title, 'type' => $a->type,
                        'count' => 0, 'published' => (bool) $a->is_published, 'questions' => [],
                        'duration' => null, 'difficulty' => 'medium', 'subject' => null,
                        'scheduled_at' => null, 'jscheduled' => null, 'taken' => 0, 'avg' => null,
                    ];
                }
            });

        // بانک سؤالات — اگر جدولش هنوز ساخته نشده (آپگرید SQL اجرا نشده) صفحه نباید ۵۰۰ بدهد.
        $bank = Schema::hasTable('exam_questions')
            ? ExamQuestion::where('teacher_id', $teacher->id)->latest()->get()
                ->map(fn ($q) => [
                    'id' => $q->id, 'type' => $q->type, 'lesson' => $q->lesson, 'prompt' => $q->prompt,
                    'choices' => $q->choices ?? [],
                ])
            : collect();

        return Inertia::render('Teacher/ExamBuilder', [
            'classroom' => $classroom?->only('id', 'name'),
            'subjects'  => $classroom ? $classroom->subjectNames() : [],
            'exams'     => $exams,
            'bank'      => $bank,
            'aiEnabled' => (bool) (env('ANTHROPIC_API_KEY') || env('OPENAI_API_KEY')),
        ]);
    }

    /** ذخیره‌ی یک یا چند سؤال در بانک سؤالات. */
    public function saveToBank(Request $request): RedirectResponse
    {
        $teacher = $request->user();
        if (! Schema::hasTable('exam_questions')) {
            return back()->with('flash', 'بانک سؤالات هنوز روی سرور فعال نشده — لطفاً آپگرید پایگاه‌داده (upgrade-v7.sql) را اجرا کنید.');
        }
        $data = $request->validate([
            'lesson'             => ['nullable', 'string', 'max:80'],
            'questions'          => ['required', 'array', 'min:1'],
            'questions.*.type'   => ['nullable', 'in:mc,tf,desc'],
            'questions.*.prompt' => ['required', 'string'],
            'questions.*.choices'=> ['nullable', 'array'],
        ]);
        $classroom = Classroom::where('teacher_id', $teacher->id)->first();

        foreach ($data['questions'] as $q) {
            ExamQuestion::create([
                'school_id'  => $teacher->school_id,
                'teacher_id' => $teacher->id,
                'type'       => $q['type'] ?? 'mc',
                'lesson'     => $data['lesson'] ?? null,
                'grade'      => $classroom?->grade,
                'prompt'     => $q['prompt'],
                'choices'    => $q['choices'] ?? [],
            ]);
        }

        return back()->with('flash', count($data['questions']) . ' سؤال به بانک اضافه شد ✅');
    }

    public function deleteFromBank(Request $request, ExamQuestion $examQuestion): RedirectResponse
    {
        abort_unless($examQuestion->teacher_id === $request->user()->id, 403);
        $examQuestion->delete();
        return back()->with('flash', 'سؤال از بانک حذف شد');
    }

    private function examSummary(Assignment $a): array
    {
        $cfg = $a->config ?? [];
        $subs = $a->submissions()->get(['score', 'max_score']);
        $avg = $subs->count() ? round($subs->avg(fn ($s) => $s->max_score ? $s->score / $s->max_score * 100 : 0)) : null;

        return [
            'id' => $a->id, 'title' => $a->title, 'type' => $a->type,
            'count' => count($cfg['questions'] ?? []) ?: $a->question_count,
            'published' => $a->is_published,
            'questions' => $cfg['questions'] ?? [],
            'duration'  => $cfg['duration'] ?? null,
            'difficulty'=> $cfg['difficulty'] ?? 'medium',
            'subject'   => $cfg['subject'] ?? null,
            'scheduled_at' => $cfg['scheduled_at'] ?? null,
            'jscheduled'=> ! empty($cfg['scheduled_at']) ? Jalali::format($cfg['scheduled_at'], true) : null,
            'taken'     => $subs->count(),
            'avg'       => $avg,
        ];
    }

    /** تولید سؤال با AI (یا fallback) — برای پیش‌نمایش در فرم. */
    public function generate(Request $request, AiExamService $ai): JsonResponse
    {
        $data = $request->validate([
            'topic' => ['required', 'string', 'max:120'],
            'count' => ['required', 'integer', 'min:1', 'max:20'],
        ]);
        return response()->json(['questions' => $ai->generate($data['topic'], $data['count'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $teacher = $request->user();
        $classroom = Classroom::where('teacher_id', $teacher->id)->firstOrFail();
        $data = $this->validated($request);

        $assignment = Assignment::create([
            'school_id'      => $teacher->school_id,
            'classroom_id'   => $classroom->id,
            'teacher_id'     => $teacher->id,
            'title'          => $data['title'],
            'type'           => $data['type'],
            'question_count' => count($data['questions']),
            'config'         => $this->configFrom($data),
            'is_published'   => $data['publish'] ?? true,
        ]);

        if ($assignment->is_published) {
            $this->notifyStudents($assignment, $classroom);
        }

        return redirect()->route('teacher.exams')->with('flash', 'آزمون ساخته و منتشر شد ✅');
    }

    /** اعلانِ انتشارِ آزمون به دانش‌آموزانِ کلاس (در زنگوله/اعلان‌ها دیده می‌شود). */
    private function notifyStudents(Assignment $assignment, Classroom $classroom): void
    {
        $ids = $classroom->students()->pluck('users.id')->all();
        if (! $ids) {
            return;
        }
        $ann = Announcement::create([
            'school_id' => $assignment->school_id,
            'sender_id' => $assignment->teacher_id,
            'title'     => '💻 آزمون جدید — ' . $assignment->title,
            'audience'  => 'personal',
            'body'      => "یک آزمون جدید برای شما منتشر شد: «{$assignment->title}».\nبرای شرکت، روی همین اعلان بزنید.",
            'link'      => '/exams',
        ]);
        $ann->recipients()->sync($ids);
    }

    public function update(Request $request, Assignment $assignment): RedirectResponse
    {
        abort_unless($assignment->teacher_id === $request->user()->id, 403);
        $data = $this->validated($request);
        $wasPublished = (bool) $assignment->is_published;

        $assignment->update([
            'title'          => $data['title'],
            'type'           => $data['type'],
            'question_count' => count($data['questions']),
            'config'         => $this->configFrom($data),
            'is_published'   => $data['publish'] ?? true,
        ]);

        // فقط هنگامِ انتشارِ تازه اعلان بده (نه هر ویرایش)
        if ($assignment->is_published && ! $wasPublished && $assignment->classroom) {
            $this->notifyStudents($assignment, $assignment->classroom);
        }

        return redirect()->route('teacher.exams')->with('flash', 'آزمون ویرایش شد ✅');
    }

    public function destroy(Request $request, Assignment $assignment): RedirectResponse
    {
        abort_unless($assignment->teacher_id === $request->user()->id, 403);
        $assignment->delete();
        return back()->with('flash', 'آزمون حذف شد');
    }

    /** کارنامه/تحلیل نتایج یک آزمون: میانگین کلاس، درصد قبولی، نمرات دانش‌آموزان، نمودار. */
    public function report(Request $request, Assignment $assignment): Response
    {
        abort_unless($assignment->teacher_id === $request->user()->id, 403);
        $classroom = $assignment->classroom;
        $students = $classroom ? $classroom->students()->get(['users.id', 'name']) : collect();
        $subs = $assignment->submissions()->get()->keyBy('student_id');

        // همه‌ی سؤال‌های آزمون + پاسخِ درست (برای تحلیلِ سؤال‌به‌سؤال)
        $built = collect($assignment->config['questions'] ?? []);
        $qMeta = $built->map(function ($q, $i) {
            $type = $q['type'] ?? 'mc';
            return [
                'i' => $i, 'type' => $type, 'prompt' => $q['prompt'] ?? '',
                'correct' => $type === 'desc' ? null : (string) (collect($q['choices'] ?? [])->firstWhere('correct', true)['value'] ?? ''),
            ];
        })->values();

        $descQuestions = $qMeta->filter(fn ($q) => $q['type'] === 'desc')->values();

        $rows = $students->map(function ($s) use ($subs, $descQuestions, $qMeta) {
            $sub = $subs->get($s->id);
            $pct = $sub && $sub->max_score ? (int) round($sub->score / $sub->max_score * 100) : null;
            $answers = collect($sub?->answers ?? [])->keyBy('i');

            // پاسخِ این دانش‌آموز به هر سؤال + درست/نادرست
            $perQ = $qMeta->map(function ($q) use ($answers) {
                $mine = (string) ($answers[$q['i']]['value'] ?? '');
                return [
                    'i' => $q['i'], 'type' => $q['type'], 'mine' => $mine,
                    'ok' => $q['type'] === 'desc' ? null : ($mine !== '' && $mine === $q['correct']),
                    'score' => $answers[$q['i']]['score'] ?? null,
                ];
            });

            $descAnswers = $descQuestions->map(fn ($q) => [
                'i' => $q['i'], 'prompt' => $q['prompt'],
                'answer' => $answers[$q['i']]['value'] ?? '',
                'score' => $answers[$q['i']]['score'] ?? null,
            ]);

            return [
                'id' => $s->id, 'name' => $s->name,
                'done' => (bool) $sub,
                'score' => $sub?->score, 'max' => $sub?->max_score, 'percent' => $pct,
                'descGraded' => (bool) ($sub?->desc_graded),
                'descAnswers' => $descAnswers,
                'perQuestion' => $perQ,
                'jdate' => $sub?->submitted_at ? Jalali::format($sub->submitted_at, true) : null,
            ];
        })->sortByDesc(fn ($r) => $r['percent'] ?? -1)->values();

        // تحلیلِ سؤال‌به‌سؤال برای کلِ کلاس — کدام سؤال‌ها بیشترین اشتباه را داشتند
        $taken = $rows->where('done', true);
        $questionStats = $qMeta->map(function ($q) use ($taken) {
            if ($q['type'] === 'desc') {
                return ['i' => $q['i'], 'prompt' => $q['prompt'], 'type' => 'desc', 'correct' => 0, 'wrong' => 0, 'blank' => 0, 'pct' => null, 'wrongNames' => []];
            }
            $c = 0; $w = 0; $b = 0; $wrongNames = [];
            foreach ($taken as $r) {
                $a = collect($r['perQuestion'])->firstWhere('i', $q['i']);
                if (! $a || $a['mine'] === '') { $b++; continue; }
                if ($a['ok']) { $c++; } else { $w++; $wrongNames[] = $r['name']; }
            }
            $answered = $c + $w;
            return [
                'i' => $q['i'], 'prompt' => $q['prompt'], 'type' => $q['type'],
                'correct' => $c, 'wrong' => $w, 'blank' => $b,
                'pct' => $answered ? (int) round($c / $answered * 100) : null,
                'wrongNames' => $wrongNames,
            ];
        })->values();

        $done = $rows->where('done', true);
        $percents = $done->pluck('percent')->filter(fn ($p) => $p !== null);
        $summary = [
            'total'    => $rows->count(),
            'taken'    => $done->count(),
            'avg'      => $percents->count() ? (int) round($percents->avg()) : 0,
            'max'      => $percents->max() ?? 0,
            'min'      => $percents->min() ?? 0,
            'passRate' => $percents->count() ? (int) round($percents->filter(fn ($p) => $p >= 50)->count() / $percents->count() * 100) : 0,
        ];
        // توزیع نمرات (بازه‌های ۲۰تایی)
        $buckets = ['۰-۲۰' => 0, '۲۱-۴۰' => 0, '۴۱-۶۰' => 0, '۶۱-۸۰' => 0, '۸۱-۱۰۰' => 0];
        foreach ($percents as $p) {
            $k = $p <= 20 ? '۰-۲۰' : ($p <= 40 ? '۲۱-۴۰' : ($p <= 60 ? '۴۱-۶۰' : ($p <= 80 ? '۶۱-۸۰' : '۸۱-۱۰۰')));
            $buckets[$k]++;
        }

        return Inertia::render('Teacher/ExamReport', [
            'exam'     => ['id' => $assignment->id, 'title' => $assignment->title, 'type' => $assignment->type],
            'classroom'=> $classroom?->only('name'),
            'rows'     => $rows,
            'summary'  => $summary,
            'buckets'  => $buckets,
            'questionStats' => $questionStats,
            'hasDesc'  => $descQuestions->isNotEmpty(),
            'printedAt'=> Jalali::format(now(), true),
        ]);
    }

    /** تصحیحِ دستیِ پاسخ‌های تشریحیِ یک دانش‌آموز (هر پاسخ ۰ تا ۱). */
    public function gradeDescriptive(Request $request, Assignment $assignment, GamificationService $game): RedirectResponse
    {
        abort_unless($assignment->teacher_id === $request->user()->id, 403);
        $data = $request->validate([
            'student_id'      => ['required', 'integer'],
            'scores'          => ['required', 'array'],
            'scores.*.i'      => ['required', 'integer'],
            'scores.*.score'  => ['required', 'numeric', 'min:0', 'max:1'],
        ]);

        $sub = $assignment->submissions()->where('student_id', $data['student_id'])->firstOrFail();

        // نوشتن نمره‌ی هر پاسخِ تشریحی داخل answers
        $scoreByI = collect($data['scores'])->keyBy('i');
        $answers = collect($sub->answers ?? [])->map(function ($a) use ($scoreByI) {
            if (($a['type'] ?? 'mc') === 'desc' && $scoreByI->has($a['i'])) {
                $a['score'] = (float) $scoreByI[$a['i']]['score'];
            }
            return $a;
        })->all();

        $descCount = collect($answers)->where('type', 'desc')->count();
        $descCorrect = collect($answers)->where('type', 'desc')->sum(fn ($a) => (float) ($a['score'] ?? 0));

        $autoScore = (float) ($sub->auto_score ?? $sub->score);
        $autoMax = (int) ($sub->auto_max ?? $sub->max_score);
        $total = $autoScore + $descCorrect;
        $maxTotal = $autoMax + $descCount;
        $accuracy = $maxTotal ? round($total / $maxTotal * 100, 2) : 0;

        $sub->update([
            'answers' => $answers, 'score' => $total, 'max_score' => $maxTotal,
            'accuracy' => $accuracy, 'desc_graded' => true,
        ]);

        // XP اضافیِ بخش تشریحی (idempotent بر اساس منبعِ Submission)
        \App\Models\XpEntry::where('source_type', \App\Models\AssignmentSubmission::class)
            ->where('source_id', $sub->id)->delete();
        $descXp = $descCount ? (int) round($descCorrect / $descCount * 20) : 0;
        if ($descXp !== 0) {
            $student = \App\Models\User::find($data['student_id']);
            if ($student) {
                $game->award($student, $descXp, '✍️ تصحیح تشریحیِ آزمون — ' . $assignment->title,
                    $request->user(), \App\Models\AssignmentSubmission::class, $sub->id);
            }
        }

        // اعلانِ نتیجه‌ی نهایی به دانش‌آموز
        $ann = Announcement::create([
            'school_id' => $assignment->school_id, 'sender_id' => $assignment->teacher_id,
            'title' => '✅ تصحیح آزمون — ' . $assignment->title, 'audience' => 'personal',
            'body' => "آزمونِ «{$assignment->title}» تصحیح شد.\nنمره‌ی نهایی: {$total} از {$maxTotal} ({$accuracy}٪)",
        ]);
        $ann->recipients()->sync([$data['student_id']]);

        return back()->with('flash', 'پاسخ‌های تشریحی تصحیح و نتیجه اعلام شد ✅');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title'                 => ['required', 'string', 'max:120'],
            'type'                  => ['required', 'in:exam,quiz'],
            'subject'               => ['nullable', 'string', 'max:80'],
            'difficulty'            => ['nullable', 'in:easy,medium,hard'],
            'duration'              => ['nullable', 'integer', 'min:1', 'max:300'],
            'scheduled_at'          => ['nullable', 'date'],
            'questions'             => ['required', 'array', 'min:1'],
            'questions.*.type'      => ['nullable', 'in:mc,tf,desc'],
            'questions.*.prompt'    => ['required', 'string'],
            'questions.*.choices'   => ['nullable', 'array'],
            'publish'               => ['boolean'],
        ]);
    }

    private function configFrom(array $data): array
    {
        return [
            'questions'    => $data['questions'],
            'subject'      => $data['subject'] ?? null,
            'difficulty'   => $data['difficulty'] ?? 'medium',
            'duration'     => $data['duration'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
        ];
    }
}
