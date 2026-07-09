<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** ادمین کل: مدیریت طرح‌های اشتراک مدارس. */
class PlanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Plans', [
            'plans' => Plan::orderBy('sort')->withCount('schools')->get()->map(fn ($p) => [
                'id' => $p->id, 'key' => $p->key, 'name' => $p->name, 'description' => $p->description,
                'max_classes' => $p->max_classes, 'max_students_per_class' => $p->max_students_per_class,
                'duration_days' => $p->duration_days, 'price' => $p->price,
                'is_active' => $p->is_active, 'schools' => $p->schools_count,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['key'] = $data['key'] ?: (Str::slug($data['name']) ?: Str::lower(Str::random(6)));

        if (Plan::where('key', $data['key'])->exists()) {
            return back()->withErrors(['key' => 'این کلید طرح قبلاً استفاده شده است.']);
        }

        Plan::create($data + ['sort' => (Plan::max('sort') ?? 0) + 1]);
        return back()->with('flash', "طرح «{$data['name']}» ساخته شد ✅");
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $data = $this->validated($request, $plan->id);
        $plan->update($data);
        return back()->with('flash', "طرح «{$plan->name}» به‌روزرسانی شد ✅");
    }

    public function toggle(Plan $plan): RedirectResponse
    {
        $plan->update(['is_active' => ! $plan->is_active]);
        return back()->with('flash', $plan->is_active ? 'طرح فعال شد.' : 'طرح غیرفعال شد.');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        if ($plan->schools()->exists()) {
            return back()->with('flash', ['type' => 'error', 'message' => 'این طرح به مدارسی اختصاص دارد و حذف نمی‌شود.']);
        }
        $plan->delete();
        return back()->with('flash', 'طرح حذف شد.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'key'  => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9_-]*$/'],
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:200'],
            'max_classes' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'max_students_per_class' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:36500'],
            'price' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
    }
}
