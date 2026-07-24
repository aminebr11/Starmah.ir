<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\User;
use App\Services\AiContentService;
use App\Support\Jalali;
use App\Support\Levels;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** پیشخوان و صفحات مدیر مدرسه (فقط مدرسه‌ی خودش). */
class SchoolDashboardController extends Controller
{
    use \App\Http\Controllers\Concerns\StoresUploads;

    public function overview(Request $request): Response
    {
        $schoolId = $request->user()->school_id;
        $school = $request->user()->school;

        $students = User::role(Roles::STUDENT)->where('school_id', $schoolId)->count();

        $topStudents = User::role(Roles::STUDENT)->where('school_id', $schoolId)->get()
            ->map(fn ($s) => ['name' => $s->name, 'xp' => $s->totalXp()])
            ->sortByDesc('xp')->take(5)->values();

        return Inertia::render('SchoolAdmin/Overview', [
            'school' => $school ? array_merge(
                $school->only('name', 'city', 'plan', 'status', 'seats'),
                ['logo_url' => $school->logo ? \Illuminate\Support\Facades\Storage::url($school->logo) : null]
            ) : null,
            'stats' => [
                'teachers' => User::role(Roles::TEACHER)->where('school_id', $schoolId)->count(),
                'students' => $students,
                'classes'  => Classroom::where('school_id', $schoolId)->count(),
                'seats'    => $school?->seats,
            ],
            'topStudents' => $topStudents,
            'classes' => Classroom::where('school_id', $schoolId)->withCount('students')->with('teacher:id,name')->get()
                ->map(fn ($c) => ['name' => $c->name, 'teacher' => $c->teacher?->name, 'students' => $c->students_count, 'code' => $c->join_code]),
        ]);
    }

    /** آپلود/به‌روزرسانیِ لوگوی مدرسه (نمایش در سایدبارِ همه‌ی نقش‌های همان مدرسه). */
    public function updateBranding(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        abort_unless($school, 403);
        $request->validate(['logo' => ['required', 'file', 'max:2048']]);

        if ($this->extensionAllowed($request->file('logo'), ['jpg', 'jpeg', 'png', 'webp', 'svg'])) {
            if ($school->logo) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($school->logo);
            }
            $school->logo = $this->storeUpload($request->file('logo'), 'school-logos');
            $school->save();

            return back()->with('flash', 'لوگوی مدرسه به‌روزرسانی شد ✅');
        }

        return back()->withErrors(['logo' => 'فرمتِ فایل مجاز نیست (jpg/png/webp/svg).']);
    }

    public function students(Request $request): Response
    {
        $schoolId = $request->user()->school_id;

        $students = User::role(Roles::STUDENT)->where('school_id', $schoolId)->get()
            ->map(function ($s) {
                $class = $s->classrooms()->with('teacher:id,name')->first();
                $settings = $s->settings ?? [];
                return [
                    'id' => $s->id, 'name' => $s->name, 'phone' => $s->phone, 'national_id' => $s->national_id,
                    'birth_date' => $s->birth_date?->toDateString(),
                    'jbirth' => $s->birth_date ? Jalali::format($s->birth_date) : null,
                    'guardian_name' => $settings['guardian']['father_name'] ?? $settings['guardian']['mother_name'] ?? ($settings['guardian_name'] ?? null),
                    'guardian_phone' => $settings['guardian']['phone'] ?? ($settings['guardian_phone'] ?? null),
                    'parent_pin' => $settings['guardian']['pin'] ?? null,
                    'classroom_id' => $class?->id, 'class' => $class?->name, 'teacher' => $class?->teacher?->name,
                    'xp' => $s->totalXp(),
                ];
            })->sortBy('name', SORT_NATURAL)->values();

        $classrooms = Classroom::with('teacher:id,name')->get()
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'grade' => $c->grade,
                'teacher' => $c->teacher?->name, 'teacher_id' => $c->teacher_id])->values();

        $teachers = User::role(Roles::TEACHER)->where('school_id', $schoolId)
            ->get(['id', 'name'])->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values();

        $audit = \App\Models\AuditLog::where('school_id', $schoolId)->latest()->limit(40)->get()
            ->map(fn ($a) => [
                'actor' => $a->actor_name, 'role' => $a->actor_role, 'action' => $a->action,
                'summary' => $a->summary, 'when' => Jalali::format($a->created_at, true),
            ]);

        return Inertia::render('SchoolAdmin/Students', [
            'students' => $students, 'classrooms' => $classrooms, 'teachers' => $teachers,
            'school' => $request->user()->school?->only('name', 'level'), 'audit' => $audit,
        ]);
    }

    public function announcements(Request $request): Response
    {
        $schoolId = $request->user()->school_id;
        $audienceFa = ['teachers' => 'معلم‌ها', 'students' => 'دانش‌آموزان', 'all' => 'همه', 'personal' => 'پیام شخصی'];

        $list = Announcement::where('school_id', $schoolId)->with('sender:id,name', 'recipients:id,name')->latest()->get()
            ->map(fn ($a) => [
                'id' => $a->id, 'title' => $a->title, 'body' => $a->body,
                'audience' => $audienceFa[$a->audience] ?? $a->audience,
                'audience_key' => $a->audience, 'grade' => $a->grade,
                'recipient_ids' => $a->recipients->pluck('id')->all(),
                'sender' => $a->sender?->name,
                'recipients' => $a->audience === 'personal' ? $a->recipients->pluck('name')->all() : [],
                'date' => Jalali::format($a->created_at),
                'created_ts' => $a->created_at?->timestamp,
            ]);

        // فهرست افراد برای پیام شخصی (معلم‌ها و دانش‌آموزان مدرسه)
        $people = User::where('school_id', $schoolId)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', [Roles::TEACHER, Roles::STUDENT]))
            ->orderBy('name')->get()
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'role' => $u->getRoleNames()->first()]);

        return Inertia::render('SchoolAdmin/Announcements', [
            'announcements' => $list,
            'people'  => $people->values(),
            'grades'  => Levels::grades($request->user()->school?->level),
            'aiReady' => app(AiContentService::class)->isConfigured(),
        ]);
    }

    public function storeAnnouncement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title'          => ['required', 'string', 'max:120'],
            'body'           => ['required', 'string', 'max:3000'],
            'audience'       => ['required', 'in:teachers,students,all,personal'],
            'grade'          => ['nullable', 'string', 'max:30'],
            'recipient_ids'  => ['nullable', 'array'],
            'recipient_ids.*'=> ['integer'],
        ]);

        if ($data['audience'] === 'personal' && empty($data['recipient_ids'])) {
            return back()->withErrors(['recipient_ids' => 'برای پیام شخصی حداقل یک نفر را انتخاب کنید.']);
        }

        $announcement = Announcement::create([
            'school_id' => $request->user()->school_id,
            'sender_id' => $request->user()->id,
            'title'     => $data['title'],
            'body'      => $data['body'],
            'audience'  => $data['audience'],
            'grade'     => $data['audience'] === 'personal' ? null : ($data['grade'] ?? null),
        ]);

        if ($data['audience'] === 'personal') {
            // فقط افراد همین مدرسه
            $ids = User::where('school_id', $request->user()->school_id)
                ->whereIn('id', $data['recipient_ids'])->pluck('id')->all();
            $announcement->recipients()->sync($ids);
        }

        return back()->with('flash', 'اطلاعیه ارسال شد ✅');
    }

    public function updateAnnouncement(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_unless($announcement->school_id === $request->user()->school_id, 403);

        $data = $request->validate([
            'title'          => ['required', 'string', 'max:120'],
            'body'           => ['required', 'string', 'max:3000'],
            'audience'       => ['required', 'in:teachers,students,all,personal'],
            'grade'          => ['nullable', 'string', 'max:30'],
            'recipient_ids'  => ['nullable', 'array'],
            'recipient_ids.*'=> ['integer'],
        ]);

        if ($data['audience'] === 'personal' && empty($data['recipient_ids'])) {
            return back()->withErrors(['recipient_ids' => 'برای پیام شخصی حداقل یک نفر را انتخاب کنید.']);
        }

        $announcement->update([
            'title'    => $data['title'],
            'body'     => $data['body'],
            'audience' => $data['audience'],
            'grade'    => $data['audience'] === 'personal' ? null : ($data['grade'] ?? null),
        ]);

        if ($data['audience'] === 'personal') {
            $ids = User::where('school_id', $request->user()->school_id)
                ->whereIn('id', $data['recipient_ids'])->pluck('id')->all();
            $announcement->recipients()->sync($ids);
        } else {
            $announcement->recipients()->sync([]);
        }

        return back()->with('flash', 'اطلاعیه ویرایش شد ✅');
    }

    public function destroyAnnouncement(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_unless($announcement->school_id === $request->user()->school_id, 403);
        $announcement->delete();
        return back()->with('flash', 'اطلاعیه حذف شد.');
    }

    /** تولید متن اطلاعیه با هوش مصنوعی. */
    public function aiAnnouncement(Request $request, AiContentService $ai): RedirectResponse
    {
        $data = $request->validate(['topic' => ['required', 'string', 'max:300']]);
        try {
            $text = $ai->generate('یک اطلاعیه‌ی مدرسه‌ی کوتاه و رسمی درباره‌ی این موضوع بنویس: ' . $data['topic'], $data['topic']);
            return back()->with('flash', ['type' => 'ai', 'message' => $text]);
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function reports(Request $request, \App\Services\AnalyticsService $analytics): Response
    {
        $school = $request->user()->school;
        $studentIds = $school
            ? User::role(Roles::STUDENT)->where('school_id', $school->id)->pluck('id')
            : collect();

        return Inertia::render('SchoolAdmin/Reports', [
            'report' => $school ? $analytics->schoolReport($school) : null,
            'trend'  => $studentIds->isNotEmpty() ? $analytics->dailyXpSeries($studentIds, 28) : [],
        ]);
    }
}
