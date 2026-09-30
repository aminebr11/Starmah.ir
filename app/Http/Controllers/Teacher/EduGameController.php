<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\EduGame;
use App\Models\EduGameQuestion;
use App\Models\GameTemplate;
use App\Models\Theme;
use App\Support\Jalali;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/** استودیوی ساخت بازی (معلم) — قالب + تم + سؤال + قوانین + انتشار. */
class EduGameController extends Controller
{
    use \App\Http\Controllers\Concerns\BuildsAiQuestions;
    /** صفحه‌ی اصلیِ استودیو: بازی‌ها دسته‌بندی‌شده (منتشر، در انتظار، آرشیو) و به تفکیکِ درس. */
    public function index(Request $request): Response
    {
        $teacher = $request->user();
        $games = EduGame::where('teacher_id', $teacher->id)->with('template:key,name,icon', 'theme:id,name,emoji')
            ->withCount(['questions', 'attempts'])->latest()->get()->map(fn ($g) => $this->card($g));

        return Inertia::render('Teacher/GameHub', [
            'items' => $games->values(),
            'hasClass' => Classroom::where('teacher_id', $teacher->id)->exists(),
        ]);
    }

    /** صفحه‌ی جداگانه‌ی ساختِ بازیِ تازه (در منو نیست؛ از دکمه‌ی «ساختِ بازیِ جدید» باز می‌شود). */
    public function create(Request $request): Response
    {
        return Inertia::render('Teacher/GameStudio', $this->payload($request));
    }

    /** تولید سؤالِ بازی با هوش مصنوعی (مشابه آزمون هوشمند). */
    public function aiGenerate(Request $request, \App\Services\SmartExamAiService $ai): \Illuminate\Http\JsonResponse
    {
        // بازی‌ها چهارگزینه‌ای، درست/نادرست و پاسخِ کوتاه (= جای خالی) دارند
        return $this->aiRespond($request, $ai, 'game', ['mc', 'tf', 'blank'], 15);
    }

    /** سؤال‌های بانک (قابل‌مشاهده برای معلم) برای استفاده در بازی. */
    public function bankQuestions(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $filters = $request->only('grade', 'subject', 'chapter_id', 'chapter', 'uncategorized', 'lesson_no', 'topic', 'difficulty', 'source', 'search', 'exclude');
        // بازی: چهارگزینه‌ای، درست/نادرست و جای خالی (= پاسخِ کوتاه)
        $filters['types'] = array_values(array_intersect((array) ($request->types ?: ['mc', 'tf', 'blank']), ['mc', 'tf', 'blank']));
        $rows = \App\Support\BankAccess::search($user, $filters)->with('teacher:id,name')
            ->orderByDesc('used_count')->latest('id')->limit(200)->get();
        return response()->json([
            'questions' => $rows->map(fn ($b) => \App\Support\BankAccess::row($b, $user))->values(),
            'tree' => \App\Support\BankAccess::facetTree($user, $filters['types']),
            'facets' => \App\Support\BankAccess::pickerFacets($user),
        ]);
    }

    private function payload(Request $request, array $extra = []): array
    {
        $teacher = $request->user();
        $classroom = Classroom::where('teacher_id', $teacher->id)->first();

        return array_merge([
            'templates' => GameTemplate::where('is_active', true)->orderBy('sort')->get(['key', 'name', 'icon', 'description', 'config']),
            'themes'    => Theme::where('is_active', true)->where('key', '!=', 'brand')->orderBy('sort')
                ->get(['id', 'name', 'emoji'])->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'emoji' => $t->emoji]),
            'subjects'  => $classroom ? collect($classroom->subjectNames())->map(fn ($s) => is_array($s) ? $s['name'] : $s)->values() : [],
            'grade'     => $classroom?->grade,
            'groups'    => $this->groups($classroom),
            'hasClass'  => (bool) $classroom,
            'classes'   => \App\Support\Curriculum::teacherClasses($teacher),
            'editing'   => null,
        ], $extra);
    }

    /** گروه‌ها (تیم‌ها) + دانش‌آموزانِ کلاسِ معلم. */
    private function groups(?Classroom $classroom): array
    {
        if (! $classroom) return [];
        return $classroom->students()->with('theme:id,name,emoji')->get()
            ->groupBy('theme_id')->map(function ($g) {
                $t = $g->first()->theme;
                return [
                    'theme_id' => $t?->id,
                    'name' => $t ? "{$t->emoji} {$t->name}" : 'بدون تیم',
                    'students' => $g->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values(),
                ];
            })->values()->all();
    }

    private function card(EduGame $g): array
    {
        return [
            'id' => $g->id, 'title' => $g->title, 'template_key' => $g->template_key,
            'template' => optional($g->template)->name ?? $g->template_key,
            'icon' => optional($g->template)->icon ?? '🎮',
            'theme' => optional($g->theme)->name, 'theme_emoji' => optional($g->theme)->emoji,
            'subject' => $g->subject, 'grade' => $g->grade, 'difficulty' => $g->difficulty,
            'status' => $g->status, 'live' => $g->isLive(),
            'cover' => $g->cover_path ? Storage::disk('public')->url($g->cover_path) : null,
            'questions' => $g->questions_count, 'plays' => $g->attempts_count,
            'publish_at' => $g->publish_at?->toDateTimeString(),
            'jpublish' => $g->publish_at ? Jalali::format($g->publish_at, true) : null,
            'date' => Jalali::format($g->created_at),
            'jclose' => $g->close_at ? Jalali::format($g->close_at, true) : null,
            'bucket' => $this->bucket($g),
        ];
    }

    /** دسته‌ی نمایش: منتشرشده (در دسترس)، در انتظارِ انتشار، آرشیو (بایگانی یا پایان‌یافته). */
    private function bucket(EduGame $g): string
    {
        if (in_array($g->status, ['archived', 'disabled'], true)) return 'archived';
        if ($g->status === 'published') {
            if ($g->close_at && now()->greaterThan($g->close_at)) return 'archived';
            if ($g->publish_at && now()->lessThan($g->publish_at)) return 'pending';
            return 'published';
        }

        return 'pending';
    }

    /** بارگذاری کاملِ یک بازی برای ویرایش. */
    public function show(Request $request, EduGame $eduGame): Response
    {
        abort_unless($eduGame->teacher_id === $request->user()->id, 403);
        $eduGame->load('questions', 'targets');
        return Inertia::render('Teacher/GameStudio', $this->payload($request, [
            'editing' => [
                'id' => $eduGame->id, 'title' => $eduGame->title, 'description' => $eduGame->description,
                'template_key' => $eduGame->template_key, 'theme_id' => $eduGame->theme_id,
                'subject' => $eduGame->subject, 'grade' => $eduGame->grade, 'difficulty' => $eduGame->difficulty,
                'level' => $eduGame->level, 'chapter_id' => $eduGame->chapter_id, 'chapter' => $eduGame->chapter,
                'topic' => $eduGame->topic, 'goal' => $eduGame->goal,
                'status' => $eduGame->status, 'rules' => $eduGame->rules ?? [],
                'publish_at' => optional($eduGame->publish_at)->format('Y-m-d H:i'),
                'close_at' => optional($eduGame->close_at)->format('Y-m-d H:i'),
                'target_themes' => $eduGame->targets->pluck('theme_id')->filter()->values(),
                'target_students' => $eduGame->targets->pluck('student_id')->filter()->values(),
                'questions' => $eduGame->questions->map(fn ($q) => [
                    'type' => $q->type, 'prompt' => $q->prompt, 'choices' => $q->choices ?? [],
                    'hint1' => $q->hint1, 'hint2' => $q->hint2, 'explanation' => $q->explanation,
                    'points' => $q->points, 'media_url' => $q->media_path,
                    'difficulty' => $q->difficulty, 'bloom' => $q->bloom, 'topic' => $q->topic,
                    'source' => $q->source, 'bank_id' => $q->bank_id,
                ])->values(),
            ],
        ]));
    }

    /**
     * پیش‌نمایشِ واقعیِ بازی — دقیقاً همان صفحه‌ای که دانش‌آموز می‌بیند.
     *
     * ── چرا لازم شد ───────────────────────────────────────────────────
     * معلم تا پیش از این فقط فرمِ ساخت را می‌دید و تنها راهِ دیدنِ نتیجه
     * «انتشار» بود؛ یعنی اگر سؤالی غلط یا گزینه‌ای جابه‌جا بود، دانش‌آموزها
     * پیش از معلم می‌دیدند.
     *
     * همان کامپوننتِ Student/GamePlayer با همان ساختارِ داده رندر می‌شود،
     * ولی با پرچمِ preview: هیچ تلاشی ساخته نمی‌شود، هیچ امتیازی ثبت
     * نمی‌شود و دکمه‌ی پایان چیزی به سرور نمی‌فرستد. روی بازیِ پیش‌نویس
     * هم کار می‌کند، پس اصلاح پیش از انتشار ممکن است.
     */
    public function preview(Request $request, EduGame $eduGame): Response
    {
        abort_unless($eduGame->teacher_id === $request->user()->id, 403);
        $eduGame->load(['template', 'theme', 'questions']);

        return Inertia::render('Student/GamePlayer', [
            'game' => [
                'id' => $eduGame->id, 'title' => $eduGame->title, 'desc' => $eduGame->description,
                'template' => $eduGame->template_key, 'template_name' => optional($eduGame->template)->name,
                'board_html' => optional($eduGame->template)->board_html,
                'board_css' => optional($eduGame->template)->board_css,
                'difficulty' => $eduGame->difficulty,
                'rules' => $eduGame->rules ?? [],
                'theme' => $eduGame->theme ? [
                    'name' => $eduGame->theme->name, 'emoji' => $eduGame->theme->emoji,
                    'skin' => $eduGame->theme->skin,
                ] : null,
                'questions' => $eduGame->questions->map(fn ($q, $i) => [
                    'i' => $i, 'type' => $q->type, 'prompt' => $q->prompt,
                    'media' => $q->media_path,
                    'choices' => collect($q->choices ?? [])->map(fn ($c) => [
                        'value' => $c['value'] ?? '', 'correct' => (bool) ($c['correct'] ?? false),
                    ])->values(),
                    'hint1' => $q->hint1, 'hint2' => $q->hint2,
                    'explanation' => $q->explanation, 'points' => $q->points,
                ])->values(),
            ],
            'attempt' => ['status' => 'preview', 'progress' => [], 'score' => 0],
            'preview' => [
                'back' => route('teacher.studio.edit', $eduGame->id),
                'status' => $eduGame->status,
                'empty' => $eduGame->questions->isEmpty(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $teacher = $request->user();
        $classroom = Classroom::where('teacher_id', $teacher->id)->firstOrFail();
        $data = $this->validated($request);

        $game = EduGame::create($this->attributes($teacher, $classroom, $data));
        $this->syncQuestions($game, $data['questions']);
        $this->syncTargets($game, $data);
        if ($game->status === 'published') {
            $this->announcePublish($game, $classroom);
        }

        AuditLog::record($teacher, 'ساخت بازی آموزشی', "بازی «{$game->title}» ({$game->template_key}) ساخته شد");
        return redirect()->route('teacher.studio')->with('flash', 'بازی ساخته شد 🎮');
    }

    public function update(Request $request, EduGame $eduGame): RedirectResponse
    {
        abort_unless($eduGame->teacher_id === $request->user()->id, 403);
        $data = $this->validated($request);
        $classroom = Classroom::where('teacher_id', $request->user()->id)->first();

        // ویرایش اساسی (تغییر سؤال‌ها) پس از بازی‌شدن → نسخه‌ی جدید تا گزارش‌های قبلی حفظ شود
        $hasResults = $eduGame->attempts()->exists();
        if ($hasResults) {
            $new = $eduGame->replicate(['created_at', 'updated_at']);
            $new->version = $eduGame->version + 1;
            $new->status = 'draft';
            $new->fill($this->attributes($request->user(), $classroom, $data));
            $new->save();
            $this->syncQuestions($new, $data['questions']);
            $this->syncTargets($new, $data);
            $eduGame->update(['status' => 'archived']);
            AuditLog::record($request->user(), 'ویرایش اساسی بازی', "نسخه‌ی {$new->version} بازی «{$new->title}» ساخته شد (نسخه‌ی قبلی آرشیو شد)");
            return redirect()->route('teacher.studio')->with('flash', 'به‌دلیل وجود نتایج قبلی، نسخه‌ی جدید ساخته و نسخه‌ی قدیمی آرشیو شد ✅');
        }

        $wasPublished = $eduGame->status === 'published';
        $eduGame->update($this->attributes($request->user(), $classroom, $data));
        $this->syncQuestions($eduGame, $data['questions']);
        $this->syncTargets($eduGame, $data);
        if (! $wasPublished && $eduGame->status === 'published') {
            $this->announcePublish($eduGame, $classroom);
        }
        return redirect()->route('teacher.studio')->with('flash', 'بازی به‌روزرسانی شد ✅');
    }

    public function status(Request $request, EduGame $eduGame): RedirectResponse
    {
        abort_unless($eduGame->teacher_id === $request->user()->id, 403);
        $data = $request->validate(['status' => ['required', 'in:draft,published,archived,disabled']]);
        $wasPublished = $eduGame->status === 'published';
        $eduGame->update(['status' => $data['status']]);
        if (! $wasPublished && $eduGame->status === 'published') {
            $classroom = Classroom::where('teacher_id', $request->user()->id)->first();
            $this->announcePublish($eduGame, $classroom);
        }
        $label = ['draft' => 'پیش‌نویس', 'published' => 'منتشر', 'archived' => 'آرشیو', 'disabled' => 'غیرفعال'][$data['status']];
        return back()->with('flash', "وضعیت بازی: {$label}");
    }

    /** اعلانِ «بازی جدید» برای دانش‌آموزانِ هدف — در زنگوله و منوی اعلان‌ها. */
    private function announcePublish(EduGame $game, ?Classroom $classroom): void
    {
        if (! $classroom) {
            return;
        }
        $game->loadMissing('targets', 'template');

        $students = $classroom->students()->get(['users.id', 'theme_id']);
        if ($game->targets->isNotEmpty()) {
            $themeIds = $game->targets->pluck('theme_id')->filter();
            $studentIds = $game->targets->pluck('student_id')->filter();
            $students = $students->filter(fn ($s) => $studentIds->contains($s->id) || $themeIds->contains($s->theme_id));
        }
        if ($students->isEmpty()) {
            return;
        }

        $tName = optional($game->template)->name ?? 'بازی';
        $ann = \App\Models\Announcement::create([
            'school_id' => $game->school_id,
            'sender_id' => $game->teacher_id,
            'title' => '🎮 بازی جدید: ' . $game->title,
            'body' => "یک {$tName} جدید برایت منتشر شد! روی همین اعلان بزن و امتیاز بگیر ⚡",
            'audience' => 'personal',
            'link' => '/game-world',
        ]);
        $ann->recipients()->sync($students->pluck('id')->all());
    }

    public function duplicate(Request $request, EduGame $eduGame): RedirectResponse
    {
        abort_unless($eduGame->teacher_id === $request->user()->id, 403);
        $copy = $eduGame->replicate(['created_at', 'updated_at']);
        $copy->title = $eduGame->title . ' (کپی)';
        $copy->status = 'draft';
        $copy->save();
        foreach ($eduGame->questions as $q) {
            $nq = $q->replicate(['created_at', 'updated_at']);
            $nq->edu_game_id = $copy->id;
            $nq->save();
        }
        return back()->with('flash', 'بازی کپی شد 📋');
    }

    public function destroy(Request $request, EduGame $eduGame): RedirectResponse
    {
        abort_unless($eduGame->teacher_id === $request->user()->id, 403);
        if ($eduGame->cover_path) Storage::disk('public')->delete($eduGame->cover_path);
        $eduGame->delete();
        return back()->with('flash', 'بازی حذف شد');
    }

    /** داده‌ی گزارشِ یک بازی — مشترکِ صفحه‌ی گزارش و برگه‌ی چاپی. */
    public function reportData(EduGame $eduGame): array
    {
        $eduGame->load('questions');
        $attempts = $eduGame->attempts()->with('student:id,name')->get();
        $completed = $attempts->where('status', 'completed');

        $rows = $attempts->map(fn ($a) => [
            'name' => $a->student?->name, 'score' => $a->score, 'max' => $a->max_score,
            'percent' => $a->max_score ? (int) round($a->score / $a->max_score * 100) : 0,
            'status' => $a->status, 'hints' => $a->hints_used,
            'duration' => $a->duration_sec,
            'jdate' => $a->completed_at ? Jalali::format($a->completed_at, true) : null,
        ])->sortByDesc('percent')->values();

        // سؤال‌های سخت (بیشترین پاسخ غلط) از progress
        $wrong = [];
        foreach ($attempts as $a) {
            foreach (($a->progress['answers'] ?? []) as $i => $ans) {
                if (isset($ans['correct']) && ! $ans['correct']) {
                    $wrong[$i] = ($wrong[$i] ?? 0) + 1;
                }
            }
        }
        arsort($wrong);
        $hardQuestions = collect($wrong)->take(5)->map(fn ($cnt, $i) => [
            'prompt' => $eduGame->questions[$i]->prompt ?? "سؤال " . ($i + 1),
            'wrong' => $cnt,
        ])->values();

        return [
            'game' => ['id' => $eduGame->id, 'title' => $eduGame->title, 'template' => optional($eduGame->template)->name],
            'summary' => [
                'started' => $attempts->count(),
                'completed' => $completed->count(),
                'avg' => $completed->count() ? (int) round($completed->avg(fn ($a) => $a->max_score ? $a->score / $a->max_score * 100 : 0)) : 0,
                'avgDuration' => $completed->count() ? (int) round($completed->avg('duration_sec')) : 0,
                'hints' => $attempts->sum('hints_used'),
            ],
            'rows' => $rows,
            'hardQuestions' => $hardQuestions,
        ];
    }

    /** گزارش تحلیلیِ یک بازی. */
    public function report(Request $request, EduGame $eduGame): Response
    {
        abort_unless($eduGame->teacher_id === $request->user()->id, 403);

        return Inertia::render('Teacher/GameReport', $this->reportData($eduGame));
    }

    /**
     * برگه‌ی چاپیِ نتایجِ بازی (A4). چاپِ خودِ صفحه‌ی داشبورد منو و دکمه‌ها را هم
     * چاپ می‌کرد و داخلِ اپِ اندروید اصلاً کاری نمی‌کرد؛ این برگه تمیز و مستقل است.
     */
    public function reportPrint(Request $request, EduGame $eduGame): \Illuminate\Contracts\View\View
    {
        abort_unless($eduGame->teacher_id === $request->user()->id, 403);
        $eduGame->loadMissing('template', 'theme');

        return view('print.game-report', \App\Http\Controllers\PrintController::header($eduGame->school_id, $request) + $this->reportData($eduGame) + [
            'title' => 'گزارشِ نتایجِ بازی: ' . $eduGame->title,
            'meta' => array_filter([
                'قالب' => optional($eduGame->template)->name, 'درس' => $eduGame->subject, 'پایه' => $eduGame->grade,
                'تعداد سؤال' => $eduGame->questions->count(), 'معلم' => $request->user()->name,
                'تاریخِ ساخت' => Jalali::format($eduGame->created_at),
            ], fn ($v) => $v !== null && $v !== ''),
            'back' => route('teacher.studio.report', $eduGame->id),
        ]);
    }

    // ---------- کمک‌کننده‌ها ----------

    private function attributes($teacher, ?Classroom $classroom, array $data): array
    {
        $ctx = \App\Support\Curriculum::resolve($data + ['grade' => $classroom?->grade], $teacher);
        return [
            'school_id' => $teacher->school_id,
            'teacher_id' => $teacher->id,
            'template_key' => $data['template_key'],
            'theme_id' => $data['theme_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'level' => $ctx['level'],
            'subject' => $ctx['subject'] ?: null,
            'grade' => $ctx['grade'] ?: $classroom?->grade,
            'chapter_id' => $ctx['chapter_id'],
            'chapter' => $ctx['chapter'],
            'topic' => $ctx['topic'],
            'goal' => $ctx['goal'],
            'difficulty' => $data['difficulty'] ?? 'medium',
            'status' => $data['status'] ?? 'draft',
            'publish_at' => $data['publish_at'] ?? null,
            'close_at' => $data['close_at'] ?? null,
            'rules' => $data['rules'] ?? [],
        ];
    }

    private function syncQuestions(EduGame $game, array $questions): void
    {
        // «تعدادِ استفاده» فقط برای سؤالی بالا می‌رود که تازه به این بازی اضافه شده، نه در هر ذخیره
        $linked = $game->questions()->pluck('bank_id')->filter()->all();
        $game->questions()->delete();
        $meta = [
            'level' => $game->level, 'grade' => $game->grade, 'subject' => $game->subject,
            'chapter_id' => $game->chapter_id, 'chapter' => $game->chapter, 'topic' => $game->topic,
            'goal' => $game->goal, 'source' => 'manual',
        ];
        foreach (array_values($questions) as $i => $q) {
            $type = $q['type'] ?? 'mc';
            // سؤالِ بازی هم در بانک ثبت/پیوند می‌شود — با همان پایه، درس و فصل
            $bankId = $game->teacher && in_array($type, ['mc', 'tf', 'short'], true)
                ? \App\Support\BankAccess::autosave($game->teacher, $q + ['hint' => $q['hint1'] ?? null],
                    $meta + ['count_use' => ! in_array($q['bank_id'] ?? null, $linked, false)])
                : null;
            EduGameQuestion::create([
                'edu_game_id' => $game->id,
                'bank_id' => $bankId,
                'type' => $type,
                'prompt' => $q['prompt'],
                'media_path' => $q['media_url'] ?? null,
                'choices' => \App\Support\BankAccess::cleanChoices($q['choices'] ?? []),
                'hint1' => $q['hint1'] ?? null,
                'hint2' => $q['hint2'] ?? null,
                'explanation' => $q['explanation'] ?? null,
                'points' => $q['points'] ?? 10,
                'difficulty' => in_array($q['difficulty'] ?? null, ['easy', 'medium', 'hard'], true) ? $q['difficulty'] : ($game->difficulty ?: 'medium'),
                'bloom' => $q['bloom'] ?? null,
                'topic' => ($q['topic'] ?? null) ?: $game->topic,
                'source' => $q['source'] ?? 'manual',
                'sort' => $i,
            ]);
        }
    }

    private function syncTargets(EduGame $game, array $data): void
    {
        $game->targets()->delete();
        foreach (($data['target_themes'] ?? []) as $themeId) {
            $game->targets()->create(['theme_id' => $themeId]);
        }
        foreach (($data['target_students'] ?? []) as $sid) {
            $game->targets()->create(['student_id' => $sid]);
        }
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'template_key' => ['required', 'exists:game_templates,key'],
            'theme_id' => ['nullable', 'exists:themes,id'],
            'subject' => ['nullable', 'string', 'max:80'],
            'grade' => ['nullable', 'string', 'max:40'],
            'classroom_id' => ['nullable', 'integer'],
            'chapter_id' => ['nullable', 'integer'],
            'chapter' => ['nullable', 'string', 'max:160'],
            'topic' => ['nullable', 'string', 'max:160'],
            'goal' => ['nullable', 'string', 'max:300'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'status' => ['nullable', 'in:draft,published,archived,disabled'],
            'publish_at' => ['nullable', 'date'],
            'close_at' => ['nullable', 'date'],
            'rules' => ['nullable', 'array'],
            'target_themes' => ['nullable', 'array'],
            'target_themes.*' => ['integer'],
            'target_students' => ['nullable', 'array'],
            'target_students.*' => ['integer'],
            'questions' => ['required', 'array', 'min:1', 'max:50'],
            'questions.*.type' => ['nullable', 'in:mc,tf,short'],
            'questions.*.prompt' => ['required', 'string', 'max:400'],
            'questions.*.choices' => ['nullable', 'array'],
            'questions.*.points' => ['nullable', 'integer', 'min:1', 'max:100'],
            // هر زیرکلید قاعده می‌خواهد، وگرنه دور ریخته می‌شود (تصویر و توضیحِ سؤال گم می‌شد)
            'questions.*.hint1' => ['nullable', 'string', 'max:255'],
            'questions.*.hint2' => ['nullable', 'string', 'max:255'],
            'questions.*.explanation' => ['nullable', 'string', 'max:1500'],
            'questions.*.media_url' => ['nullable', 'string', 'max:500'],
            'questions.*.answer' => ['nullable'],
            'questions.*.topic' => ['nullable', 'string', 'max:160'],
            'questions.*.difficulty' => ['nullable', 'in:easy,medium,hard'],
            'questions.*.bloom' => ['nullable', 'in:remember,understand,apply,analyze'],
            'questions.*.bank_id' => ['nullable', 'integer'],
            'questions.*.source' => ['nullable', 'string', 'max:20'],
        ]);
    }
}
