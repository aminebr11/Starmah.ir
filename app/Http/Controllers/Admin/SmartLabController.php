<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SmartExamAiRequest;
use App\Models\User;
use App\Support\Roles;
use App\Support\SmartLab;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** پنل ادمینِ «آزمایشگاه هوشمند آزمون» — پرچم‌ها، دامنه، معلمان آزمایشی، آمار AI. */
class SmartLabController extends Controller
{
    public function index(): Response
    {
        $teachers = User::role(Roles::TEACHER)->get(['id', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]);

        return Inertia::render('Admin/SmartLab', [
            'flags' => collect(SmartLab::FLAGS)->map(fn ($def, $k) => SmartLab::flag($k)),
            'scope' => SmartLab::scope(),
            'pilotTeachers' => SmartLab::pilotTeacherIds(),
            'teachers' => $teachers,
            'ai' => [
                'provider' => Setting::get('ai_provider', 'anthropic'),
                'requests' => (int) SmartExamAiRequest::count(),
                'produced' => (int) SmartExamAiRequest::sum('produced'),
                'errors' => (int) SmartExamAiRequest::where('ok', false)->count(),
                'lastAt' => optional(SmartExamAiRequest::latest()->first())?->created_at?->diffForHumans(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'flags' => ['array'],
            'flags.*' => ['boolean'],
            'scope' => ['required', 'in:off,pilot,all'],
            'pilotTeachers' => ['array'],
            'pilotTeachers.*' => ['integer'],
        ]);

        foreach (array_keys(SmartLab::FLAGS) as $k) {
            Setting::put($k, ! empty($data['flags'][$k]) ? '1' : '0');
        }
        Setting::put('smart_scope', $data['scope']);
        Setting::put('smart_pilot_teachers', json_encode(array_values($data['pilotTeachers'] ?? [])));

        \App\Models\AuditLog::record($request->user(), 'تنظیم آزمایشگاه هوشمند', 'پرچم‌ها/دامنه به‌روزرسانی شد');
        return back()->with('flash', 'تنظیمات آزمایشگاه هوشمند ذخیره شد ✅');
    }
}
