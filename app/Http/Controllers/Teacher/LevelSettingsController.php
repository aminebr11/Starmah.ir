<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\LevelConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** تنظیم مرحله‌ها (سطوح) توسط معلم — امتیازِ هر مرحله و نام مرحله‌ها. */
class LevelSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $schoolId = $request->user()->school_id;

        return Inertia::render('Teacher/LevelSettings', [
            'config' => LevelConfig::forSchool($schoolId),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $schoolId = $request->user()->school_id;
        $data = $request->validate([
            'xp_per_level' => ['required', 'integer', 'min:10', 'max:5000'],
            'names'        => ['nullable', 'array', 'max:20'],
            'names.*'      => ['nullable', 'string', 'max:40'],
        ]);

        $names = array_values(array_filter($data['names'] ?? [], fn ($n) => filled($n)));
        Setting::put("level_xp:$schoolId", (string) (int) $data['xp_per_level']);
        Setting::put("level_names:$schoolId", json_encode($names, JSON_UNESCAPED_UNICODE));

        \App\Models\AuditLog::record($request->user(), 'تنظیم مرحله‌ها',
            "امتیاز هر مرحله روی {$data['xp_per_level']} تنظیم شد");

        return back()->with('flash', 'تنظیمات مرحله‌ها ذخیره شد ✅');
    }
}
