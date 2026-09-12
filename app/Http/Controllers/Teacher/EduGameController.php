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
    public function index(Request $request): Response
    {
        return Inertia::render('Teacher/GameStudio', $this->payload($request));
    }

    /** تولید سؤالِ بازی با هوش مصنوعی (مشابه آزمون هوشمند). */
    public function aiGenerate(Request $request, \App\Services\SmartExamAiService $ai): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:80'], 'topic' => ['nullable', 'string', 'max:120'],
            'grade' => ['nullable', 'string', 'max:40'], 'count' => ['required', 'integer', 'min:1', 'max:15'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'], 'flavor' => ['nullable', 'string', 'max:60'],
            'sample' => ['nullable', 'boolean'],
        ]);
        $result = $ai->generate([...$data, 'type' => 'mc',
            'school_id' => $request->user()->school_id, 'teacher_id' => $request->user()->id]);
        if (! empty($result['questions'])) {
            $result['questions'] = collect($result['questions'])
                ->filter(fn ($q) => in_array($q['type'] ?? 'mc', ['mc', 'tf']))->values()->all();
        }
        return response()->json($result);
    }

    /** سؤال‌های بانک (قابل‌مشاهده برای معلم) برای استفاده در بازی. */
    public function bankQuestions(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $q = \App\Support\BankAccess::pickerQuery($user, $request->subject, $request->lesson_no, $request->search)
            ->latest()->limit(150)->get()
            ->map(fn ($b) => [
                'id' => $b->id, 'prompt' => $b->prompt, 'choices' => $b->choices ?? [],
                'subject' => $b->subject ?: $b->book, 'lesson_no' => $b->lesson_no, 'difficulty' => $b->difficulty,
            ]);
        return response()->json([
            'questions' => $q,
            'facets' => \App\Support\BankAccess::pickerFacets($user),
        ]);
    }

    private function payload(Request $request, array $extra = []): array
    {
        $teacher = $request->user();
        $classroom = Classroom::where('teacher_id', $teacher->id)->first();

        $games = EduGame::where('teacher_id', $teacher->id)
            ->withCount(['questions', 'attempts'])
            ->latest()->get()
            ->map(fn ($g) => $this->card($g));

        return array_merge([
            'games'     => $games->values(),
            'templates' => GameTemplate::where('is_active', true)->orderBy('sort')->get(['key', 'name', 'icon', 'description', 'config']),
            'themes'    => Theme::where('is_active', true)->where('key', '!=', 'brand')->orderBy('sort')
                ->get(['id', 'name', 'emoji'])->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'emoji' => $t->emoji]),
            'subjects'  => $classroom ? collect($classroom->subjectNames())->map(fn ($s) => is_array($s) ? $s['name'] : $s)->values() : [],
            'grade'     => $classroom?->grade,
            'groups'    => $this->groups($classroom),
            'hasClass'  => (bool) $classroom,
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
        ];
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
                'status' => $eduGame->status, 'rules' => $eduGame->rules ?? [],
                'publish_at' => optional($eduGame->publish_at)->format('Y-m-d H:i'),
                'close_at' => optional($eduGame->close_at)->format('Y-m-d H:i'),
                'target_themes' => $eduGame->targets->pluck('theme_id')->filter()->values(),
                'target_students' => $eduGame->targets->pluck('student_id')->filter()->values(),
                'questions' => $eduGame->questions->map(fn ($q) => [
                    'type' => $q->type, 'prompt' => $q->prompt, 'choices' => $q->choices ?? [],
                    'hint1' => $q->hint1, 'hint2' => $q->hint2, 'explanation' => $q->explanation,
                    'points' => $q->points, 'media_url' => $q->media_path,
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

    /** گزارش تحلیلیِ یک بازی. */
    public function report(Request $request, EduGame $eduGame): Response
    {
        abort_unless($eduGame->teacher_id === $request->user()->id, 403);
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

        return Inertia::render('Teacher/GameReport', [
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
        ]);
    }

    // ---------- کمک‌کننده‌ها ----------

    private function attributes($teacher, ?Classroom $classroom, array $data): array
    {
        return [
            'school_id' => $teacher->school_id,
            'teacher_id' => $teacher->id,
            'template_key' => $data['template_key'],
            'theme_id' => $data['theme_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'subject' => $data['subject'] ?? null,
            'grade' => $data['grade'] ?? $classroom?->grade,
            'difficulty' => $data['difficulty'] ?? 'medium',
            'status' => $data['status'] ?? 'draft',
            'publish_at' => $data['publish_at'] ?? null,
            'close_at' => $data['close_at'] ?? null,
            'rules' => $data['rules'] ?? [],
        ];
    }

    private function syncQuestions(EduGame $game, array $questions): void
    {
        $game->questions()->delete();
        $meta = ['subject' => $game->subject, 'grade' => $game->grade, 'source' => 'manual'];
        foreach (array_values($questions) as $i => $q) {
            EduGameQuestion::create([
                'edu_game_id' => $game->id,
                'type' => $q['type'] ?? 'mc',
                'prompt' => $q['prompt'],
                'media_path' => $q['media_url'] ?? null,
                'choices' => $q['choices'] ?? [],
                'hint1' => $q['hint1'] ?? null,
                'hint2' => $q['hint2'] ?? null,
                'explanation' => $q['explanation'] ?? null,
                'points' => $q['points'] ?? 10,
                'sort' => $i,
            ]);
            // ثبتِ خودکارِ سؤالِ بازی در بانک سؤالات (چه آزمون چه بازی، در بانک نگه‌داری می‌شود)
            if ($game->teacher && in_array(($q['type'] ?? 'mc'), ['mc', 'tf'])) {
                \App\Support\BankAccess::autosave($game->teacher, $q, $meta);
            }
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
        ]);
    }
}
