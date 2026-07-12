<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ClassContent;
use App\Models\XpEntry;
use App\Support\Jalali;
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
        $items = $this->contentQuery($request->user())
            ->whereIn('type', ['material', 'podcast', 'gallery'])
            ->get()->map(fn ($c) => $this->mapItem($c));

        return Inertia::render('Student/ClassContent', ['items' => $items->values()]);
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
