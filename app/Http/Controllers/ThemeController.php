<?php

namespace App\Http\Controllers;

use App\Models\Theme;
use App\Services\ThemeEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** انتخاب «دنیای علاقه» توسط دانش‌آموز (onboarding). */
class ThemeController extends Controller
{
    public function index(): Response
    {
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
        $data = $request->validate(['theme_id' => ['required', 'exists:themes,id']]);
        $request->user()->update(['theme_id' => $data['theme_id']]);

        return redirect()->route('dashboard');
    }
}
