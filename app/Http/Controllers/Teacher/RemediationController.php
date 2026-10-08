<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Remediation;
use App\Models\RemediationPlan;
use App\Services\RemediationService;
use App\Support\Jalali;
use App\Support\Objectives;
use App\Support\QuestionChapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * معلم: صفحه‌ی «مرورِ اشتباه‌ها» — زمان‌بندیِ کلاس، رصدِ دانش‌آموزان، فرستادنِ مرور
 * برای یک یا چند دانش‌آموز در یک فصل، و بستنِ یک مرور.
 */
class RemediationController extends Controller
{
    public function index(Request $request, RemediationService $svc): Response
    {
        if (! RemediationService::ready()) {
            \App\Support\AutoMigrate::ensure(true);
            RemediationService::ready(true);
        }
        RemediationService::continueBackfill();
        $rooms = Classroom::where('teacher_id', $request->user()->id)->orderBy('id')->get();
        $room = $rooms->firstWhere('id', (int) $request->query('classroom')) ?? $rooms->first();

        return Inertia::render('Teacher/Review', [
            'classrooms' => $rooms->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
            'classroom' => $room?->only('id', 'name'),
            'plan' => RemediationPlan::forClassroom($room?->id),
            'defaults' => RemediationPlan::DEFAULTS,
            'remediation' => $room ? rescue(fn () => $svc->overview($room), null, true) : null,
            'reason' => RemediationService::ready() ? null : RemediationService::whyNotReady(),
        ]);
    }

    /** زمان‌بندیِ اعلام‌شده‌ی معلم برای کلاس (مرورهای بازِ کلاس هم با آن ادامه می‌دهند). */
    public function plan(Request $request, RemediationService $svc): RedirectResponse
    {
        $data = $request->validate([
            'classroom_id' => ['required', 'integer'],
            'enabled' => ['boolean'],
            'sources' => ['array'], 'sources.*' => ['boolean'],
            'first_delay' => ['required', 'integer', 'min:0', 'max:14'],
            'rounds' => ['required', 'integer', 'min:1', 'max:5'],
            'gaps' => ['array'], 'gaps.*' => ['integer', 'min:1', 'max:30'],
            'retry' => ['required', 'integer', 'min:1', 'max:7'],
            'per_session' => ['required', 'integer', 'min:1', 'max:8'],
            'similar' => ['required', 'integer', 'min:0', 'max:4'],
            'share' => ['required', 'integer', 'min:0', 'max:50'],
            'pass' => ['required', 'integer', 'min:40', 'max:100'],
        ], [
            'share.max' => 'حداکثر ۵۰٪ امتیازِ از دست‌رفته برمی‌گردد تا دانش‌آموزی که از اول درست زده همیشه جلوتر بماند.',
        ]);
        $room = Classroom::where('teacher_id', $request->user()->id)->findOrFail($data['classroom_id']);
        abort_unless(RemediationPlan::ready(), 503, 'جدولِ زمان‌بندی هنوز ساخته نشده؛ از «سلامتِ سیستم» مایگریشن را اجرا کنید.');
        $plan = RemediationPlan::put($room->id, $request->user()->id, $data);
        $n = $svc->applyPlan($room, $plan);

        return back()->with('flash', '✅ زمان‌بندیِ مرورِ «' . $room->name . '» ذخیره شد'
            . ($n ? ' — ' . Jalali::fa((string) $n) . ' مرورِ باز هم با همین زمان‌بندی ادامه می‌دهد' : ''));
    }

    public function store(Request $request, RemediationService $svc): RedirectResponse
    {
        $teacher = $request->user();
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer'],
            'subject' => ['required', 'string', 'max:80'],
            'chapter_id' => ['required', 'integer'],
        ], [
            'student_ids.required' => 'دستِ‌کم یک دانش‌آموز را انتخاب کنید.',
            'subject.required' => 'درس را انتخاب کنید.',
            'chapter_id.required' => 'فصل را انتخاب کنید.',
        ]);
        $rooms = Classroom::where('teacher_id', $teacher->id)->with('students:id')->get();
        $mine = $rooms->flatMap(fn ($c) => $c->students->pluck('id'))->unique();
        $ids = collect($data['student_ids'])->map(fn ($i) => (int) $i)->filter(fn ($i) => $mine->contains($i))->values()->all();
        $label = QuestionChapter::labels([$data['chapter_id']])[(int) $data['chapter_id']] ?? null;
        if (! $ids || ! $label) {
            throw ValidationException::withMessages(['student_ids' => 'دانش‌آموز یا فصلِ انتخاب‌شده معتبر نیست.']);
        }
        $oid = Objectives::idFor(['grade' => $rooms->first()?->grade, 'subject' => $data['subject'], 'chapter_id' => (int) $data['chapter_id']]);
        $bank = RemediationService::bankCount($teacher, $oid);
        if ($bank < 1 && ! RemediationService::aiAvailable()) {
            throw ValidationException::withMessages(['chapter_id' => 'برای «' . $label . '» هنوز سؤالِ چهارگزینه‌ای یا درست/نادرست در بانک نیست؛ اول چند سؤال برای این فصل بسازید (یا هوش مصنوعی را فعال کنید).']);
        }
        $n = $svc->teacherAssign($teacher, $ids, $oid, $label);

        return back()->with('flash', $n
            ? '🔁 مرورِ «' . $label . '» برای ' . Jalali::fa((string) $n) . ' دانش‌آموز فرستاده شد' . ($bank < 3 ? ' (بانکِ این فصل کم‌سؤال است؛ هوش مصنوعی سؤالِ مشابه می‌سازد)' : '')
            : 'برای این دانش‌آموزان مرورِ بازِ همین فصل از قبل وجود دارد.');
    }

    public function destroy(Request $request, Remediation $remediation): RedirectResponse
    {
        $mine = Classroom::where('teacher_id', $request->user()->id)->with('students:id')->get()->flatMap(fn ($c) => $c->students->pluck('id'));
        abort_unless($mine->contains($remediation->student_id), 403);
        // امتیازی که دانش‌آموز تا اینجا جبران کرده سرِ جایش می‌ماند
        $remediation->update(['status' => 'closed', 'due_on' => null]);

        return back()->with('flash', 'این مرور بسته شد');
    }
}
