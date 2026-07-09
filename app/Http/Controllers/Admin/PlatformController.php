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

/** پیشخوان و تنظیمات پلتفرم (سوپرادمین). */
class PlatformController extends Controller
{
    public function overview(): Response
    {
        return Inertia::render('Admin/Overview', [
            'stats' => [
                'schools'  => School::count(),
                'active_schools' => School::where('status', 'active')->count(),
                'pending'  => SchoolRequest::where('status', 'pending')->count(),
                'students' => User::role(Roles::STUDENT)->count(),
                'teachers' => User::role(Roles::TEACHER)->count(),
                'classes'  => Classroom::count(),
                'themes'   => Theme::where('is_active', true)->count(),
                'total_xp' => (int) \Illuminate\Support\Facades\DB::table('xp_ledger')->sum('amount'),
            ],
            'recent_schools' => School::latest()->limit(6)->get(['id', 'name', 'city', 'status', 'plan']),
            'recent_requests' => SchoolRequest::where('status', 'pending')->latest()->limit(5)->get(),
        ]);
    }

    public function themes(): Response
    {
        return Inertia::render('Admin/Themes', [
            'themes' => Theme::orderBy('sort')->get()->map(fn ($t) => [
                'id' => $t->id, 'key' => $t->key, 'name' => $t->name, 'emoji' => $t->emoji,
                'skin' => $t->skin, 'is_active' => $t->is_active, 'is_premium' => $t->is_premium,
                'header' => $t->header_image ? '/' . ltrim($t->header_image, '/') : null,
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
            // بدون قاعده‌ی image (که به fileinfo نیاز دارد) — بررسی پسوند به‌صورت دستی
            'header'   => ['nullable', 'file', 'max:8192'],
        ]);

        $header = ($request->hasFile('header') && $this->isImage($request->file('header')))
            ? $this->saveHeader($request->file('header')) : null;

        Theme::create([
            'key'   => Str::slug($data['name']) ?: Str::lower(Str::random(6)),
            'name'  => $data['name'],
            'emoji' => $data['emoji'],
            'header_image' => $header,
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

    /** آپلود/جایگزینی تصویر هدر یک تیم موجود — مقاوم، بدون نیاز به fileinfo. */
    public function uploadHeader(Request $request, Theme $theme): RedirectResponse
    {
        try {
            $file = $request->file('header');
            if (! $file || ! $file->isValid()) {
                return back()->with('flash', ['type' => 'error', 'message' => 'فایلی دریافت نشد. شاید حجم عکس از حد مجاز سرور (upload_max_filesize) بیشتر است.']);
            }
            if (! $this->isImage($file)) {
                return back()->with('flash', ['type' => 'error', 'message' => 'فقط فایل تصویری (jpg, png, webp) مجاز است.']);
            }

            $path = $this->saveHeader($file);
            $theme->update(['header_image' => $path]);
        } catch (\Throwable $e) {
            report($e);
            return back()->with('flash', ['type' => 'error', 'message' => 'خطا در ذخیره‌ی تصویر: ' . $e->getMessage()]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => "تصویر هدر «{$theme->name}» به‌روزرسانی شد ✅"]);
    }

    /** بررسی تصویربودن فقط با پسوند (بدون وابستگی به fileinfo). */
    private function isImage($file): bool
    {
        $ext = strtolower($file->getClientOriginalExtension());
        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    /** ذخیره‌ی تصویر هدر مستقیم در public/team-headers (بدون نیاز به symlink). */
    private function saveHeader($file): string
    {
        $dir = public_path('team-headers');
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $ext = strtolower($file->getClientOriginalExtension()) ?: 'png';
        $name = \Illuminate\Support\Str::random(24) . '.' . $ext;
        $file->move($dir, $name);

        return 'team-headers/' . $name; // در مرورگر: /team-headers/xxx
    }

    public function reports(\App\Services\AnalyticsService $analytics): Response
    {
        return Inertia::render('Admin/Reports', [
            'report' => $analytics->platformReport(),
        ]);
    }

    public function settings(): Response
    {
        // برای امنیت، کلید کامل را نمایش نمی‌دهیم؛ فقط اینکه تنظیم شده یا نه
        $mask = fn ($v) => $v ? '••••••••' . mb_substr($v, -4) : '';
        return Inertia::render('Admin/Settings', [
            'settings' => [
                'ai_provider'    => \App\Models\Setting::get('ai_provider', 'anthropic'),
                'anthropic_set'  => (bool) \App\Models\Setting::get('anthropic_key'),
                'openai_set'     => (bool) \App\Models\Setting::get('openai_key'),
                'anthropic_hint' => $mask(\App\Models\Setting::get('anthropic_key')),
                'openai_hint'    => $mask(\App\Models\Setting::get('openai_key')),
                'anthropic_model'=> \App\Models\Setting::get('anthropic_model', 'claude-haiku-4-5-20251001'),
                'openai_model'   => \App\Models\Setting::get('openai_model', 'gpt-4o-mini'),
            ],
        ]);
    }

    public function storeSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ai_provider'     => ['required', 'in:anthropic,openai'],
            'anthropic_key'   => ['nullable', 'string', 'max:200'],
            'openai_key'      => ['nullable', 'string', 'max:200'],
            'anthropic_model' => ['nullable', 'string', 'max:80'],
            'openai_model'    => ['nullable', 'string', 'max:80'],
        ]);

        \App\Models\Setting::put('ai_provider', $data['ai_provider']);
        \App\Models\Setting::put('anthropic_model', $data['anthropic_model'] ?: 'claude-haiku-4-5-20251001');
        \App\Models\Setting::put('openai_model', $data['openai_model'] ?: 'gpt-4o-mini');
        // کلیدها فقط در صورت وارد شدن مقدار جدید، به‌روزرسانی می‌شوند (خالی = بدون تغییر)
        if (! empty($data['anthropic_key'])) {
            \App\Models\Setting::put('anthropic_key', $data['anthropic_key']);
        }
        if (! empty($data['openai_key'])) {
            \App\Models\Setting::put('openai_key', $data['openai_key']);
        }

        return back()->with('flash', 'تنظیمات ذخیره شد ✅');
    }
}
