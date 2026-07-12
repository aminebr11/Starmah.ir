<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EduGame;
use App\Models\GameTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** مدیریت قالب‌های بازی (مکانیک‌ها) — ادمین کل. */
class GameTemplateController extends Controller
{
    public function index(): Response
    {
        $templates = GameTemplate::orderBy('sort')->get()->map(fn ($t) => [
            'id' => $t->id, 'key' => $t->key, 'name' => $t->name, 'icon' => $t->icon,
            'description' => $t->description, 'is_active' => $t->is_active,
            'games' => EduGame::where('template_key', $t->key)->count(),
        ]);

        return Inertia::render('Admin/GameTemplates', ['templates' => $templates]);
    }

    public function toggle(GameTemplate $gameTemplate): RedirectResponse
    {
        $gameTemplate->update(['is_active' => ! $gameTemplate->is_active]);
        return back()->with('flash', $gameTemplate->is_active ? 'قالب فعال شد ✅' : 'قالب غیرفعال شد');
    }

    public function update(Request $request, GameTemplate $gameTemplate): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:300'],
            'icon' => ['nullable', 'string', 'max:16'],
        ]);
        $gameTemplate->update($data);
        return back()->with('flash', 'قالب به‌روزرسانی شد ✅');
    }
}
