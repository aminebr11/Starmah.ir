<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ClassContent;
use App\Models\Classroom;
use App\Support\Jalali;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * محتوای کلاس معلم — جزوه/فایل، پادکست، گالری، تکلیف (مطابق وبسایت قبلی).
 */
class ClassContentController extends Controller
{
    public function index(Request $request): Response
    {
        $teacher = $request->user();

        $contents = ClassContent::where('teacher_id', $teacher->id)->latest()->get();

        // آمار بازدید/گوش‌دادن — چه کسانی دیدند/گوش دادند
        $views = \App\Models\ContentView::with('student:id,name')
            ->whereIn('class_content_id', $contents->pluck('id'))
            ->get()->groupBy('class_content_id');

        $items = $contents->map(function ($c) use ($views) {
            $vs = $views->get($c->id) ?? collect();
            return [
                'id'    => $c->id,
                'type'  => $c->type,
                'title' => $c->title,
                'description' => $c->description,
                'classroom_id' => $c->classroom_id,
                'due_at_raw' => $c->due_at ? $c->due_at->format('Y-m-d H:i') : null,
                'external_url' => $c->external_url,
                'url'   => $c->file_path ? Storage::disk('public')->url($c->file_path) : $c->external_url,
                'is_file' => (bool) $c->file_path,
                'due_at'  => $c->due_at ? Jalali::format($c->due_at) : null,
                'date'    => Jalali::format($c->created_at),
                'viewers' => $vs->map(fn ($v) => [
                    'name'    => $v->student?->name ?? '—',
                    'seconds' => (int) $v->seconds,
                    'xp'      => (int) $v->xp_awarded,
                ])->values(),
                'views_count' => $vs->count(),
            ];
        });

        $classrooms = Classroom::where('teacher_id', $teacher->id)
            ->get(['id', 'name'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]);

        return Inertia::render('Teacher/Materials', [
            'items'      => $items->values(),
            'classrooms' => $classrooms->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type'         => 'required|in:material,podcast,gallery,homework',
            'title'        => 'required|string|max:150',
            'description'  => 'nullable|string|max:2000',
            'classroom_id' => 'nullable|integer|exists:classrooms,id',
            'external_url' => 'nullable|url|max:500',
            'file'         => 'nullable|file|max:20480', // حداکثر ۲۰ مگابایت
            'due_at'       => 'nullable|date',
        ]);

        $path = null;
        if ($request->hasFile('file')) {
            $path = $request->file('file')->store("class-content/{$data['type']}", 'public');
        }

        ClassContent::create([
            'teacher_id'   => $request->user()->id,
            'classroom_id' => $data['classroom_id'] ?? null,
            'type'         => $data['type'],
            'title'        => $data['title'],
            'description'  => $data['description'] ?? null,
            'file_path'    => $path,
            'external_url' => $data['external_url'] ?? null,
            'due_at'       => $data['due_at'] ?? null,
        ]);

        return back()->with('flash', 'محتوا با موفقیت اضافه شد ✅');
    }

    public function update(Request $request, ClassContent $classContent): RedirectResponse
    {
        abort_unless($classContent->teacher_id === $request->user()->id, 403);

        $data = $request->validate([
            'title'        => 'required|string|max:150',
            'description'  => 'nullable|string|max:2000',
            'classroom_id' => 'nullable|integer|exists:classrooms,id',
            'external_url' => 'nullable|url|max:500',
            'file'         => 'nullable|file|max:20480',
            'due_at'       => 'nullable|date',
        ]);

        // جایگزینیِ فایل (در صورت آپلود فایلِ جدید)
        if ($request->hasFile('file')) {
            if ($classContent->file_path) {
                Storage::disk('public')->delete($classContent->file_path);
            }
            $classContent->file_path = $request->file('file')->store("class-content/{$classContent->type}", 'public');
        }

        $classContent->fill([
            'title'        => $data['title'],
            'description'  => $data['description'] ?? null,
            'classroom_id' => $data['classroom_id'] ?? $classContent->classroom_id,
            'external_url' => $data['external_url'] ?? $classContent->external_url,
            'due_at'       => $data['due_at'] ?? $classContent->due_at,
        ])->save();

        return back()->with('flash', 'محتوا ویرایش شد ✅');
    }

    public function destroy(Request $request, ClassContent $classContent): RedirectResponse
    {
        abort_unless($classContent->teacher_id === $request->user()->id, 403);

        if ($classContent->file_path) {
            Storage::disk('public')->delete($classContent->file_path);
        }
        $classContent->delete();

        return back()->with('flash', 'محتوا حذف شد ✅');
    }
}
