<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\School;
use App\Models\SchoolRequest;
use App\Models\Theme;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** پیشخان و تنظیمات پلتفرم (سوپرادمین). */
class PlatformController extends Controller
{
    public function overview(): Response
    {
        return Inertia::render('Admin/Overview', [
            'stats' => [
                'schools'  => School::count(),
                'pending'  => SchoolRequest::where('status', 'pending')->count(),
                'students' => User::role(Roles::STUDENT)->count(),
                'teachers' => User::role(Roles::TEACHER)->count(),
                'classes'  => Classroom::count(),
                'themes'   => Theme::where('is_active', true)->count(),
            ],
            'recent_schools' => School::latest()->limit(5)->get(['id', 'name', 'city', 'status', 'plan']),
            'recent_requests' => SchoolRequest::where('status', 'pending')->latest()->limit(5)->get(),
        ]);
    }

    public function themes(): Response
    {
        return Inertia::render('Admin/Themes', [
            'themes' => Theme::orderBy('sort')->get()->map(fn ($t) => [
                'id' => $t->id, 'key' => $t->key, 'name' => $t->name, 'emoji' => $t->emoji,
                'skin' => $t->skin, 'is_active' => $t->is_active, 'is_premium' => $t->is_premium,
            ]),
        ]);
    }

    public function storeTheme(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:60'],
            'emoji'    => ['required', 'string', 'max:8'],
            'bg1'      => ['required', 'string', 'max:9'],
            'bg2'      => ['required', 'string', 'max:9'],
            'p1'       => ['required', 'string', 'max:9'],
            'p2'       => ['required', 'string', 'max:9'],
            'acc'      => ['required', 'string', 'max:9'],
            'xp_unit'  => ['required', 'string', 'max:20'],
            'league'   => ['required', 'string', 'max:30'],
        ]);

        Theme::create([
            'key'   => Str::slug($data['name']) ?: Str::lower(Str::random(6)),
            'name'  => $data['name'],
            'emoji' => $data['emoji'],
            'sort'  => Theme::max('sort') + 1,
            'skin'  => [
                'bg1' => $data['bg1'], 'bg2' => $data['bg2'],
                'p1' => $data['p1'], 'p2' => $data['p2'],
                'acc' => $data['acc'], 'acc2' => $data['p1'],
                'ring' => $data['p1'], 'mascot' => $data['emoji'], 'hero' => $data['emoji'],
            ],
            'narrative' => [
                'xp_unit' => $data['xp_unit'], 'xp_label' => $data['xp_unit'] . '‌های این فصل',
                'level' => 'مرحله', 'league' => $data['league'], 'rank_title' => 'قهرمان',
                'next_tier' => 'تا مرحله‌ی بعد', 'mission_title' => 'تمرین امروز',
                'play_label' => 'تمرین', 'leaderboard' => 'جدول رقابت', 'streak' => 'زنجیره',
                'reward_title' => 'آفرین! ' . $data['emoji'],
            ],
            'content_pools' => ['team' => ['تیم'], 'unit' => [$data['xp_unit']], 'hero' => ['قهرمان']],
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => "تم «{$data['name']}» ساخته شد ✅"]);
    }

    public function toggleTheme(Theme $theme): RedirectResponse
    {
        if ($theme->key !== 'brand') {
            $theme->update(['is_active' => ! $theme->is_active]);
        }
        return back();
    }

    public function reports(): Response
    {
        return Inertia::render('Admin/Reports', [
            'top_schools' => School::withCount('users')->orderByDesc('users_count')->limit(8)->get(['id', 'name', 'city']),
        ]);
    }

    public function settings(): Response
    {
        return Inertia::render('Admin/Settings');
    }
}
