<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Mission;
use App\Support\BankAccess;
use App\Support\Jalali;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * تنظیماتِ مأموریت‌های روزانه — معلم تعریف می‌کند سؤال‌های مأموریتِ روزانه از کدام
 * درس/شماره‌درسِ بانکِ سؤالش بیایند، چند سؤال، چه امتیازی و کدام نشان.
 */
class MissionController extends Controller
{
    public function index(Request $request): Response
    {
        $teacher = $request->user();
        $rows = Mission::where('teacher_id', $teacher->id)->latest()->withCount('completions')->get();

        $today = now()->toDateString();
        $items = $rows->map(fn (Mission $m) => [
            'id' => $m->id, 'title' => $m->title, 'type' => $m->type ?? 'quiz', 'subject' => $m->subject, 'lesson_no' => $m->lesson_no,
            'difficulty' => $m->difficulty, 'question_count' => $m->question_count, 'xp_reward' => $m->xp_reward,
            'badge_name' => $m->badge_name, 'badge_icon' => $m->badge_icon,
            'classroom_id' => $m->classroom_id, 'is_active' => $m->is_active,
            'completions' => $m->completions_count,
            'today' => $m->completions()->where('play_date', $today)->count(),
            'date' => Jalali::format($m->created_at),
        ]);

        return Inertia::render('Teacher/Missions', [
            'missions' => $items->values(),
            'facets' => BankAccess::pickerFacets($teacher),
            'classrooms' => Classroom::where('teacher_id', $teacher->id)->get(['id', 'name'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $user = $request->user();

        Mission::create([
            'school_id' => $user->school_id, 'teacher_id' => $user->id,
            'classroom_id' => $data['classroom_id'] ?? null,
            'title' => $data['title'], 'type' => $data['type'] ?? 'quiz', 'subject' => $data['subject'] ?? null,
            'lesson_no' => $data['lesson_no'] ?? null, 'difficulty' => $data['difficulty'] ?? null,
            'question_count' => $data['question_count'], 'xp_reward' => $data['xp_reward'],
            'badge_name' => $data['badge_name'] ?? null, 'badge_icon' => $data['badge_icon'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return back()->with('flash', 'مأموریت ساخته شد ✅');
    }

    public function update(Request $request, Mission $mission): RedirectResponse
    {
        abort_unless($mission->teacher_id === $request->user()->id, 403);
        $data = $this->validated($request);
        $mission->update([
            'classroom_id' => $data['classroom_id'] ?? null,
            'title' => $data['title'], 'type' => $data['type'] ?? 'quiz', 'subject' => $data['subject'] ?? null,
            'lesson_no' => $data['lesson_no'] ?? null, 'difficulty' => $data['difficulty'] ?? null,
            'question_count' => $data['question_count'], 'xp_reward' => $data['xp_reward'],
            'badge_name' => $data['badge_name'] ?? null, 'badge_icon' => $data['badge_icon'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
        return back()->with('flash', 'مأموریت به‌روزرسانی شد ✅');
    }

    public function toggle(Request $request, Mission $mission): RedirectResponse
    {
        abort_unless($mission->teacher_id === $request->user()->id, 403);
        $mission->update(['is_active' => ! $mission->is_active]);
        return back()->with('flash', $mission->is_active ? 'مأموریت فعال شد.' : 'مأموریت غیرفعال شد.');
    }

    public function destroy(Request $request, Mission $mission): RedirectResponse
    {
        abort_unless($mission->teacher_id === $request->user()->id, 403);
        $mission->delete();
        return back()->with('flash', 'مأموریت حذف شد ✅');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'type' => ['nullable', 'in:quiz,podcast,worksheet,game'],
            'subject' => ['nullable', 'string', 'max:120'],
            'lesson_no' => ['nullable', 'string', 'max:40'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
            'question_count' => ['required', 'integer', 'min:1', 'max:20'],
            'xp_reward' => ['required', 'integer', 'min:0', 'max:500'],
            'badge_name' => ['nullable', 'string', 'max:60'],
            'badge_icon' => ['nullable', 'string', 'max:16'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
