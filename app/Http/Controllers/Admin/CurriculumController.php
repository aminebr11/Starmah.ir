<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CurriculumBook;
use App\Support\Levels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** ادمین کل: مدیریت دروس/کتاب‌های هر پایه (سراسری). */
class CurriculumController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Curriculum', [
            'levels' => Levels::MAP,
            'books' => CurriculumBook::orderBy('sort')->get()
                ->map(fn ($b) => [
                    'id' => $b->id, 'level' => $b->level, 'grade' => $b->grade,
                    'name' => $b->name, 'icon' => $b->icon, 'is_active' => $b->is_active,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'level' => ['required', 'string', 'max:30'],
            'grade' => ['required', 'string', 'max:30'],
            'name'  => ['required', 'string', 'max:60'],
            'icon'  => ['nullable', 'string', 'max:16'],
        ]);
        abort_unless(Levels::isValidLevel($data['level']) && in_array($data['grade'], Levels::grades($data['level']), true), 422);

        CurriculumBook::create($data + ['sort' => (CurriculumBook::where('grade', $data['grade'])->max('sort') ?? 0) + 1]);
        return back()->with('flash', "درس «{$data['name']}» اضافه شد ✅");
    }

    public function update(Request $request, CurriculumBook $curriculumBook): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'icon' => ['nullable', 'string', 'max:16'],
        ]);
        $curriculumBook->update($data);
        return back()->with('flash', 'درس به‌روزرسانی شد ✅');
    }

    public function destroy(CurriculumBook $curriculumBook): RedirectResponse
    {
        $curriculumBook->delete();
        return back()->with('flash', 'درس حذف شد.');
    }
}
