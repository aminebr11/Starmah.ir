<?php

namespace App\Http\Controllers;

use App\Models\BankShare;
use App\Models\School;
use App\Models\Setting;
use App\Models\SmartQuestionBank;
use App\Services\SmartExamAiService;
use App\Support\BankAccess;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * مدیریتِ حرفه‌ایِ بانک سؤالات — دسته‌بندی بر مبنای مقطع→کلاس→درس.
 * ادمین کل: کلِ بانک + ساختِ سؤالِ سراسری + اشتراک‌گذاریِ دقیق با مدارس.
 * مدیر مدرسه: بانکِ مدرسه‌ی خودش + بانکِ سراسریِ به‌اشتراک‌گذاشته‌شده.
 */
class QuestionBankController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isSuper = $user->hasRole(Roles::SUPER_ADMIN);

        $rows = BankAccess::visibleQuery($user)
            ->with('teacher:id,name')
            ->when($request->level, fn ($x) => $x->where('level', $request->level))
            ->when($request->subject, fn ($x) => $x->where(fn ($w) => $w->where('subject', $request->subject)->orWhere('book', $request->subject)))
            ->when($request->grade, fn ($x) => $x->where('grade', $request->grade))
            ->when($request->difficulty, fn ($x) => $x->where('difficulty', $request->difficulty))
            ->when($request->type, fn ($x) => $x->where('type', $request->type))
            ->when($request->search, fn ($x) => $x->where('prompt', 'like', '%' . $request->search . '%'))
            ->latest()->limit(600)->get();

        $questions = $rows->map(fn ($b) => [
            'id' => $b->id, 'type' => $b->type, 'prompt' => $b->prompt, 'choices' => $b->choices,
            'answer' => $b->answer, 'explanation' => $b->explanation,
            'level' => BankAccess::levelOf($b->level, $b->grade),
            'subject' => $b->subject ?: $b->book, 'lesson_no' => $b->lesson_no, 'grade' => $b->grade, 'topic' => $b->topic,
            'difficulty' => $b->difficulty, 'source' => $b->source, 'used' => $b->used_count,
            'author' => $b->teacher?->name, 'school_id' => $b->school_id, 'scope' => $b->scope,
            'media' => $b->media_path ? Storage::disk('public')->url($b->media_path) : null,
            'can_edit' => BankAccess::canEdit($user, $b),
        ]);

        // گروه‌بندیِ مقطع → کلاس → درس → شماره درس
        $grouped = $questions->groupBy(fn ($q) => $q['level'] ?: 'دسته‌بندی‌نشده')
            ->map(fn ($byLevel, $level) => [
                'level' => $level,
                'count' => $byLevel->count(),
                'grades' => $byLevel->groupBy(fn ($q) => $q['grade'] ?: '—')
                    ->map(fn ($byGrade, $grade) => [
                        'grade' => $grade,
                        'subjects' => $byGrade->groupBy(fn ($q) => $q['subject'] ?: 'عمومی')
                            ->map(fn ($bySubj, $subj) => [
                                'subject' => $subj,
                                'lessons' => $bySubj->groupBy(fn ($q) => $q['lesson_no'] ?: '—')
                                    ->map(fn ($items, $ln) => ['lesson_no' => $ln, 'items' => $items->values()])
                                    ->values(),
                            ])->values(),
                    ])->values(),
            ])->values();

        return Inertia::render('Admin/QuestionBank', [
            'isSuper' => $isSuper,
            'grouped' => $grouped,
            'flat' => $questions->values(),
            'curriculum' => BankAccess::curriculumTree(),
            'filters' => $request->only('level', 'subject', 'grade', 'difficulty', 'type', 'search'),
            'stats' => [
                'total' => BankAccess::visibleQuery($user)->count(),
                'ai' => (clone BankAccess::visibleQuery($user))->where('source', 'ai')->count(),
            ],
            'share' => $isSuper ? [
                'grants' => BankShare::with('school:id,name')->latest()->get()->map(fn ($g) => [
                    'id' => $g->id, 'school_id' => $g->school_id, 'school' => $g->school?->name,
                    'level' => $g->level, 'grade' => $g->grade, 'subject' => $g->subject,
                ]),
                'allSchools' => School::orderBy('name')->get(['id', 'name', 'level'])
                    ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'level' => $s->level]),
            ] : null,
            'aiOn' => (bool) (Setting::get('anthropic_key') || Setting::get('openai_key')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'level' => ['nullable', 'string', 'max:60'],
            'grade' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:80'],
            'lesson_no' => ['nullable', 'string', 'max:40'],
            'topic' => ['nullable', 'string', 'max:120'],
            'scope' => ['nullable', 'in:teacher,school,shared,global'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.type' => ['nullable', 'in:mc,tf,desc,blank'],
            'questions.*.prompt' => ['required', 'string'],
            'questions.*.choices' => ['nullable', 'array'],
            'questions.*.topic' => ['nullable', 'string', 'max:120'],
            'questions.*.image' => ['nullable', 'image', 'max:4096'],
        ]);

        $scope = $user->hasRole(Roles::SUPER_ADMIN) ? ($data['scope'] ?? 'global') : 'school';
        foreach ($data['questions'] as $i => $q) {
            $mediaPath = null;
            if ($request->hasFile("questions.$i.image")) {
                $mediaPath = $request->file("questions.$i.image")->store('bank-media', 'public');
            }
            SmartQuestionBank::create([
                'school_id' => $user->school_id, 'teacher_id' => $user->id, 'scope' => $scope,
                'level' => $data['level'] ?? null, 'lesson_no' => $data['lesson_no'] ?? null,
                'type' => $q['type'] ?? 'mc', 'prompt' => $q['prompt'], 'choices' => $q['choices'] ?? [],
                'answer' => $q['answer'] ?? null, 'explanation' => $q['explanation'] ?? null,
                'subject' => $data['subject'] ?? null, 'book' => $data['subject'] ?? null,
                'grade' => $data['grade'] ?? null, 'topic' => $q['topic'] ?? ($data['topic'] ?? null),
                'media_path' => $mediaPath,
                'difficulty' => $q['difficulty'] ?? 'medium', 'source' => $q['source'] ?? 'manual',
            ]);
        }
        return back()->with('flash', count($data['questions']) . ' سؤال به بانک اضافه شد ✅');
    }

    /** حذفِ گروهیِ سؤال‌ها — فقط ادمین کل. */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole(Roles::SUPER_ADMIN), 403);
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);
        $q = SmartQuestionBank::withoutGlobalScopes()->whereIn('id', $data['ids']);
        $paths = (clone $q)->whereNotNull('media_path')->pluck('media_path');
        foreach ($paths as $p) {
            Storage::disk('public')->delete($p);
        }
        $n = $q->delete();
        return back()->with('flash', $n . ' سؤال حذف شد ✅');
    }

    public function update(Request $request, SmartQuestionBank $question): RedirectResponse
    {
        abort_unless(BankAccess::canEdit($request->user(), $question), 403);
        $data = $request->validate([
            'prompt' => ['required', 'string'],
            'choices' => ['nullable', 'array'],
            'explanation' => ['nullable', 'string'],
            'level' => ['nullable', 'string', 'max:60'],
            'subject' => ['nullable', 'string', 'max:80'],
            'grade' => ['nullable', 'string', 'max:40'],
            'topic' => ['nullable', 'string', 'max:120'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
        ]);
        if (array_key_exists('subject', $data)) {
            $data['book'] = $data['subject'];
        }
        $question->update([...$data, 'version' => $question->version + 1]);
        return back()->with('flash', 'سؤال ویرایش شد ✅');
    }

    public function destroy(Request $request, SmartQuestionBank $question): RedirectResponse
    {
        abort_unless(BankAccess::canEdit($request->user(), $question), 403);
        $question->delete();
        return back()->with('flash', 'سؤال حذف شد');
    }

    public function ai(Request $request, SmartExamAiService $ai): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:80'], 'topic' => ['nullable', 'string', 'max:120'],
            'grade' => ['nullable', 'string', 'max:40'], 'count' => ['required', 'integer', 'min:1', 'max:20'],
            'type' => ['nullable', 'in:mc,tf,desc,blank'], 'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'sample' => ['nullable', 'boolean'],
        ]);
        return response()->json($ai->generate([...$data,
            'school_id' => $request->user()->school_id, 'teacher_id' => $request->user()->id]));
    }

    /** افزودنِ یک مجوزِ اشتراک (مقطع/کلاس/درس → مدرسه) — فقط ادمین کل. */
    public function share(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole(Roles::SUPER_ADMIN), 403);
        $data = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'level' => ['nullable', 'string', 'max:60'],
            'grade' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:80'],
        ]);
        BankShare::firstOrCreate([
            'school_id' => $data['school_id'],
            'level' => ($data['level'] ?? null) ?: null,
            'grade' => ($data['grade'] ?? null) ?: null,
            'subject' => ($data['subject'] ?? null) ?: null,
        ]);
        return back()->with('flash', 'دسترسیِ اشتراک اضافه شد ✅');
    }

    /** حذفِ یک مجوزِ اشتراک — فقط ادمین کل. */
    public function unshare(Request $request, BankShare $bankShare): RedirectResponse
    {
        abort_unless($request->user()->hasRole(Roles::SUPER_ADMIN), 403);
        $bankShare->delete();
        return back()->with('flash', 'دسترسیِ اشتراک حذف شد');
    }
}
