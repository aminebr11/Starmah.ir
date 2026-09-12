<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ClassContent;
use App\Models\EduGame;
use App\Models\Mission;
use App\Models\SmartExam;
use App\Models\SmartQuestionBank;
use App\Models\Theme;
use App\Models\Worksheet;
use App\Services\SmartExamAiService;
use App\Support\BankAccess;
use App\Support\Jalali;
use App\Support\SmartLab;
use App\Support\WorksheetAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * مأموریت‌های روزانه — سمتِ معلم.
 *
 * ── چه چیزی عوض شد ────────────────────────────────────────────────────
 * پیش از این معلم فقط می‌توانست بگوید «چند سؤال از فلان درس» و سؤال‌ها
 * کورکورانه از بانک برداشته می‌شدند؛ معلم نه می‌دید چند سؤال در دسترس
 * است، نه می‌توانست سؤالِ مشخصی را انتخاب کند، و نه می‌توانست همان‌جا
 * سؤالِ تازه بسازد. منابع هم فقط پادکست/کاربرگ/بازی بود.
 *
 * حالا:
 *   • هفت منبع: بانکِ سؤال، پادکست، ویدیو، جزوه، کاربرگ، بازی و آزمونِ هوشمند
 *   • انتخابگرِ بانک با جست‌وجو، فیلتر، شمارشِ زنده و سنجاق‌کردنِ سؤالِ دقیق
 *   • ساختِ سؤالِ تازه در همین صفحه — که به بانکِ سؤال هم اضافه می‌شود
 *   • نمایشِ زنده‌ی اینکه هر مأموریت واقعاً آماده‌ی اجراست یا نه
 */
class MissionController extends Controller
{
    private const PER_PAGE = 12;

    public function index(Request $request): Response
    {
        $teacher = $request->user();
        $rows = Mission::where('teacher_id', $teacher->id)->latest()->withCount('completions')->get();

        $today = now()->toDateString();
        $items = $rows->map(function (Mission $m) use ($today, $teacher) {
            $ready = $this->readiness($m, $teacher);

            return [
                'id' => $m->id, 'title' => $m->title, 'description' => $m->description,
                'type' => $m->type ?? 'quiz', 'subject' => $m->subject, 'lesson_no' => $m->lesson_no,
                'difficulty' => $m->difficulty, 'question_count' => $m->question_count,
                'question_ids' => $m->question_ids ?: [], 'pass_percent' => $m->pass_percent ?? 60,
                'xp_reward' => $m->xp_reward, 'badge_name' => $m->badge_name, 'badge_icon' => $m->badge_icon,
                'classroom_id' => $m->classroom_id, 'theme_id' => $m->theme_id, 'resource_id' => $m->resource_id,
                'resource_title' => $this->resourceTitle($m),
                'is_active' => $m->is_active,
                'completions' => $m->completions_count,
                'today' => $m->completions()->whereDate('play_date', $today)->count(),
                'ready' => $ready['ok'], 'ready_note' => $ready['note'],
                'date' => Jalali::format($m->created_at),
            ];
        });

        return Inertia::render('Teacher/Missions', [
            'missions' => $items->values(),
            'facets' => BankAccess::pickerFacets($teacher),
            'bankTotal' => BankAccess::visibleQuery($teacher)->whereIn('type', ['mc', 'tf'])->count(),
            'classrooms' => Classroom::where('teacher_id', $teacher->id)->get(['id', 'name'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
            'themes' => Theme::where('is_active', true)->where('key', '!=', 'brand')
                ->orderBy('sort')->get(['id', 'name', 'emoji'])
                ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'emoji' => $t->emoji])->values(),
            'resources' => $this->resources($teacher),
            'smartLab' => SmartLab::enabledFor($teacher),
            'stats' => $this->stats($teacher),
        ]);
    }

    /* ═══════════════════════ منابعِ قابلِ انتخاب ═══════════════════════ */

    /**
     * هر چیزی که معلم از قبل ساخته و می‌تواند مأموریتِ امروز شود.
     * هر منبع «لینکِ ساخت» هم دارد تا اگر چیزی نساخته، همان‌جا برود بسازد.
     */
    private function resources($teacher): array
    {
        $content = fn (string $type) => ClassContent::where('teacher_id', $teacher->id)->where('type', $type)
            ->latest()->limit(60)->get(['id', 'title'])
            ->map(fn ($c) => ['id' => $c->id, 'title' => $c->title])->values();

        return [
            'podcast'   => $content('podcast'),
            'video'     => $content('video'),
            'material'  => $content('material'),
            'worksheet' => WorksheetAccess::visibleQuery($teacher)->where('is_published', true)
                ->latest()->limit(60)->get(['id', 'title'])
                ->map(fn ($w) => ['id' => $w->id, 'title' => $w->title])->values(),
            'game'      => EduGame::where('teacher_id', $teacher->id)->where('status', 'published')
                ->latest()->limit(60)->get(['id', 'title'])
                ->map(fn ($g) => ['id' => $g->id, 'title' => $g->title])->values(),
            'exam'      => SmartExam::where('teacher_id', $teacher->id)->where('status', 'published')
                ->latest()->limit(60)->get(['id', 'title'])
                ->map(fn ($e) => ['id' => $e->id, 'title' => $e->title])->values(),
        ];
    }

    /** آیا این مأموریت واقعاً قابلِ اجراست؟ (تا معلم مأموریتِ خالی منتشر نکند) */
    private function readiness(Mission $m, $teacher): array
    {
        $type = $m->type ?? 'quiz';

        if ($type === 'quiz') {
            $n = $this->bankQuery($m, $teacher)->count();
            if ($n === 0) {
                return ['ok' => false, 'note' => 'در بانک سؤالی با این فیلترها نیست — فیلتر را باز کنید یا سؤال بسازید.'];
            }
            if ($n < (int) $m->question_count) {
                return ['ok' => true, 'note' => "فقط {$n} سؤال در دسترس است (کمتر از {$m->question_count} سؤالِ خواسته‌شده)."];
            }

            return ['ok' => true, 'note' => "{$n} سؤال در دسترس است."];
        }

        if ($m->resource_id && ! $this->resourceTitle($m)) {
            return ['ok' => false, 'note' => 'موردی که انتخاب شده دیگر وجود ندارد یا منتشر نیست.'];
        }

        return ['ok' => true, 'note' => $m->resource_id ? 'به یک موردِ مشخص وصل است.' : 'هر موردی از این نوع پذیرفته می‌شود.'];
    }

    private function resourceTitle(Mission $m): ?string
    {
        if (! $m->resource_id) {
            return null;
        }

        return match ($m->type) {
            'podcast', 'video', 'material' => optional(ClassContent::find($m->resource_id))->title,
            'worksheet' => optional(Worksheet::find($m->resource_id))->title,
            'game' => optional(EduGame::find($m->resource_id))->title,
            'exam' => optional(SmartExam::find($m->resource_id))->title,
            default => null,
        };
    }

    /** آمارِ کوتاهِ بالای صفحه — تا معلم بداند مأموریت‌هایش چقدر گرفته‌اند. */
    private function stats($teacher): array
    {
        $ids = Mission::where('teacher_id', $teacher->id)->pluck('id');
        $today = now()->toDateString();
        $doneToday = \App\Models\MissionCompletion::whereIn('mission_id', $ids)->whereDate('play_date', $today);

        return [
            'active' => Mission::where('teacher_id', $teacher->id)->where('is_active', true)->count(),
            'today_players' => (clone $doneToday)->distinct('student_id')->count('student_id'),
            'today_done' => (clone $doneToday)->count(),
            'week_xp' => (int) \App\Models\MissionCompletion::whereIn('mission_id', $ids)
                ->where('play_date', '>=', now()->subDays(7)->toDateString())->sum('xp_awarded'),
        ];
    }

    /* ═══════════════════════ انتخابگرِ بانکِ سؤال ═══════════════════════ */

    /** همان کوئری‌ای که هنگام اجرا استفاده می‌شود — تا شمارشِ معلم دقیق باشد. */
    private function bankQuery(Mission $m, $teacher)
    {
        if ($ids = $m->question_ids) {
            return BankAccess::visibleQuery($teacher)->whereIn('type', ['mc', 'tf'])->whereIn('id', $ids);
        }

        return BankAccess::visibleQuery($teacher)->whereIn('type', ['mc', 'tf'])
            ->when($m->subject, fn ($q) => $q->where(fn ($w) => $w->where('subject', $m->subject)->orWhere('book', $m->subject)))
            ->when($m->lesson_no, fn ($q) => $q->where('lesson_no', $m->lesson_no))
            ->when($m->difficulty, fn ($q) => $q->where('difficulty', $m->difficulty));
    }

    /**
     * جست‌وجو و مرورِ بانکِ سؤال برای ساختِ مأموریت.
     * برمی‌گرداند: تعدادِ کلِ منطبق + یک صفحه سؤالِ قابلِ سنجاق‌کردن.
     */
    public function bank(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:120'],
            'lesson_no' => ['nullable', 'string', 'max:40'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'ids' => ['nullable', 'array', 'max:60'],
            'ids.*' => ['integer'],
        ]);
        $teacher = $request->user();

        $base = BankAccess::pickerQuery($teacher, $data['subject'] ?? null, $data['lesson_no'] ?? null, $data['q'] ?? null)
            ->when($data['difficulty'] ?? null, fn ($x, $d) => $x->where('difficulty', $d));

        $total = (clone $base)->count();
        $page = (int) ($data['page'] ?? 1);
        $rows = (clone $base)->orderByDesc('id')
            ->skip(($page - 1) * self::PER_PAGE)->take(self::PER_PAGE)->get();

        // سؤال‌های سنجاق‌شده همیشه برگردانده می‌شوند، حتی اگر بیرونِ فیلتر باشند
        $pinned = collect();
        if (! empty($data['ids'])) {
            $pinned = BankAccess::visibleQuery($teacher)->whereIn('id', $data['ids'])->get();
        }

        $shape = fn (SmartQuestionBank $q) => [
            'id' => $q->id, 'prompt' => $q->prompt, 'type' => $q->type,
            'subject' => $q->subject ?: $q->book, 'lesson_no' => $q->lesson_no,
            'difficulty' => $q->difficulty, 'source' => $q->source,
            'choices' => collect($q->choices ?? [])->map(fn ($c) => [
                'value' => $c['value'] ?? '', 'correct' => (bool) ($c['correct'] ?? false),
            ])->values(),
        ];

        return response()->json([
            'ok' => true,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / self::PER_PAGE)),
            'items' => $rows->map($shape)->values(),
            'pinned' => $pinned->map($shape)->values(),
        ]);
    }

    /**
     * ساختِ سؤالِ تازه از داخلِ مأموریت‌ساز.
     * سؤال در **بانکِ سؤال** ذخیره می‌شود (نه فقط داخلِ مأموریت)، پس بعداً
     * در آزمون و بازی هم در دسترس است — همان چیزی که از بخش‌های دیگر
     * انتظار می‌رود.
     */
    public function quickQuestion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:1000'],
            'type' => ['nullable', 'in:mc,tf'],
            'choices' => ['required', 'array', 'min:2', 'max:6'],
            'choices.*.value' => ['required', 'string', 'max:400'],
            'choices.*.correct' => ['nullable', 'boolean'],
            'explanation' => ['nullable', 'string', 'max:600'],
            'subject' => ['nullable', 'string', 'max:120'],
            'lesson_no' => ['nullable', 'string', 'max:40'],
            'grade' => ['nullable', 'string', 'max:60'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
        ]);
        $teacher = $request->user();

        $choices = collect($data['choices'])->map(fn ($c) => [
            'value' => trim($c['value']), 'correct' => (bool) ($c['correct'] ?? false),
        ])->values();
        if (! $choices->contains(fn ($c) => $c['correct'])) {
            return response()->json(['ok' => false, 'message' => 'یکی از گزینه‌ها باید به‌عنوانِ پاسخِ درست انتخاب شود.'], 422);
        }

        $prompt = trim($data['prompt']);
        $existing = SmartQuestionBank::withoutGlobalScopes()
            ->where('teacher_id', $teacher->id)->where('prompt', $prompt)->first();
        if ($existing) {
            return response()->json([
                'ok' => true, 'duplicate' => true, 'id' => $existing->id,
                'message' => 'این سؤال از قبل در بانک بود؛ همان سنجاق شد.',
            ]);
        }

        $q = SmartQuestionBank::create([
            'school_id' => $teacher->school_id, 'teacher_id' => $teacher->id,
            'scope' => 'school', 'type' => $data['type'] ?? 'mc', 'prompt' => $prompt,
            'choices' => $choices->all(), 'answer' => null,
            'explanation' => $data['explanation'] ?? null,
            'subject' => $data['subject'] ?? null, 'book' => $data['subject'] ?? null,
            'lesson_no' => $data['lesson_no'] ?? null, 'grade' => $data['grade'] ?? null,
            'difficulty' => $data['difficulty'] ?? 'medium',
            'source' => 'mission',
        ]);

        return response()->json([
            'ok' => true, 'duplicate' => false, 'id' => $q->id,
            'message' => 'سؤال ساخته و در بانکِ سؤال ذخیره شد ✅',
        ]);
    }

    /** پیشنهادِ سؤال با هوش مصنوعی — خروجی پیش از ذخیره به معلم نشان داده می‌شود. */
    public function aiQuestions(Request $request, SmartExamAiService $ai): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:120'],
            'lesson_no' => ['nullable', 'string', 'max:40'],
            'grade' => ['nullable', 'string', 'max:60'],
            'topic' => ['nullable', 'string', 'max:160'],
            'count' => ['nullable', 'integer', 'min:1', 'max:10'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'sample' => ['nullable', 'boolean'],
        ]);
        $user = $request->user();

        return response()->json($ai->generate([
            'subject' => $data['subject'] ?? '', 'topic' => $data['topic'] ?? ($data['subject'] ?? ''),
            'grade' => $data['grade'] ?? '', 'count' => $data['count'] ?? 4,
            'difficulty' => $data['difficulty'] ?? 'medium', 'type' => 'mc',
            'flavor' => 'مأموریتِ روزانه و چالشی',
            'sample' => (bool) ($data['sample'] ?? false),
            'school_id' => $user->school_id, 'teacher_id' => $user->id,
        ]));
    }

    /* ═══════════════════════ CRUD ═══════════════════════ */

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $user = $request->user();

        Mission::create($this->payload($data, $user));

        return back()->with('flash', 'مأموریت ساخته شد ✅');
    }

    public function update(Request $request, Mission $mission): RedirectResponse
    {
        abort_unless($mission->teacher_id === $request->user()->id, 403);
        $mission->update($this->payload($this->validated($request), $request->user()));

        return back()->with('flash', 'مأموریت به‌روزرسانی شد ✅');
    }

    public function toggle(Request $request, Mission $mission): RedirectResponse
    {
        abort_unless($mission->teacher_id === $request->user()->id, 403);
        $mission->update(['is_active' => ! $mission->is_active]);

        return back()->with('flash', $mission->is_active ? 'مأموریت فعال شد.' : 'مأموریت غیرفعال شد.');
    }

    /** کپیِ یک مأموریت — برای ساختِ سریعِ مأموریتِ فردا از روی امروز. */
    public function duplicate(Request $request, Mission $mission): RedirectResponse
    {
        abort_unless($mission->teacher_id === $request->user()->id, 403);
        $copy = $mission->replicate(['created_at', 'updated_at']);
        $copy->title = $mission->title . ' (رونوشت)';
        $copy->is_active = false;
        $copy->save();

        return back()->with('flash', 'رونوشت ساخته شد — ویرایشش کن و فعالش کن ✅');
    }

    public function destroy(Request $request, Mission $mission): RedirectResponse
    {
        abort_unless($mission->teacher_id === $request->user()->id, 403);
        $mission->delete();

        return back()->with('flash', 'مأموریت حذف شد ✅');
    }

    private function payload(array $data, $user): array
    {
        $type = $data['type'] ?? 'quiz';
        $ids = $type === 'quiz' ? array_values(array_unique(array_map('intval', $data['question_ids'] ?? []))) : [];

        return [
            'school_id' => $user->school_id, 'teacher_id' => $user->id,
            'classroom_id' => $data['classroom_id'] ?? null,
            'theme_id' => $data['theme_id'] ?? null,
            'resource_id' => $type === 'quiz' ? null : ($data['resource_id'] ?? null),
            'title' => $data['title'], 'description' => $data['description'] ?? null,
            'type' => $type,
            'subject' => $type === 'quiz' ? ($data['subject'] ?? null) : null,
            'lesson_no' => $type === 'quiz' ? ($data['lesson_no'] ?? null) : null,
            'difficulty' => $type === 'quiz' ? ($data['difficulty'] ?? null) : null,
            'question_ids' => $ids ?: null,
            // اگر سؤالِ مشخص سنجاق شده، تعداد همان است
            'question_count' => $ids ? count($ids) : $data['question_count'],
            'pass_percent' => $data['pass_percent'] ?? 60,
            'xp_reward' => $data['xp_reward'],
            'badge_name' => $data['badge_name'] ?? null, 'badge_icon' => $data['badge_icon'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:400'],
            'type' => ['nullable', 'in:' . implode(',', Mission::TYPES)],
            'resource_id' => ['nullable', 'integer'],
            'theme_id' => ['nullable', 'integer', 'exists:themes,id'],
            'subject' => ['nullable', 'string', 'max:120'],
            'lesson_no' => ['nullable', 'string', 'max:40'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
            'question_ids' => ['nullable', 'array', 'max:30'],
            'question_ids.*' => ['integer'],
            'question_count' => ['required', 'integer', 'min:1', 'max:30'],
            'pass_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'xp_reward' => ['required', 'integer', 'min:0', 'max:500'],
            'badge_name' => ['nullable', 'string', 'max:60'],
            'badge_icon' => ['nullable', 'string', 'max:16'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
