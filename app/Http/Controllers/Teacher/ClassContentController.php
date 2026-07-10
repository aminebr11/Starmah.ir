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

        $items = ClassContent::where('teacher_id', $teacher->id)
            ->latest()
            ->get()
            ->map(fn ($c) => [
                'id'    => $c->id,
                'type'  => $c->type,
                'title' => $c->title,
                'description' => $c->description,
                'url'   => $c->file_path ? Storage::disk('public')->url($c->file_path) : $c->external_url,
                'is_file' => (bool) $c->file_path,
                'due_at'  => $c->due_at ? Jalali::format($c->due_at) : null,
                'date'    => Jalali::format($c->created_at),
            ]);

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
