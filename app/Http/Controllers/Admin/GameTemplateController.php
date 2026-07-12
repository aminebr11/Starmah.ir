<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EduGame;
use App\Models\GameTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
            'board_html' => $t->board_html, 'board_css' => $t->board_css,
            'games' => EduGame::where('template_key', $t->key)->count(),
        ]);

        return Inertia::render('Admin/GameTemplates', ['templates' => $templates]);
    }

    /** ساخت محیطِ بازیِ جدید توسط ادمین کل. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:300'],
            'icon' => ['nullable', 'string', 'max:16'],
            'board_html' => ['nullable', 'string', 'max:20000'],
            'board_css' => ['nullable', 'string', 'max:10000'],
        ]);
        $this->assertSafe($data);

        // کلیدِ یکتا از روی نام (لاتین) یا تصادفی
        $base = Str::slug($data['name']) ?: 'env';
        $key = $base;
        $i = 1;
        while (GameTemplate::where('key', $key)->exists()) {
            $key = $base . '-' . (++$i);
        }

        GameTemplate::create([
            'key' => $key, 'name' => $data['name'], 'description' => $data['description'] ?? null,
            'icon' => $data['icon'] ?: '🎲', 'board_html' => $data['board_html'] ?? null,
            'board_css' => $data['board_css'] ?? null, 'is_active' => true,
            'sort' => (int) GameTemplate::max('sort') + 1,
        ]);
        \App\Models\AuditLog::record($request->user(), 'ساخت محیط بازی', "محیطِ «{$data['name']}» ساخته شد");
        return back()->with('flash', 'محیطِ بازیِ جدید ساخته شد ✅');
    }

    public function destroy(Request $request, GameTemplate $gameTemplate): RedirectResponse
    {
        $used = EduGame::where('template_key', $gameTemplate->key)->count();
        if ($used > 0) {
            return back()->withErrors(['delete' => "این محیط در {$used} بازیِ فعال استفاده شده و حذف نمی‌شود — ابتدا آن بازی‌ها را حذف/تغییر دهید."]);
        }
        $gameTemplate->delete();
        return back()->with('flash', 'محیط حذف شد');
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
            'board_html' => ['nullable', 'string', 'max:20000'],
            'board_css' => ['nullable', 'string', 'max:10000'],
        ]);
        $this->assertSafe($data);
        $gameTemplate->update($data);
        return back()->with('flash', 'قالب به‌روزرسانی شد ✅');
    }

    /** تخته‌ی سفارشی فقط HTML/CSS نمایشی است — اسکریپت/رویداد مجاز نیست. */
    private function assertSafe(array $data): void
    {
        foreach (['board_html', 'board_css'] as $f) {
            if (! empty($data[$f]) && preg_match('/<\s*script|on\w+\s*=|javascript:/iu', $data[$f])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    $f => 'کد اسکریپت (script/on*/javascript:) مجاز نیست — فقط HTML و CSS نمایشی.',
                ]);
            }
        }
    }
}
