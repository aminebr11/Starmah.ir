<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\SmartExam;
use App\Models\SmartExamQuestion;
use App\Models\SmartQuestionBank;
use App\Models\Theme;
use App\Services\SmartExamAiService;
use App\Services\SmartExamAnalyticsService;
use App\Support\Jalali;
use App\Support\SmartLab;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** آزمایشگاه هوشمند آزمون — سمت معلم (ماژول آزمایشی، جدا از آزمون‌ساز فعلی). */
class SmartExamController extends Controller
{
    public function lab(Request $request): Response
    {
        $teacher = $request->user();
        $exams = SmartExam::where('teacher_id', $teacher->id)
            ->withCount(['questions', 'attempts'])->latest()->get()
            ->map(fn ($e) => $this->card($e));

        $classrooms = Classroom::where('teacher_id', $teacher->id)->get(['id', 'name', 'grade']);

        return Inertia::render('Teacher/SmartExamLab', array_merge(
            $this->formData($teacher),
            [
                'flags' => SmartLab::config(),
                'exams' => $exams->values(),
                'buckets' => [
                    'draft' => $exams->where('status', 'draft')->count(),
                    'published' => $exams->where('status', 'published')->count(),
                    'scheduled' => $exams->where('status', 'scheduled')->count(),
                    'closed' => $exams->whereIn('status', ['closed', 'archived'])->count(),
                ],
                'bankCount' => SmartQuestionBank::where('teacher_id', $teacher->id)->count(),
                'aiCount' => \App\Models\SmartExamAiRequest::where('teacher_id', $teacher->id)->sum('produced'),
                'classrooms' => $classrooms,
            ]
        ));
    }

    private function formData($teacher): array
    {
        $classrooms = Classroom::where('teacher_id', $teacher->id)->get();
        $groups = [];
        foreach ($classrooms as $c) {
            $c->students()->with('theme:id,name,emoji')->get()
                ->groupBy('theme_id')->each(function ($g, $tid) use (&$groups, $c) {
                    $t = $g->first()->theme;
                    $groups[] = [
                        'classroom_id' => $c->id, 'classroom' => $c->name,
                        'theme_id' => $t?->id, 'name' => $t ? "{$t->emoji} {$t->name}" : 'بدون تیم',
                        'students' => $g->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values(),
                    ];
                });
        }
        return [
            'classroomsFull' => $classrooms->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'grade' => $c->grade,
                'subjects' => collect($c->subjectNames())->map(fn ($s) => is_array($s) ? $s['name'] : $s)->values()]),
            'groups' => $groups,
            'themes' => Theme::where('is_active', true)->where('key', '!=', 'brand')->orderBy('sort')
                ->get(['id', 'name', 'emoji'])->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'emoji' => $t->emoji]),
            'aiEnabled' => SmartLab::flag('smart_ai_enabled'),
            'adaptiveEnabled' => SmartLab::flag('smart_adaptive_enabled'),
        ];
    }

    private function card(SmartExam $e): array
    {
        return [
            'id' => $e->id, 'title' => $e->title, 'subject' => $e->subject, 'grade' => $e->grade,
            'topic' => $e->topic, 'kind' => $e->kind, 'status' => $e->status, 'adaptive' => $e->adaptive,
            'live' => $e->isLive(), 'questions' => $e->questions_count, 'attempts' => $e->attempts_count,
            'scheduled' => $e->status === 'published' && $e->opens_at && now()->lessThan($e->opens_at),
            'opens_at' => optional($e->opens_at)->format('Y-m-d H:i'),
            'jopens' => $e->opens_at ? Jalali::format($e->opens_at, true) : null,
            'jcloses' => $e->closes_at ? Jalali::format($e->closes_at, true) : null,
            'date' => Jalali::format($e->created_at), 'version' => $e->version,
        ];
    }

    public function edit(Request $request, SmartExam $smartExam): Response
    {
        abort_unless($smartExam->teacher_id === $request->user()->id, 403);
        $smartExam->load('questions', 'targets');
        return Inertia::render('Teacher/SmartExamLab', array_merge(
            $this->formData($request->user()),
            [
                'flags' => SmartLab::config(),
                'exams' => SmartExam::where('teacher_id', $request->user()->id)->withCount(['questions', 'attempts'])->latest()->get()->map(fn ($e) => $this->card($e)),
                'buckets' => [], 'bankCount' => 0, 'aiCount' => 0,
                'classrooms' => Classroom::where('teacher_id', $request->user()->id)->get(['id', 'name', 'grade']),
                'editing' => [
                    'id' => $smartExam->id,
                    ...$smartExam->only(['title', 'description', 'grade', 'subject', 'book', 'chapter', 'topic', 'goal', 'kind', 'status', 'adaptive']),
                    'rules' => $smartExam->rules ?? [],
                    'opens_at' => optional($smartExam->opens_at)->format('Y-m-d H:i'),
                    'closes_at' => optional($smartExam->closes_at)->format('Y-m-d H:i'),
                    'target_classrooms' => $smartExam->targets->pluck('classroom_id')->filter()->unique()->values(),
                    'target_themes' => $smartExam->targets->pluck('theme_id')->filter()->unique()->values(),
                    'target_students' => $smartExam->targets->pluck('student_id')->filter()->unique()->values(),
                    'questions' => $smartExam->questions->map(fn ($q) => [
                        'type' => $q->type, 'prompt' => $q->prompt, 'choices' => $q->choices ?? [],
                        'answer' => $q->answer, 'explanation' => $q->explanation, 'difficulty' => $q->difficulty,
                        'points' => $q->points, 'topic' => $q->topic, 'goal' => $q->goal, 'source' => $q->source,
                    ])->values(),
                ],
            ]
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $teacher = $request->user();
        $data = $this->validated($request);
        $this->assertTargetsOwned($teacher, $data);

        $exam = SmartExam::create($this->attributes($teacher, $data));
        $this->syncQuestions($exam, $data['questions']);
        $this->syncTargets($exam, $data);

        AuditLog::record($teacher, 'ساخت آزمون هوشمند', "آزمون «{$exam->title}» ساخته شد");
        return redirect()->route('teacher.smart.lab')->with('flash', 'آزمون هوشمند ساخته شد 🧪');
    }

    public function update(Request $request, SmartExam $smartExam): RedirectResponse
    {
        abort_unless($smartExam->teacher_id === $request->user()->id, 403);
        $data = $this->validated($request);
        $this->assertTargetsOwned($request->user(), $data);

        // ویرایشِ آزمونِ دارای نتیجه → نسخه‌ی جدید تا گزارش‌های قبلی حفظ شوند
        if ($smartExam->attempts()->exists()) {
            $new = $smartExam->replicate(['created_at', 'updated_at']);
            $new->fill($this->attributes($request->user(), $data));
            $new->version = $smartExam->version + 1;
            $new->status = 'draft';
            $new->save();
            $this->syncQuestions($new, $data['questions']);
            $this->syncTargets($new, $data);
            $smartExam->update(['status' => 'archived']);
            return redirect()->route('teacher.smart.lab')->with('flash', 'نسخه‌ی جدید ساخته و نسخه‌ی قبلی (با نتایجش) آرشیو شد ✅');
        }

        $smartExam->update($this->attributes($request->user(), $data));
        $this->syncQuestions($smartExam, $data['questions']);
        $this->syncTargets($smartExam, $data);
        return redirect()->route('teacher.smart.lab')->with('flash', 'آزمون به‌روزرسانی شد ✅');
    }

    public function status(Request $request, SmartExam $smartExam): RedirectResponse
    {
        abort_unless($smartExam->teacher_id === $request->user()->id, 403);
        $data = $request->validate(['status' => ['required', 'in:draft,review,scheduled,published,closed,archived']]);
        $smartExam->update(['status' => $data['status']]);
        return back()->with('flash', 'وضعیت آزمون به‌روزرسانی شد');
    }

    public function destroy(Request $request, SmartExam $smartExam): RedirectResponse
    {
        abort_unless($smartExam->teacher_id === $request->user()->id, 403);
        $smartExam->delete();
        return back()->with('flash', 'آزمون حذف شد (آزمون‌های قدیمی و نتایجشان دست‌نخورده‌اند).');
    }

    /** دستیار هوشمند طراحی سؤال. */
    public function aiGenerate(Request $request, SmartExamAiService $ai): JsonResponse
    {
        abort_unless(SmartLab::flag('smart_ai_enabled'), 403);
        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:80'],
            'topic' => ['nullable', 'string', 'max:120'],
            'chapter' => ['nullable', 'string', 'max:120'],
            'book' => ['nullable', 'string', 'max:120'],
            'goal' => ['nullable', 'string', 'max:300'],
            'kind' => ['nullable', 'string', 'max:40'],
            'grade' => ['nullable', 'string', 'max:40'],
            'count' => ['required', 'integer', 'min:1', 'max:20'],
            'type' => ['nullable', 'in:mc,tf,desc,blank'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'flavor' => ['nullable', 'string', 'max:60'],
            'sample' => ['nullable', 'boolean'],
        ]);
        $result = $ai->generate([...$data, 'school_id' => $request->user()->school_id, 'teacher_id' => $request->user()->id]);
        return response()->json($result);
    }

    // ── بانک سؤال ──
    public function bank(Request $request): Response
    {
        $teacher = $request->user();
        $q = \App\Support\BankAccess::visibleQuery($teacher)
            ->with('teacher:id,name')
            ->when($request->subject, fn ($x) => $x->where('subject', $request->subject))
            ->when($request->grade, fn ($x) => $x->where('grade', $request->grade))
            ->when($request->difficulty, fn ($x) => $x->where('difficulty', $request->difficulty))
            ->when($request->type, fn ($x) => $x->where('type', $request->type))
            ->when($request->search, fn ($x) => $x->where('prompt', 'like', '%' . $request->search . '%'))
            ->latest()->limit(300)->get();

        return Inertia::render('Teacher/SmartQuestionBank', [
            'flags' => SmartLab::config(),
            'questions' => $q->map(fn ($b) => [
                'id' => $b->id, 'type' => $b->type, 'prompt' => $b->prompt, 'choices' => $b->choices,
                'subject' => $b->subject, 'grade' => $b->grade, 'topic' => $b->topic,
                'difficulty' => $b->difficulty, 'source' => $b->source, 'used' => $b->used_count,
                'author' => $b->teacher?->name,
                'mine' => $b->teacher_id === $teacher->id,
            ]),
            'filters' => $request->only('subject', 'difficulty', 'type', 'search', 'grade'),
            'grades' => \App\Support\BankAccess::teacherGrades($teacher),
        ]);
    }

    public function bankStore(Request $request): RedirectResponse
    {
        $teacher = $request->user();
        $data = $request->validate([
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.type' => ['nullable', 'in:mc,tf,desc,blank'],
            'questions.*.prompt' => ['required', 'string'],
            'questions.*.choices' => ['nullable', 'array'],
            'subject' => ['nullable', 'string', 'max:80'],
            'grade' => ['nullable', 'string', 'max:40'],
            'topic' => ['nullable', 'string', 'max:120'],
            'scope' => ['nullable', 'in:teacher,school'],
        ]);
        foreach ($data['questions'] as $q) {
            SmartQuestionBank::create([
                'school_id' => $teacher->school_id, 'teacher_id' => $teacher->id,
                'scope' => $data['scope'] ?? 'teacher',
                'type' => $q['type'] ?? 'mc', 'prompt' => $q['prompt'], 'choices' => $q['choices'] ?? [],
                'answer' => $q['answer'] ?? null, 'explanation' => $q['explanation'] ?? null,
                'subject' => $data['subject'] ?? null, 'grade' => $data['grade'] ?? null, 'topic' => $data['topic'] ?? null,
                'difficulty' => $q['difficulty'] ?? 'medium', 'source' => $q['source'] ?? 'manual',
            ]);
        }
        return back()->with('flash', count($data['questions']) . ' سؤال به بانک اضافه شد ✅');
    }

    public function bankDestroy(Request $request, SmartQuestionBank $question): RedirectResponse
    {
        abort_unless($question->teacher_id === $request->user()->id, 403);
        // نسخه‌بندی: قبل از حذف، snapshot ذخیره می‌شود تا نسخه‌های آزمون خراب نشوند (سؤال‌ها در آزمون کپی‌اند)
        $question->delete();
        return back()->with('flash', 'سؤال از بانک حذف شد');
    }

    public function report(Request $request, SmartExam $smartExam, SmartExamAnalyticsService $analytics): Response
    {
        abort_unless($smartExam->teacher_id === $request->user()->id, 403);
        return Inertia::render('Teacher/SmartExamReport', [
            'exam' => ['id' => $smartExam->id, 'title' => $smartExam->title, 'subject' => $smartExam->subject, 'kind' => $smartExam->kind],
            ...$analytics->examReport($smartExam),
            'printedAt' => Jalali::format(now(), true),
            'gamesEnabled' => SmartLab::flag('smart_games_enabled'),
        ]);
    }

    /** ساخت تمرین/بازی جبرانی از پاسخ‌های غلطِ آزمون → پیش‌نویسِ بازی در استودیو. */
    public function buildGame(Request $request, SmartExam $smartExam): RedirectResponse
    {
        abort_unless($smartExam->teacher_id === $request->user()->id, 403);
        abort_unless(SmartLab::flag('smart_games_enabled'), 403);
        $smartExam->load('questions');

        // سؤال‌هایی که بیشترین پاسخ غلط را داشته‌اند
        $attemptIds = $smartExam->attempts()->pluck('id');
        $wrongByIndex = \App\Models\SmartExamAnswer::whereIn('attempt_id', $attemptIds)
            ->where('correct', false)->get()->groupBy('q_index')->map->count();

        $questions = $smartExam->questions->values()
            ->filter(fn ($q, $i) => ($wrongByIndex[$i] ?? 0) > 0 && in_array($q->type, ['mc', 'tf']))
            ->take(10)->values();
        if ($questions->isEmpty()) {
            $questions = $smartExam->questions->whereIn('type', ['mc', 'tf'])->take(6)->values();
        }
        abort_if($questions->isEmpty(), 422);

        $game = \App\Models\EduGame::create([
            'school_id' => $smartExam->school_id, 'teacher_id' => $smartExam->teacher_id,
            'template_key' => 'snake', 'title' => 'جبرانیِ ' . $smartExam->title,
            'description' => 'بازیِ جبرانی بر اساس اشتباهاتِ آزمون', 'subject' => $smartExam->subject,
            'grade' => $smartExam->grade, 'difficulty' => 'easy', 'status' => 'draft',
        ]);
        foreach ($questions as $i => $q) {
            \App\Models\EduGameQuestion::create([
                'edu_game_id' => $game->id, 'type' => $q->type, 'prompt' => $q->prompt,
                'choices' => $q->choices ?? [], 'explanation' => $q->explanation, 'points' => 10, 'sort' => $i,
            ]);
        }
        // هدف‌گیریِ همان دانش‌آموزان
        foreach ($smartExam->targets as $t) {
            $game->targets()->create($t->only('classroom_id', 'theme_id', 'student_id'));
        }

        return redirect()->route('teacher.studio.edit', $game->id)
            ->with('flash', 'پیش‌نویسِ بازیِ جبرانی از اشتباهاتِ آزمون ساخته شد — قالب/تم را انتخاب و منتشر کنید 🎮');
    }

    // ── کمک‌کننده‌ها ──

    private function attributes($teacher, array $d): array
    {
        return [
            'school_id' => $teacher->school_id, 'teacher_id' => $teacher->id,
            'title' => $d['title'], 'description' => $d['description'] ?? null,
            'grade' => $d['grade'] ?? null, 'subject' => $d['subject'] ?? null, 'book' => $d['book'] ?? null,
            'chapter' => $d['chapter'] ?? null, 'topic' => $d['topic'] ?? null, 'goal' => $d['goal'] ?? null,
            'kind' => $d['kind'] ?? 'practice', 'status' => $d['status'] ?? 'draft',
            'adaptive' => (bool) ($d['adaptive'] ?? false), 'rules' => $d['rules'] ?? [],
            'opens_at' => $d['opens_at'] ?? null, 'closes_at' => $d['closes_at'] ?? null,
        ];
    }

    private function syncQuestions(SmartExam $exam, array $questions): void
    {
        $exam->questions()->delete();
        $meta = ['subject' => $exam->subject, 'grade' => $exam->grade, 'book' => $exam->book, 'chapter' => $exam->chapter, 'topic' => $exam->topic, 'source' => 'manual'];
        foreach (array_values($questions) as $i => $q) {
            SmartExamQuestion::create([
                'smart_exam_id' => $exam->id, 'type' => $q['type'] ?? 'mc', 'prompt' => $q['prompt'],
                'choices' => $q['choices'] ?? [], 'answer' => $q['answer'] ?? null,
                'explanation' => $q['explanation'] ?? null, 'difficulty' => $q['difficulty'] ?? 'medium',
                'points' => $q['points'] ?? 1, 'topic' => $q['topic'] ?? ($exam->topic ?? null),
                'goal' => $q['goal'] ?? null, 'source' => $q['source'] ?? 'manual', 'sort' => $i,
            ]);
            // ثبتِ خودکار در بانک سؤالات (با نامِ درس و معلم)
            if ($exam->teacher) {
                \App\Support\BankAccess::autosave($exam->teacher, $q, $meta);
            }
        }
    }

    private function syncTargets(SmartExam $exam, array $d): void
    {
        $exam->targets()->delete();
        foreach (($d['target_classrooms'] ?? []) as $id) {
            $exam->targets()->create(['classroom_id' => $id]);
        }
        foreach (($d['target_themes'] ?? []) as $id) {
            $exam->targets()->create(['theme_id' => $id]);
        }
        foreach (($d['target_students'] ?? []) as $id) {
            $exam->targets()->create(['student_id' => $id]);
        }
    }

    /** امنیت: کلاس/گروه/دانش‌آموزِ هدف باید متعلق به خودِ معلم باشند. */
    private function assertTargetsOwned($teacher, array $d): void
    {
        $myClassroomIds = Classroom::where('teacher_id', $teacher->id)->pluck('id');
        foreach (($d['target_classrooms'] ?? []) as $id) {
            abort_unless($myClassroomIds->contains($id), 403, 'کلاس انتخاب‌شده متعلق به شما نیست.');
        }
        $myStudentIds = \App\Models\User::whereHas('classrooms', fn ($q) => $q->whereIn('classrooms.id', $myClassroomIds))->pluck('id');
        foreach (($d['target_students'] ?? []) as $id) {
            abort_unless($myStudentIds->contains($id), 403, 'دانش‌آموز انتخاب‌شده در کلاس‌های شما نیست.');
        }
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:600'],
            'grade' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:80'],
            'book' => ['nullable', 'string', 'max:120'],
            'chapter' => ['nullable', 'string', 'max:120'],
            'topic' => ['nullable', 'string', 'max:120'],
            'goal' => ['nullable', 'string', 'max:300'],
            'kind' => ['nullable', 'in:diagnostic,practice,class,formal,remedial,game'],
            'status' => ['nullable', 'in:draft,review,scheduled,published,closed,archived'],
            'adaptive' => ['nullable', 'boolean'],
            'rules' => ['nullable', 'array'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date'],
            'target_classrooms' => ['nullable', 'array'],
            'target_classrooms.*' => ['integer'],
            'target_themes' => ['nullable', 'array'],
            'target_themes.*' => ['integer'],
            'target_students' => ['nullable', 'array'],
            'target_students.*' => ['integer'],
            'questions' => ['required', 'array', 'min:1', 'max:60'],
            'questions.*.type' => ['nullable', 'in:mc,tf,desc,blank'],
            'questions.*.prompt' => ['required', 'string', 'max:600'],
            'questions.*.choices' => ['nullable', 'array'],
            'questions.*.points' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);
    }
}
