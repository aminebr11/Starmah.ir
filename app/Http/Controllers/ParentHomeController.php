<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * داشبوردِ والد — «وضعیتِ فرزندِ من»: نمودارهای روند، ترکیبِ امتیاز، تسلط،
 * حضور/انضباط و توصیه‌ی قابل‌فهم. اگر چند فرزند داشته باشد با ?child عوض می‌شود.
 */
class ParentHomeController extends Controller
{
    public function __invoke(Request $request, AnalyticsService $analytics): Response
    {
        $parent = $request->user();
        $children = $parent->children()->with('theme:id,name,emoji')->get(['users.id', 'users.name', 'users.theme_id', 'users.avatar']);

        $selectedId = (int) $request->query('child') ?: $children->first()?->id;
        $child = $children->firstWhere('id', $selectedId);

        return Inertia::render('Parent/Dashboard', [
            'children' => $children->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name,
                'emoji' => $c->theme?->emoji ?? '🎓',
                'avatar' => $c->avatar ? \Illuminate\Support\Facades\Storage::url($c->avatar) : null,
            ])->values(),
            'selectedId' => $child?->id,
            'report' => $child ? $analytics->childReport($child) : null,
            // روندِ هفته‌به‌هفته‌ی امتیاز و رتبه (نمودارِ کارنامه)
            'pointsTrend' => $child ? app(\App\Services\PointsAnalytics::class)->studentTrend($child) : null,
            // «📬 گزارشِ هفتگی» + «۱۰ دقیقه با فرزندم»
            'weekly' => $child && \App\Models\WeeklyReport::ready()
                ? \App\Models\WeeklyReport::where('student_id', $child->id)->where('status', 'sent')->latest('week_start')->limit(4)->get()
                    ->map(fn ($w) => ['id' => $w->id, 'range' => ($w->data['from'] ?? '') . ' تا ' . ($w->data['to'] ?? ''), 'highlights' => $w->data['highlights'] ?? [],
                        'note' => $w->teacher_note, 'activity' => $w->activity])->values()
                : [],
        ]);
    }
}
