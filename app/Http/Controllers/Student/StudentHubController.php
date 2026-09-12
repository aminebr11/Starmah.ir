<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ClassContent;
use App\Models\ContentView;
use App\Services\ContentProgressService;
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
            // فقط محتوایی که نمایش‌اش روشن است و زمانِ انتشارش رسیده
            ->live()
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
            'duration' => $c->duration_seconds ? (int) $c->duration_seconds : null,
            'xp_value' => app(\App\Services\ContentProgressService::class)->xpFor($c),
        ];
    }

    /** کاربرگ‌های منتشرشده برای کلاسِ دانش‌آموز. */
    private function worksheetsFor($user)
    {
        $classroomIds = $user->classrooms()->pluck('classrooms.id')->all();
        $subs = \App\Models\WorksheetSubmission::where('student_id', $user->id)->pluck('worksheet_id')->all();

        return \App\Models\Worksheet::withoutGlobalScopes()
            ->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('classroom_id')->when($classroomIds, fn ($x) => $x->orWhereIn('classroom_id', $classroomIds)))
            ->where('school_id', $user->school_id)
            ->latest('published_at')->get()
            ->map(fn ($w) => [
                'id' => $w->id, 'title' => $w->title, 'subject' => $w->subject, 'theme' => $w->theme,
                'has_image' => (bool) $w->image_path,
                'submitted' => in_array($w->id, $subs, true),
                'date' => Jalali::format($w->published_at ?? $w->created_at),
            ])->values();
    }

    /**
     * محتوای کلاس — حالا تکالیف و کاربرگ‌ها هم همین‌جا هستند.
     * دانش‌آموز همه‌چیزِ درسی را در یک صفحه‌ی تب‌دار دارد و منو شلوغ نمی‌شود.
     */
    public function content(Request $request): Response
    {
        $user = $request->user();
        $rows = $this->contentQuery($user)
            ->whereIn('type', ['material', 'podcast', 'video', 'gallery'])
            ->get();

        // رکوردِ بازدید/گوش‌دادنِ خودِ دانش‌آموز
        $views = ContentView::where('student_id', $user->id)
            ->whereIn('class_content_id', $rows->pluck('id'))
            ->get()->keyBy('class_content_id');

        $items = $rows->map(function ($c) use ($views) {
            $v = $views->get($c->id);
            $total = $c->duration_seconds
                ? max(1, (int) ceil($c->duration_seconds / \App\Services\ContentProgressService::BUCKET))
                : 0;
            $done = $v && is_array($v->covered) ? count($v->covered) : 0;

            return $this->mapItem($c) + [
                'viewed'      => (bool) $v,
                'my_seconds'  => $v ? (int) $v->verified_seconds : 0,
                'my_position' => $v ? (int) $v->max_position : 0,
                'my_xp'       => $v ? (int) $v->xp_awarded : 0,
                'completed'   => $v && $v->completed_at !== null,
                'percent'     => $total > 0 ? min(100, (int) round($done / $total * 100)) : ($v && $v->completed_at ? 100 : 0),
            ];
        });

        $homework = $this->contentQuery($user)->where('type', 'homework')->get()
            ->map(fn ($c) => $this->mapItem($c))
            ->sortBy(fn ($i) => $i['overdue'] ? 1 : 0)->values();

        return Inertia::render('Student/ClassContent', [
            'items' => $items->values(),
            'homework' => $homework,
            'worksheets' => $this->worksheetsFor($user),
            'tab' => $request->query('tab'),
        ]);
    }

    /**
     * ثبتِ پیشرفتِ پخشِ محتوا و امتیازدهی.
     *
     * منطقِ امتیاز عمداً در ContentProgressService است، نه اینجا: سرور
     * نقشه‌ی پوششِ پخش‌شده را نگه می‌دارد و با ساعتِ خودش سرعتِ پیشرفت را
     * محدود می‌کند. پس نه جلو زدنِ نوارِ پخش کار می‌کند و نه فرستادنِ
     * عددِ ساختگی از مرورگر. امتیاز دقیقاً یک‌بار و در لحظه‌ی تکمیل
     * پرداخت می‌شود.
     *
     * مرورگر فقط دو چیز می‌فرستد: نقطه‌ی جاریِ پخش و (یک‌بار) مدتِ مدیا.
     */
    public function contentProgress(Request $request, ClassContent $classContent, ContentProgressService $progress): JsonResponse
    {
        $user = $request->user();

        // فقط محتوای معلمِ کلاسِ خودِ دانش‌آموز
        abort_unless($this->teacherId($user) === $classContent->teacher_id, 403);

        $data = $request->validate([
            'position' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'duration' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'ended'    => ['nullable', 'boolean'],
        ]);

        // مدتِ مدیا را نخستین‌بار از مرورگر می‌گیریم و سپس ثابت نگه می‌داریم،
        // تا بعداً با فرستادنِ مدتِ کوچک نتوان «تکمیل» را ارزان کرد.
        if (! $classContent->duration_seconds && ! empty($data['duration'])) {
            $classContent->duration_seconds = (int) $data['duration'];
            $classContent->save();
        }

        $result = $progress->report(
            $user,
            $classContent,
            (int) ($data['position'] ?? 0),
            (bool) ($data['ended'] ?? false),
        );

        return response()->json(['ok' => true] + $result);
    }

    /**
     * تکالیف حالا تبی از «محتوای کلاس» است.
     * این مسیر برای لینک‌های قدیمی و اعلان‌های پیشین نگه داشته شده.
     */
    public function homework(Request $request): \Illuminate\Http\RedirectResponse
    {
        return redirect('/class-content?tab=homework');
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
