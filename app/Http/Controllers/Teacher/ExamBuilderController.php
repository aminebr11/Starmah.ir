<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Services\AiExamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** آزمون‌ساز: ساخت دستی سؤال یا تولید با هوش مصنوعی. */
class ExamBuilderController extends Controller
{
    public function index(Request $request): Response
    {
        $teacher = $request->user();
        $classroom = Classroom::where('teacher_id', $teacher->id)->first();

        $exams = Assignment::where('classroom_id', $classroom?->id)
            ->whereIn('type', ['exam', 'quiz'])->latest()->get()
            ->map(fn ($a) => [
                'id' => $a->id, 'title' => $a->title, 'type' => $a->type,
                'count' => count($a->config['questions'] ?? []) ?: $a->question_count,
                'published' => $a->is_published,
            ]);

        return Inertia::render('Teacher/ExamBuilder', [
            'classroom' => $classroom?->only('id', 'name'),
            'exams'     => $exams,
            'aiEnabled' => (bool) (env('ANTHROPIC_API_KEY') || env('OPENAI_API_KEY')),
        ]);
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

        $data = $request->validate([
            'title'                 => ['required', 'string', 'max:120'],
            'type'                  => ['required', 'in:exam,quiz'],
            'questions'             => ['required', 'array', 'min:1'],
            'questions.*.prompt'    => ['required', 'string'],
            'questions.*.choices'   => ['required', 'array', 'min:2'],
            'publish'               => ['boolean'],
        ]);

        Assignment::create([
            'school_id'      => $teacher->school_id,
            'classroom_id'   => $classroom->id,
            'teacher_id'     => $teacher->id,
            'title'          => $data['title'],
            'type'           => $data['type'],
            'question_count' => count($data['questions']),
            'config'         => ['questions' => $data['questions']],
            'is_published'   => $data['publish'] ?? true,
        ]);

        return redirect()->route('teacher.exams')->with('flash', 'آزمون ساخته و منتشر شد ✅');
    }
}
