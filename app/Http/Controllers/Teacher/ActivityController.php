<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ClassActivity;
use App\Models\Classroom;
use App\Services\GamificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** فعالیت‌های کلاسی + امتیازدهی (بازی/آزمون/تکلیف/پادکست...). */
class ActivityController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $teacher = $request->user();
        $classroom = Classroom::where('teacher_id', $teacher->id)->firstOrFail();

        $data = $request->validate([
            'type'         => ['required', 'in:game,exam,homework,podcast,online_exam,custom'],
            'title'        => ['required', 'string', 'max:120'],
            'description'  => ['nullable', 'string', 'max:500'],
            'points'       => ['required', 'integer', 'min:1', 'max:1000'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        ClassActivity::create([
            'school_id'    => $teacher->school_id,
            'classroom_id' => $classroom->id,
            'teacher_id'   => $teacher->id,
            ...$data,
        ]);

        return back()->with('flash', 'فعالیت ثبت شد ✅');
    }

    /** ویرایشِ یک فعالیتِ اضافه‌شده. */
    public function update(Request $request, ClassActivity $classActivity): RedirectResponse
    {
        abort_unless($classActivity->teacher_id === $request->user()->id, 403);
        $data = $request->validate([
            'type'         => ['required', 'in:game,exam,homework,podcast,online_exam,custom'],
            'title'        => ['required', 'string', 'max:120'],
            'description'  => ['nullable', 'string', 'max:500'],
            'points'       => ['required', 'integer', 'min:1', 'max:1000'],
            'scheduled_at' => ['nullable', 'date'],
        ]);
        $classActivity->update($data);

        return back()->with('flash', 'فعالیت ویرایش شد ✅');
    }

    /** حذفِ یک فعالیت + بازگرداندنِ امتیازهایی که بابتش داده شده. */
    public function destroy(Request $request, ClassActivity $classActivity): RedirectResponse
    {
        abort_unless($classActivity->teacher_id === $request->user()->id, 403);

        $awardIds = \App\Models\ActivityAward::where('class_activity_id', $classActivity->id)->pluck('id');
        if ($awardIds->isNotEmpty()) {
            \App\Models\XpEntry::where('source_type', \App\Models\ActivityAward::class)
                ->whereIn('source_id', $awardIds)->delete();
            \App\Models\ActivityAward::whereIn('id', $awardIds)->delete();
        }
        // نوبت‌هایی که از «مرکزِ امتیاز» با این فعالیت داده شده‌اند
        foreach (\App\Models\PointBatch::where('class_activity_id', $classActivity->id)->pluck('id') as $bid) {
            \App\Models\XpEntry::where('source_type', \App\Models\PointBatch::class)->where('source_id', $bid)->delete();
            \App\Models\TeamPoint::where('batch_id', $bid)->delete();
            \App\Models\PointBatch::whereKey($bid)->delete();
        }
        $classActivity->delete();

        return back()->with('flash', 'فعالیت و امتیازهای مرتبطش حذف شد ✅');
    }

    /** اعطای امتیاز به دانش‌آموزها (لیست id) یا یک تیم یا کل کلاس. */
    public function award(Request $request, ClassActivity $classActivity, GamificationService $game): RedirectResponse
    {
        abort_unless($classActivity->teacher_id === $request->user()->id, 403);

        $data = $request->validate([
            'student_ids'   => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $count = 0;
        foreach (\App\Models\User::whereIn('id', $data['student_ids'])->get() as $student) {
            if ($game->awardActivity($classActivity, $student, $request->user())) {
                $count++;
            }
        }

        return back()->with('flash', "به {$count} دانش‌آموز امتیاز داده شد ✅ (+{$classActivity->points})");
    }
}
