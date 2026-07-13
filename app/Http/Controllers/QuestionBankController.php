<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\Setting;
use App\Models\SmartQuestionBank;
use App\Services\SmartExamAiService;
use App\Support\BankAccess;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * مدیریتِ حرفه‌ایِ بانک سؤالات — برای ادمین کل (کلِ بانک) و مدیر مدرسه (بانکِ مدرسه‌ی خودش).
 * ساخت سؤال به‌صورت دستی و با هوش مصنوعی، بر اساس مقطع و درس.
 */
class QuestionBankController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isSuper = $user->hasRole(Roles::SUPER_ADMIN);

        $q = BankAccess::visibleQuery($user)
            ->with('teacher:id,name')
            ->when($request->subject, fn ($x) => $x->where('subject', $request->subject))
            ->when($request->grade, fn ($x) => $x->where('grade', $request->grade))
            ->when($request->difficulty, fn ($x) => $x->where('difficulty', $request->difficulty))
            ->when($request->type, fn ($x) => $x->where('type', $request->type))
            ->when($request->search, fn ($x) => $x->where('prompt', 'like', '%' . $request->search . '%'))
            ->latest()->limit(400)->get();

        return Inertia::render('Admin/QuestionBank', [
            'isSuper' => $isSuper,
            'questions' => $q->map(fn ($b) => [
                'id' => $b->id, 'type' => $b->type, 'prompt' => $b->prompt, 'choices' => $b->choices,
                'answer' => $b->answer, 'explanation' => $b->explanation,
                'subject' => $b->subject, 'grade' => $b->grade, 'topic' => $b->topic, 'difficulty' => $b->difficulty,
                'source' => $b->source, 'used' => $b->used_count, 'author' => $b->teacher?->name,
                'school_id' => $b->school_id, 'scope' => $b->scope,
            ]),
            'filters' => $request->only('subject', 'grade', 'difficulty', 'type', 'search'),
            'stats' => [
                'total' => BankAccess::visibleQuery($user)->count(),
                'ai' => (clone BankAccess::visibleQuery($user))->where('source', 'ai')->count(),
            ],
            // تنظیماتِ اشتراک (فقط ادمین کل)
            'share' => $isSuper ? [
                'scope' => BankAccess::shareScope(),
                'schools' => BankAccess::sharedSchoolIds(),
                'allSchools' => School::orderBy('name')->get(['id', 'name'])->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]),
            ] : null,
            'aiOn' => (bool) (Setting::get('anthropic_key') || Setting::get('openai_key')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:80'],
            'grade' => ['nullable', 'string', 'max:40'],
            'topic' => ['nullable', 'string', 'max:120'],
            'scope' => ['nullable', 'in:teacher,school,shared,global'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.type' => ['nullable', 'in:mc,tf,desc,blank'],
            'questions.*.prompt' => ['required', 'string'],
            'questions.*.choices' => ['nullable', 'array'],
        ]);

        // ادمین کل می‌تواند سؤالِ سراسری بسازد؛ مدیر مدرسه در بانکِ مدرسه‌ی خودش
        $scope = $user->hasRole(Roles::SUPER_ADMIN) ? ($data['scope'] ?? 'global') : 'school';
        foreach ($data['questions'] as $q) {
            SmartQuestionBank::create([
                'school_id' => $user->school_id, 'teacher_id' => $user->id, 'scope' => $scope,
                'type' => $q['type'] ?? 'mc', 'prompt' => $q['prompt'], 'choices' => $q['choices'] ?? [],
                'answer' => $q['answer'] ?? null, 'explanation' => $q['explanation'] ?? null,
                'subject' => $data['subject'] ?? null, 'grade' => $data['grade'] ?? null, 'topic' => $data['topic'] ?? null,
                'difficulty' => $q['difficulty'] ?? 'medium', 'source' => $q['source'] ?? 'manual',
            ]);
        }
        return back()->with('flash', count($data['questions']) . ' سؤال به بانک اضافه شد ✅');
    }

    public function update(Request $request, SmartQuestionBank $question): RedirectResponse
    {
        abort_unless(BankAccess::canEdit($request->user(), $question), 403);
        $data = $request->validate([
            'prompt' => ['required', 'string'],
            'choices' => ['nullable', 'array'],
            'explanation' => ['nullable', 'string'],
            'subject' => ['nullable', 'string', 'max:80'],
            'grade' => ['nullable', 'string', 'max:40'],
            'topic' => ['nullable', 'string', 'max:120'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
        ]);
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

    /** تنظیمِ اشتراک‌گذاریِ بانک — فقط ادمین کل. */
    public function share(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole(Roles::SUPER_ADMIN), 403);
        $data = $request->validate([
            'scope' => ['required', 'in:off,all,schools'],
            'schools' => ['array'], 'schools.*' => ['integer'],
        ]);
        Setting::put('bank_share_scope', $data['scope']);
        Setting::put('bank_share_schools', json_encode(array_values($data['schools'] ?? [])));
        return back()->with('flash', 'تنظیماتِ اشتراکِ بانک ذخیره شد ✅');
    }
}
