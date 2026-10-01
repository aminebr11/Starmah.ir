<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\SmartExam;
use App\Models\SmartExamAnswer;
use App\Models\SmartExamAttempt;
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
    use \App\Http\Controllers\Concerns\BuildsAiQuestions;
    /** صفحه‌ی اصلیِ آزمون‌ها: دسته‌بندی‌شده (منتشر، در انتظار، آرشیو) و به تفکیکِ درس. */
    public function lab(Request $request): Response
    {
        $teacher = $request->user();
        $exams = SmartExam::where('teacher_id', $teacher->id)
            ->withCount(['questions', 'attempts'])->latest()->get()
            ->map(fn ($e) => $this->card($e));

        return Inertia::render('Teacher/SmartExamHub', [
            'items' => $exams->values(),
            'bankCount' => SmartQuestionBank::where('teacher_id', $teacher->id)->count(),
            'aiCount' => (int) \App\Models\SmartExamAiRequest::where('teacher_id', $teacher->id)->sum('produced'),
        ]);
    }

    /** صفحه‌ی جداگانه‌ی ساختِ آزمونِ تازه (در منو نیست؛ از دکمه‌ی «ساختِ آزمونِ جدید» باز می‌شود). */
    public function create(Request $request): Response
    {
        $teacher = $request->user();

        return Inertia::render('Teacher/SmartExamLab', array_merge($this->formData($teacher), [
            'flags' => SmartLab::config(),
            'classrooms' => Classroom::where('teacher_id', $teacher->id)->get(['id', 'name', 'grade']),
        ]));
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
            // دکمه‌ی طراحی همیشه دیده شود؛ اگر کلید نباشد «حالتِ نمونه» و پیامِ روشن داریم
            'aiEnabled' => SmartLab::flag('smart_ai_enabled'),
            'aiConfigured' => \App\Support\AiConfig::configured(),
            'adaptiveEnabled' => SmartLab::flag('smart_adaptive_enabled'),
            'classes' => \App\Support\Curriculum::teacherClasses($teacher),
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
            'bucket' => $this->bucket($e),
        ];
    }

    /** دسته‌ی نمایش: منتشرشده (در دسترس)، در انتظارِ انتشار، آرشیو (بسته، بایگانی یا پایان‌یافته). */
    private function bucket(SmartExam $e): string
    {
        if (in_array($e->status, ['closed', 'archived'], true)) return 'archived';
        if ($e->status === 'published') {
            if ($e->closes_at && now()->greaterThan($e->closes_at)) return 'archived';
            if ($e->opens_at && now()->lessThan($e->opens_at)) return 'pending';
            return 'published';
        }

        return 'pending';
    }

    public function edit(Request $request, SmartExam $smartExam): Response
    {
        abort_unless($smartExam->teacher_id === $request->user()->id, 403);
        $smartExam->load('questions', 'targets');
        return Inertia::render('Teacher/SmartExamLab', array_merge(
            $this->formData($request->user()),
            [
                'flags' => SmartLab::config(),
                'classrooms' => Classroom::where('teacher_id', $request->user()->id)->get(['id', 'name', 'grade']),
                'editing' => [
                    'id' => $smartExam->id,
                    ...$smartExam->only(['title', 'description', 'level', 'grade', 'subject', 'book', 'chapter', 'chapter_id', 'topic', 'goal', 'kind', 'status', 'adaptive']),
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
                        'bloom' => $q->bloom, 'bank_id' => $q->bank_id,
                    ])->values(),
                ],
            ]
        ));
    }

    /**
     * پیش‌نمایشِ واقعیِ آزمون — همان صفحه‌ای که دانش‌آموز می‌بیند.
     *
     * تا پیش از این تنها راهِ دیدنِ آزمون از چشمِ دانش‌آموز، منتشر کردنش بود.
     * حالا معلم می‌تواند روی نسخه‌ی پیش‌نویس هم آزمون را «بدهد»، غلط‌ها را
     * ببیند، برگردد و اصلاح کند و بعد منتشر کند.
     *
     * هیچ تلاشی (attempt) ساخته نمی‌شود، ذخیره‌ی خودکار خاموش است و ارسالِ
     * نهایی فقط یک نتیجه‌ی محلی نشان می‌دهد — نه نمره‌ای ثبت می‌شود نه امتیازی.
     */
    public function preview(Request $request, SmartExam $smartExam): Response
    {
        abort_unless($smartExam->teacher_id === $request->user()->id, 403);
        $smartExam->load('questions');

        $rules = $smartExam->rules ?? [];
        $questions = $smartExam->questions->values()->map(function ($q, $i) {
            $choices = collect($q->choices ?? [])->map(fn ($c) => [
                'value' => $c['value'] ?? '',
                // در پیش‌نمایش پاسخِ درست هم می‌آید تا معلم بتواند صحتش را بسنجد
                'correct' => (bool) ($c['correct'] ?? false),
            ])->values();

            return [
                'i' => $i, 'type' => $q->type, 'prompt' => $q->prompt, 'media' => $q->media_path,
                'choices' => $choices, 'points' => $q->points,
                'answer' => $q->answer, 'explanation' => $q->explanation,
                'difficulty' => $q->difficulty,
            ];
        });

        return Inertia::render('Student/SmartExamTake', [
            'exam' => [
                'id' => $smartExam->id, 'title' => $smartExam->title, 'subject' => $smartExam->subject,
                'rules' => $rules, 'onePerPage' => (bool) ($rules['one_per_page'] ?? true),
            ],
            'token' => null,
            'questions' => $questions,
            'saved' => [],
            'preview' => [
                'back' => route('teacher.smart.edit', $smartExam->id),
                'status' => $smartExam->status,
                'empty' => $smartExam->questions->isEmpty(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $teacher = $request->user();
        $data = $this->validated($request);
        $this->assertTargetsOwned($teacher, $data);

        $exam = SmartExam::create($this->attributes($teacher, $data));
        $this->syncQuestions($exam, $data['questions']);
        $this->syncTargets($exam, $data);

        if ($exam->status === 'published') {
            $this->notifyTargets($exam);
        }

        AuditLog::record($teacher, 'ساخت آزمون هوشمند', "آزمون «{$exam->title}» ساخته شد");
        return redirect()->route('teacher.smart.lab')->with('flash', 'آزمون هوشمند ساخته شد 🧪');
    }

    public function update(Request $request, SmartExam $smartExam): RedirectResponse
    {
        abort_unless($smartExam->teacher_id === $request->user()->id, 403);
        $data = $this->validated($request);
        $this->assertTargetsOwned($request->user(), $data);

        $wasPublished = $smartExam->status === 'published';

        // ویرایش روی همان آزمون ذخیره می‌شود (بدون ساختِ نسخه‌ی جدید).
        $smartExam->update($this->attributes($request->user(), $data));
        $smartExam->increment('version');
        $this->syncQuestions($smartExam, $data['questions']);
        $this->syncTargets($smartExam, $data);

        if ($smartExam->status === 'published' && ! $wasPublished) {
            $this->notifyTargets($smartExam);
        }

        return redirect()->route('teacher.smart.lab')->with('flash', 'تغییرات روی همین آزمون ذخیره شد ✅');
    }

    public function status(Request $request, SmartExam $smartExam): RedirectResponse
    {
        abort_unless($smartExam->teacher_id === $request->user()->id, 403);
        $data = $request->validate(['status' => ['required', 'in:draft,review,scheduled,published,closed,archived']]);
        $wasPublished = $smartExam->status === 'published';
        $smartExam->update(['status' => $data['status']]);
        // فقط هنگامِ انتشارِ تازه اعلان بده
        if ($data['status'] === 'published' && ! $wasPublished) {
            $this->notifyTargets($smartExam);
        }
        return back()->with('flash', 'وضعیت آزمون به‌روزرسانی شد');
    }

    /** اعلانِ انتشارِ آزمونِ هوشمند به دانش‌آموزانِ هدف (زنگوله/اعلان‌ها). */
    private function notifyTargets(SmartExam $exam): void
    {
        $exam->loadMissing('targets');
        $classroomIds = Classroom::where('teacher_id', $exam->teacher_id)->pluck('id');

        if ($exam->targets->isEmpty()) {
            // بدون هدفِ صریح → همه‌ی دانش‌آموزانِ کلاس‌های معلم
            $ids = \App\Models\User::whereHas('classrooms', fn ($q) => $q->whereIn('classrooms.id', $classroomIds))->pluck('id');
        } else {
            $ids = collect();
            $tClass = $exam->targets->pluck('classroom_id')->filter();
            $tTheme = $exam->targets->pluck('theme_id')->filter();
            $tStud = $exam->targets->pluck('student_id')->filter();
            if ($tClass->isNotEmpty()) {
                $ids = $ids->merge(\App\Models\User::whereHas('classrooms', fn ($q) => $q->whereIn('classrooms.id', $tClass))->pluck('id'));
            }
            if ($tTheme->isNotEmpty()) {
                $ids = $ids->merge(\App\Models\User::whereIn('theme_id', $tTheme)
                    ->whereHas('classrooms', fn ($q) => $q->whereIn('classrooms.id', $classroomIds))->pluck('id'));
            }
            $ids = $ids->merge($tStud)->unique()->values();
        }
        if ($ids->isEmpty()) {
            return;
        }
        $ann = Announcement::create([
            'school_id' => $exam->school_id, 'sender_id' => $exam->teacher_id,
            'title' => '🧠 آزمون هوشمند جدید — ' . $exam->title,
            'audience' => 'personal',
            'body' => "یک آزمون هوشمندِ جدید برای شما منتشر شد: «{$exam->title}».\nبرای شرکت، روی همین اعلان بزنید.",
            'link' => '/student/smart-exams',
        ]);
        $ann->recipients()->sync($ids->all());
    }

    public function destroy(Request $request, SmartExam $smartExam): RedirectResponse
    {
        abort_unless($smartExam->teacher_id === $request->user()->id, 403);
        $smartExam->delete();
        return back()->with('flash', 'آزمون حذف شد (آزمون‌های قدیمی و نتایجشان دست‌نخورده‌اند).');
    }

    /**
     * «آزادسازیِ آزمون» — حذفِ تلاش‌ها (و پاسخ‌ها) تا دانش‌آموزان بتوانند دوباره در آزمون شرکت کنند.
     * بدون student_ids → همه؛ با آرایه‌ای از شناسه‌ها → فقط همان دانش‌آموزان.
     */
    public function release(Request $request, SmartExam $smartExam): RedirectResponse
    {
        abort_unless($smartExam->teacher_id === $request->user()->id, 403);
        $data = $request->validate([
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer'],
        ]);

        $q = $smartExam->attempts();
        if (! empty($data['student_ids'])) {
            $q->whereIn('student_id', $data['student_ids']);
        }
        $attemptIds = $q->pluck('id');
        if ($attemptIds->isEmpty()) {
            return back()->with('flash', 'تلاشی برای حذف پیدا نشد.');
        }

        \App\Models\SmartExamAnswer::whereIn('attempt_id', $attemptIds)->delete();
        SmartExamAttempt::whereIn('id', $attemptIds)->delete();

        $n = $attemptIds->count();
        $who = empty($data['student_ids'])
            ? 'همه‌ی دانش‌آموزان'
            : count($data['student_ids']) . ' دانش‌آموز';
        return back()->with('flash', "آزمون آزاد شد؛ {$n} تلاش پاک شد و {$who} می‌توانند دوباره در آزمون شرکت کنند.");
    }

    /** دستیار هوشمند طراحی سؤال. */
    public function aiGenerate(Request $request, SmartExamAiService $ai): JsonResponse
    {
        if (! SmartLab::flag('smart_ai_enabled')) {
            return response()->json(['ok' => false, 'mode' => 'unavailable', 'questions' => [],
                'message' => 'طراحیِ سؤال با هوش مصنوعی در «آزمایشگاهِ هوشمند» توسطِ ادمینِ کل خاموش است (ادمین کل → آزمایشگاه هوشمند → «تولید سؤال با هوش مصنوعی»).']);
        }
        return $this->aiRespond($request, $ai, 'exam', ['mc', 'tf', 'blank', 'desc'], 20);
    }

    // ── بانک سؤال ──
    /** انتخابگرِ بانک برای افزودن سؤال به آزمون (JSON) — با فیلترِ درس/شماره‌درس/جست‌وجو. */
    public function bankPick(Request $request): \Illuminate\Http\JsonResponse
    {
        $teacher = $request->user();
        $filters = $request->only('grade', 'subject', 'chapter_id', 'chapter', 'uncategorized', 'lesson_no', 'topic', 'difficulty', 'source', 'search', 'exclude');
        $filters['types'] = (array) ($request->types ?: ['mc', 'tf', 'blank', 'desc']);
        $rows = \App\Support\BankAccess::search($teacher, $filters)->with('teacher:id,name')
            ->orderByDesc('used_count')->latest('id')->limit(200)->get();
        return response()->json([
            'questions' => $rows->map(fn ($b) => \App\Support\BankAccess::row($b, $teacher))->values(),
            'tree' => \App\Support\BankAccess::facetTree($teacher, $filters['types']),
            'facets' => \App\Support\BankAccess::pickerFacets($teacher),
        ]);
    }

    /** صفحه‌ی بانک به «بانکِ سؤالاتِ من» منتقل شد (همیشه در دسترس، با دسته‌بندیِ فصل). */
    public function bank(Request $request): RedirectResponse
    {
        return redirect()->route('teacher.mybank');
    }

    public function bankStore(Request $request): RedirectResponse
    {
        $teacher = $request->user();
        $data = $request->validate([
            'questions' => ['required', 'array', 'min:1', 'max:60'],
            'questions.*.type' => ['nullable', 'in:mc,tf,desc,blank'],
            'questions.*.prompt' => ['required', 'string', 'max:1000'],
            'questions.*.choices' => ['nullable', 'array'],
            'questions.*.answer' => ['nullable'],
            'questions.*.explanation' => ['nullable', 'string', 'max:1500'],
            'questions.*.difficulty' => ['nullable', 'in:easy,medium,hard'],
            'questions.*.source' => ['nullable', 'string', 'max:20'],
            'classroom_id' => ['nullable', 'integer'],
            'grade' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:80'],
            'chapter_id' => ['nullable', 'integer'],
            'chapter' => ['nullable', 'string', 'max:160'],
            'topic' => ['nullable', 'string', 'max:160'],
            'goal' => ['nullable', 'string', 'max:300'],
        ]);
        $ctx = \App\Support\Curriculum::resolve($data, $teacher);
        $n = 0;
        foreach ($data['questions'] as $q) {
            $n += \App\Support\BankAccess::autosave($teacher, $q, $ctx + ['source' => $q['source'] ?? 'manual']) ? 1 : 0;
        }
        return back()->with('flash', \App\Support\Jalali::fa((string) $n) . ' سؤال در بانک ثبت شد ✅');
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

    /** برگه‌ی چاپیِ نتایجِ آزمون (A4) — تمیز، بدونِ منو و دکمه، در اپ هم چاپ می‌شود. */
    public function reportPrint(Request $request, SmartExam $smartExam, SmartExamAnalyticsService $analytics): \Illuminate\Contracts\View\View
    {
        abort_unless($smartExam->teacher_id === $request->user()->id, 403);
        $smartExam->loadCount('questions');

        return view('print.exam-report', \App\Http\Controllers\PrintController::header($smartExam->school_id, $request) + $analytics->examReport($smartExam) + [
            'title' => 'گزارشِ نتایجِ آزمون: ' . $smartExam->title,
            'meta' => array_filter([
                'درس' => $smartExam->subject, 'پایه' => $smartExam->grade, 'مبحث' => $smartExam->topic,
                'تعداد سؤال' => $smartExam->questions_count, 'معلم' => $request->user()->name,
                'تاریخِ برگزاری' => $smartExam->opens_at ? Jalali::format($smartExam->opens_at) : Jalali::format($smartExam->created_at),
            ], fn ($v) => $v !== null && $v !== ''),
            'back' => route('teacher.smart.report', $smartExam->id),
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
        $ctx = \App\Support\Curriculum::resolve($d, $teacher);
        return [
            'school_id' => $teacher->school_id, 'teacher_id' => $teacher->id,
            'title' => $d['title'], 'description' => $d['description'] ?? null,
            'level' => $ctx['level'], 'grade' => $ctx['grade'], 'subject' => $ctx['subject'] ?: null,
            'book' => $d['book'] ?? null, 'chapter_id' => $ctx['chapter_id'], 'chapter' => $ctx['chapter'],
            'topic' => $ctx['topic'], 'goal' => $ctx['goal'],
            'kind' => $d['kind'] ?? 'practice', 'status' => $d['status'] ?? 'draft',
            'adaptive' => (bool) ($d['adaptive'] ?? false), 'rules' => $d['rules'] ?? [],
            'opens_at' => $d['opens_at'] ?? null, 'closes_at' => $d['closes_at'] ?? null,
        ];
    }

    private function syncQuestions(SmartExam $exam, array $questions): void
    {
        // «تعدادِ استفاده» فقط برای سؤالی بالا می‌رود که تازه به این آزمون اضافه شده، نه در هر ذخیره
        $linked = $exam->questions()->pluck('bank_id')->filter()->all();
        $exam->questions()->delete();
        $meta = [
            'level' => $exam->level, 'grade' => $exam->grade, 'subject' => $exam->subject, 'book' => $exam->book,
            'chapter_id' => $exam->chapter_id, 'chapter' => $exam->chapter, 'topic' => $exam->topic, 'goal' => $exam->goal,
            'source' => 'manual',
        ];
        foreach (array_values($questions) as $i => $q) {
            // ثبت/پیوند در بانک سؤالات با دسته‌بندیِ کامل (پایه، درس، فصل، مبحث)
            $bankId = $exam->teacher ? \App\Support\BankAccess::autosave($exam->teacher, $q,
                $meta + ['count_use' => ! in_array($q['bank_id'] ?? null, $linked, false)]) : null;
            SmartExamQuestion::create([
                'smart_exam_id' => $exam->id, 'bank_id' => $bankId, 'type' => $q['type'] ?? 'mc', 'prompt' => $q['prompt'],
                'choices' => \App\Support\BankAccess::cleanChoices($q['choices'] ?? []), 'answer' => $q['answer'] ?? null,
                'explanation' => $q['explanation'] ?? null,
                'difficulty' => in_array($q['difficulty'] ?? null, ['easy', 'medium', 'hard'], true) ? $q['difficulty'] : 'medium',
                'bloom' => $q['bloom'] ?? null,
                'points' => $q['points'] ?? 1, 'topic' => ($q['topic'] ?? null) ?: $exam->topic,
                'goal' => $q['goal'] ?? null, 'source' => $q['source'] ?? 'manual', 'sort' => $i,
            ]);
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
            'classroom_id' => ['nullable', 'integer'],
            'level' => ['nullable', 'string', 'max:40'],
            'grade' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:80'],
            'book' => ['nullable', 'string', 'max:120'],
            'chapter_id' => ['nullable', 'integer'],
            'chapter' => ['nullable', 'string', 'max:160'],
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
            // هر زیرکلیدِ سؤال باید قاعده داشته باشد، وگرنه validate() آن را دور می‌ریزد —
            // تا پیش از این «توضیح»، «پاسخِ جای خالی/تشریحی» و «مبحث» هنگامِ ذخیره گم می‌شدند.
            'questions.*.answer' => ['nullable'],
            'questions.*.explanation' => ['nullable', 'string', 'max:1500'],
            'questions.*.topic' => ['nullable', 'string', 'max:160'],
            'questions.*.goal' => ['nullable', 'string', 'max:300'],
            'questions.*.difficulty' => ['nullable', 'in:easy,medium,hard'],
            'questions.*.bloom' => ['nullable', 'in:remember,understand,apply,analyze'],
            'questions.*.bank_id' => ['nullable', 'integer'],
            'questions.*.source' => ['nullable', 'string', 'max:20'],
        ]);
    }
}
