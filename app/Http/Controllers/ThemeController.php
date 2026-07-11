<?php

namespace App\Http\Controllers;

use App\Models\Theme;
use App\Services\ThemeEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * انتخاب «دنیای علاقه» توسط دانش‌آموز — فقط بار اول (onboarding).
 * پس از انتخاب، تغییر تیم فقط توسط معلم انجام می‌شود.
 */
class ThemeController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        // تیم قبلاً انتخاب شده → تغییر فقط توسط معلم ممکن است
        if ($request->user()->theme_id) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Student/ChooseWorld', [
            'themes' => Theme::where('is_active', true)
                ->where('key', '!=', 'brand')
                ->orderBy('sort')
                ->get()
                ->map(fn ($t) => [
                    'id' => $t->id, 'key' => $t->key, 'name' => $t->name,
                    'emoji' => $t->emoji, 'skin' => $t->skin, 'premium' => $t->is_premium,
                    'subtitle' => $t->word('subtitle'), 'tagline' => $t->word('tagline'),
                    'character' => data_get($t->skin, 'character', $t->emoji),
                ]),
        ]);
    }

    public function update(Request $request, ThemeEngine $engine): RedirectResponse
    {
        // قفل: فقط انتخاب اول آزاد است
        if ($request->user()->theme_id) {
            return redirect()->route('dashboard')
                ->with('flash', 'تغییر تیم فقط توسط معلم انجام می‌شود.');
        }

        $data = $request->validate(['theme_id' => ['required', 'exists:themes,id']]);
        $request->user()->update(['theme_id' => $data['theme_id']]);

        return redirect()->route('dashboard');
    }
}
