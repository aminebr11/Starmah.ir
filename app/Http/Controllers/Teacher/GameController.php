<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ClassActivity;
use App\Models\Classroom;
use App\Support\Jalali;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * بازی‌ساز معلم — ساخت بازیِ چهارگزینه‌ای/جای‌خالی، بانک بازی‌ها،
 * زمان انتشار و امتیاز (XP). بازی‌ها روی مدل ClassActivity (type=game) ذخیره می‌شوند.
 */
class GameController extends Controller
{
    public function index(Request $request): Response
    {
        $teacher = $request->user();
        $classroom = Classroom::where('teacher_id', $teacher->id)->first();

        $games = ClassActivity::where('teacher_id', $teacher->id)
            ->where('type', 'game')
            ->withCount('awards')
            ->latest()->get()
            ->map(fn ($g) => $this->present($g));

        return Inertia::render('Teacher/Games', [
            'classroom' => $classroom?->only('id', 'name'),
            'games'     => $games->values(),
            'subjects'  => $classroom ? $classroom->subjectNames() : [],
        ]);
    }

    private function present(ClassActivity $g): array
    {
        $meta = $g->meta ?? [];
        $now = now();
        $published = $g->status === 'active'
            && (empty($meta['scheduled_at']) || $now->greaterThanOrEqualTo($meta['scheduled_at']));

        return [
            'id'        => $g->id,
            'title'     => $g->title,
            'mode'      => $meta['mode'] ?? 'quiz',
            'subject'   => $meta['subject'] ?? null,
            'points'    => $g->points,
            'questions' => $meta['questions'] ?? [],
            'count'     => count($meta['questions'] ?? []),
            'status'    => $g->status,
            'live'      => $published,
            'scheduled_at' => $meta['scheduled_at'] ?? null,
            'jscheduled'   => ! empty($meta['scheduled_at']) ? Jalali::format($meta['scheduled_at'], true) : null,
            'plays'     => $g->awards_count,
            'date'      => Jalali::format($g->created_at),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $teacher = $request->user();
        $classroom = Classroom::where('teacher_id', $teacher->id)->firstOrFail();
        $data = $this->validated($request);

        ClassActivity::create([
            'school_id'    => $teacher->school_id,
            'classroom_id' => $classroom->id,
            'teacher_id'   => $teacher->id,
            'type'         => 'game',
            'title'        => $data['title'],
            'description'  => $data['description'] ?? null,
            'points'       => $data['points'],
            'status'       => ($data['publish'] ?? true) ? 'active' : 'closed',
            'meta'         => $this->metaFrom($data),
        ]);

        return back()->with('flash', 'بازی ساخته شد 🎮');
    }

    public function update(Request $request, ClassActivity $classActivity): RedirectResponse
    {
        abort_unless($classActivity->teacher_id === $request->user()->id && $classActivity->type === 'game', 403);
        $data = $this->validated($request);

        $classActivity->update([
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'points'      => $data['points'],
            'status'      => ($data['publish'] ?? true) ? 'active' : 'closed',
            'meta'        => $this->metaFrom($data),
        ]);

        return back()->with('flash', 'بازی به‌روزرسانی شد ✅');
    }

    /** انتشار/توقفِ سریع بازی. */
    public function toggle(Request $request, ClassActivity $classActivity): RedirectResponse
    {
        abort_unless($classActivity->teacher_id === $request->user()->id && $classActivity->type === 'game', 403);
        $classActivity->update(['status' => $classActivity->status === 'active' ? 'closed' : 'active']);
        return back()->with('flash', $classActivity->status === 'active' ? 'بازی منتشر شد ▶️' : 'بازی متوقف شد ⏸️');
    }

    /** کپی از بانک بازی‌ها (بازاستفاده). */
    public function duplicate(Request $request, ClassActivity $classActivity): RedirectResponse
    {
        abort_unless($classActivity->teacher_id === $request->user()->id && $classActivity->type === 'game', 403);
        $copy = $classActivity->replicate();
        $copy->title = $classActivity->title . ' (کپی)';
        $copy->status = 'closed';
        $copy->save();
        return back()->with('flash', 'بازی در بانک کپی شد 📋');
    }

    public function destroy(Request $request, ClassActivity $classActivity): RedirectResponse
    {
        abort_unless($classActivity->teacher_id === $request->user()->id && $classActivity->type === 'game', 403);
        $classActivity->delete();
        return back()->with('flash', 'بازی حذف شد');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title'                 => ['required', 'string', 'max:120'],
            'description'           => ['nullable', 'string', 'max:400'],
            'mode'                  => ['nullable', 'in:quiz,truefalse,memory'],
            'subject'               => ['nullable', 'string', 'max:80'],
            'points'                => ['required', 'integer', 'min:1', 'max:500'],
            'scheduled_at'          => ['nullable', 'date'],
            'publish'               => ['boolean'],
            'questions'             => ['required', 'array', 'min:1', 'max:30'],
            'questions.*.prompt'    => ['required', 'string', 'max:300'],
            'questions.*.choices'   => ['required', 'array', 'min:2', 'max:4'],
            'questions.*.choices.*.value'   => ['required', 'string', 'max:120'],
            'questions.*.choices.*.correct' => ['boolean'],
        ]);
    }

    private function metaFrom(array $data): array
    {
        return [
            'mode'         => $data['mode'] ?? 'quiz',
            'subject'      => $data['subject'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'questions'    => $data['questions'],
        ];
    }
}
