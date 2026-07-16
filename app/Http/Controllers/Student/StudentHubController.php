<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ClassContent;
use App\Models\ContentView;
use App\Models\XpEntry;
use App\Services\GamificationService;
use App\Support\Jalali;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/** صفحه‌های کمکیِ دانش‌آموز: محتوای کلاس، تکالیف، فعالیت‌ها و امتیازها. */
class StudentHubController extends Controller
{
    /** شناسه‌ی معلمِ کلاسِ دانش‌آموز (برای فیلترِ محتوا). */
    private function teacherId($user): ?int
    {
        return $user->classrooms()->with('teacher')->first()?->teacher_id;
    }

    private function contentQuery($user)
    {
        $teacherId = $this->teacherId($user);
        return ClassContent::query()
            ->when($teacherId, fn ($q) => $q->where('teacher_id', $teacherId))
            ->latest();
    }

    private function mapItem($c): array
    {
        return [
            'id'    => $c->id,
            'type'  => $c->type,
            'title' => $c->title,
            'description' => $c->description,
            'url'   => $c->file_path ? Storage::disk('public')->url($c->file_path) : $c->external_url,
            'is_file' => (bool) $c->file_path,
            'due_at'  => $c->due_at ? Jalali::format($c->due_at) : null,
            'overdue' => $c->due_at ? now()->greaterThan($c->due_at) : false,
            'date'    => Jalali::format($c->created_at),
        ];
    }

    /** محتوای کلاس (جزوه، پادکست، گالری) — بدون تکلیف. */
    public function content(Request $request): Response
    {
        $user = $request->user();
        $rows = $this->contentQuery($user)
            ->whereIn('type', ['material', 'podcast', 'gallery'])
            ->get();

        // رکوردِ بازدید/گوش‌دادنِ خودِ دانش‌آموز
        $views = ContentView::where('student_id', $user->id)
            ->whereIn('class_content_id', $rows->pluck('id'))
            ->get()->keyBy('class_content_id');

        $items = $rows->map(function ($c) use ($views) {
            $v = $views->get($c->id);
            return $this->mapItem($c) + [
                'viewed'     => (bool) $v,
                'my_seconds' => $v ? (int) $v->seconds : 0,
                'my_xp'      => $v ? (int) $v->xp_awarded : 0,
            ];
        });

        return Inertia::render('Student/ClassContent', ['items' => $items->values()]);
    }

    /**
     * ثبتِ پیشرفتِ گوش‌دادن/دیدنِ محتوا + محاسبه‌ی XP (فقط یک‌بار، متناسب با ثانیه).
     * فرمول: هر ۱۵ ثانیه گوش‌دادن = ۱ XP، سقف ۲۰ XP. عکس/جزوه‌ی صرفاً دیده‌شده = ۱ XP یک‌بار.
     */
    public function contentProgress(Request $request, ClassContent $classContent, GamificationService $game): JsonResponse
    {
        $user = $request->user();

        // فقط محتوای معلمِ کلاسِ خودِ دانش‌آموز
        abort_unless($this->teacherId($user) === $classContent->teacher_id, 403);

        $data = $request->validate([
            'seconds'  => ['nullable', 'integer', 'min:0', 'max:100000'],
            'finished' => ['nullable', 'boolean'],
        ]);
        $seconds = (int) ($data['seconds'] ?? 0);

        $view = ContentView::firstOrNew([
            'class_content_id' => $classContent->id,
            'student_id'       => $user->id,
        ]);
        $view->viewed = true;
        $view->seconds = max((int) ($view->seconds ?? 0), $seconds);

        // XPِ هدف بر اساس نوع محتوا
        if ($classContent->type === 'podcast') {
            $target = min(20, intdiv($view->seconds, 15)); // ۱ XP در هر ۱۵ ثانیه، سقف ۲۰
        } else {
            $target = 1; // عکس/جزوه‌ی دیده‌شده = ۱ XP یک‌بار
        }

        $delta = max(0, $target - (int) ($view->xp_awarded ?? 0));
        if ($delta > 0) {
            $game->award($user, $delta, '🎧 محتوای کلاس — ' . $classContent->title, null, 'content', $classContent->id);
            $view->xp_awarded = $target;
        }
        $view->save();

        return response()->json([
            'ok'      => true,
            'seconds' => $view->seconds,
            'xp'      => (int) $view->xp_awarded,
            'gained'  => $delta,
        ]);
    }

    /** تکالیف. */
    public function homework(Request $request): Response
    {
        $items = $this->contentQuery($request->user())
            ->where('type', 'homework')
            ->get()->map(fn ($c) => $this->mapItem($c))
            // آینده‌دار اول، بعد قدیمی‌ها
            ->sortBy(fn ($i) => $i['overdue'] ? 1 : 0)->values();

        return Inertia::render('Student/Homework', ['items' => $items->values()]);
    }

    /** فعالیت‌ها و امتیازها — دفترکل XP دانش‌آموز. */
    public function activities(Request $request): Response
    {
        $user = $request->user();

        $entries = XpEntry::where('student_id', $user->id)
            ->latest()->limit(80)->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'amount' => (int) $e->amount,
                'reason' => $e->reason,
                'date'   => Jalali::format($e->created_at, true),
                'kind'   => $e->amount >= 0 ? 'plus' : 'minus',
            ]);

        $summary = app(\App\Services\AnalyticsService::class)->studentSummary($user);

        return Inertia::render('Student/Activities', [
            'entries' => $entries->values(),
            'total'   => $user->totalXp(),
            'week'    => $summary['week_points'] ?? 0,
            'byType'  => $summary['by_type'] ?? [],
        ]);
    }
}
