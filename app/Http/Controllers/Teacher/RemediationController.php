<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Remediation;
use App\Services\RemediationService;
use App\Support\Jalali;
use App\Support\Objectives;
use App\Support\QuestionChapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** معلم: فرستادنِ «مرورِ اشتباه‌ها» برای یک یا چند دانش‌آموز در یک فصل، و بستنِ یک مرور. */
class RemediationController extends Controller
{
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
