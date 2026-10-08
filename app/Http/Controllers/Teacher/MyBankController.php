<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\SmartQuestionBank;
use App\Support\BankAccess;
use App\Support\Curriculum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * بانکِ سؤالاتِ معلم — مرورِ پایه ← درس ← فصل، ویرایشِ دسته‌بندی و محتوا.
 *
 * قبلاً صفحه‌ی بانک فقط داخلِ «آزمایشگاه هوشمند» بود و وقتی آن ماژول خاموش
 * بود معلم راهی برای دیدنِ سؤال‌هایی که از بازی‌ها و کاربرگ‌ها در بانک
 * نشسته بودند نداشت. این صفحه همیشه در دسترس است.
 */
class MyBankController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        return Inertia::render('Teacher/MyBank', [
            'tree' => BankAccess::facetTree($user),
            'classes' => Curriculum::teacherClasses($user),
            'total' => BankAccess::visibleQuery($user)->count(),
            'mine' => BankAccess::visibleQuery($user)->where('teacher_id', $user->id)->count(),
            'ai' => BankAccess::visibleQuery($user)->where('source', 'ai')->count(),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $user = $request->user();
        $f = $request->only('grade', 'subject', 'chapter_id', 'chapter', 'uncategorized', 'topic', 'difficulty', 'source', 'search');
        $f['types'] = (array) ($request->types ?: []);
        $q = BankAccess::search($user, $f)
            ->when($request->boolean('mine'), fn ($x) => $x->where('teacher_id', $user->id))
            ->with('teacher:id,name');
        $total = (clone $q)->count();
        $rows = $q->latest('id')->offset((int) $request->offset)->limit(60)->get();
        return response()->json([
            'total' => $total,
            'questions' => $rows->map(fn ($b) => BankAccess::row($b, $user))->values(),
        ]);
    }

    public function update(Request $request, SmartQuestionBank $question): RedirectResponse
    {
        $user = $request->user();
        abort_unless(BankAccess::canEdit($user, $question), 403);
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:1000'],
            'type' => ['required', 'in:mc,tf,desc,blank'],
            'choices' => ['nullable', 'array', 'max:6'],
            'answer' => ['nullable', 'string', 'max:1000'],
            'explanation' => ['nullable', 'string', 'max:1000'],
            'hint' => ['nullable', 'string', 'max:300'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'classroom_id' => ['nullable', 'integer'],
            'grade' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:80'],
            'chapter_id' => ['nullable', 'integer'],
            'chapter' => ['nullable', 'string', 'max:160'],
            'topic' => ['nullable', 'string', 'max:160'],
        ]);
        $choices = BankAccess::cleanChoices($data['choices'] ?? []);
        if (in_array($data['type'], ['mc', 'tf'], true)) {
            abort_if(count(array_filter($choices, fn ($c) => $c['correct'])) !== 1, 422, 'دقیقاً یک گزینه باید درست باشد.');
        }
        $ctx = Curriculum::resolve($data, $user);
        $question->update([
            'prompt' => trim($data['prompt']), 'type' => $data['type'], 'choices' => $choices,
            'answer' => $data['answer'] ?? null, 'explanation' => $data['explanation'] ?? null,
            'hint' => $data['hint'] ?? null, 'difficulty' => $data['difficulty'] ?? 'medium',
            'level' => $ctx['level'], 'grade' => $ctx['grade'], 'subject' => $ctx['subject'] ?: null,
            'book' => $ctx['subject'] ?: $question->book,
            'chapter_id' => $ctx['chapter_id'], 'chapter' => $ctx['chapter'], 'topic' => $ctx['topic'],
            'fingerprint' => Curriculum::fingerprint($data['prompt']),
            'version' => $question->version + 1,
        ]);
        return back()->with('flash', 'سؤال به‌روزرسانی شد ✅');
    }

    /** انتقالِ گروهیِ چند سؤال به یک پایه/درس/فصل. */
    public function move(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:300'], 'ids.*' => ['integer'],
            'grade' => ['nullable', 'string', 'max:40'], 'subject' => ['nullable', 'string', 'max:80'],
            'chapter_id' => ['nullable', 'integer'], 'topic' => ['nullable', 'string', 'max:160'],
        ]);
        $ctx = Curriculum::resolve($data, $user);
        $n = 0;
        foreach (SmartQuestionBank::withoutGlobalScopes()->whereIn('id', $data['ids'])->get() as $q) {
            if (! BankAccess::canEdit($user, $q)) {
                continue;
            }
            $q->update(array_filter([
                'level' => $ctx['level'], 'grade' => $ctx['grade'], 'subject' => $ctx['subject'] ?: null,
                'chapter_id' => $ctx['chapter_id'], 'chapter' => $ctx['chapter'], 'topic' => $ctx['topic'],
            ], fn ($v) => $v !== null && $v !== ''));
            $n++;
        }
        return back()->with('flash', \App\Support\Jalali::fa((string) $n) . ' سؤال منتقل شد ✅');
    }

    public function destroy(Request $request, SmartQuestionBank $question): RedirectResponse
    {
        abort_unless(BankAccess::canEdit($request->user(), $question), 403);
        $question->delete();   // سؤال‌ها در آزمون/بازی کپی‌اند؛ حذف از بانک آن‌ها را خراب نمی‌کند
        return back()->with('flash', 'سؤال از بانک حذف شد');
    }
}
