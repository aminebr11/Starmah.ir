<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\User;
use App\Services\AiContentService;
use App\Support\Levels;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** پیشخوان و صفحات مدیر مدرسه (فقط مدرسه‌ی خودش). */
class SchoolDashboardController extends Controller
{
    public function overview(Request $request): Response
    {
        $schoolId = $request->user()->school_id;
        $school = $request->user()->school;

        $students = User::role(Roles::STUDENT)->where('school_id', $schoolId)->count();

        $topStudents = User::role(Roles::STUDENT)->where('school_id', $schoolId)->get()
            ->map(fn ($s) => ['name' => $s->name, 'xp' => $s->totalXp()])
            ->sortByDesc('xp')->take(5)->values();

        return Inertia::render('SchoolAdmin/Overview', [
            'school' => $school?->only('name', 'city', 'plan', 'status', 'seats'),
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

    public function students(Request $request): Response
    {
        $schoolId = $request->user()->school_id;

        $students = User::role(Roles::STUDENT)->where('school_id', $schoolId)->get()
            ->map(function ($s) {
                $class = $s->classrooms()->with('teacher:id,name')->first();
                return [
                    'id' => $s->id, 'name' => $s->name, 'phone' => $s->phone,
                    'class' => $class?->name, 'teacher' => $class?->teacher?->name,
                    'xp' => $s->totalXp(),
                ];
            })->sortByDesc('xp')->values();

        return Inertia::render('SchoolAdmin/Students', ['students' => $students]);
    }

    public function announcements(Request $request): Response
    {
        $schoolId = $request->user()->school_id;
        $audienceFa = ['teachers' => 'معلم‌ها', 'students' => 'دانش‌آموزان', 'all' => 'همه'];

        $list = Announcement::where('school_id', $schoolId)->with('sender:id,name')->latest()->get()
            ->map(fn ($a) => [
                'id' => $a->id, 'title' => $a->title, 'body' => $a->body,
                'audience' => $audienceFa[$a->audience] ?? $a->audience,
                'grade' => $a->grade, 'sender' => $a->sender?->name,
                'date' => $a->created_at?->format('Y/m/d'),
            ]);

        return Inertia::render('SchoolAdmin/Announcements', [
            'announcements' => $list,
            'grades' => Levels::grades($request->user()->school?->level),
            'aiReady' => app(AiContentService::class)->isConfigured(),
        ]);
    }

    public function storeAnnouncement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title'    => ['required', 'string', 'max:120'],
            'body'     => ['required', 'string', 'max:3000'],
            'audience' => ['required', 'in:teachers,students,all'],
            'grade'    => ['nullable', 'string', 'max:30'],
        ]);

        Announcement::create([
            'school_id' => $request->user()->school_id,
            'sender_id' => $request->user()->id,
            ...$data,
        ]);

        return back()->with('flash', 'اطلاعیه ارسال شد ✅');
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
            $text = $ai->generate('یک اطلاعیه‌ی مدرسه درباره‌ی این موضوع بنویس: ' . $data['topic']);
            return back()->with('flash', ['type' => 'ai', 'message' => $text]);
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function reports(Request $request, \App\Services\AnalyticsService $analytics): Response
    {
        return Inertia::render('SchoolAdmin/Reports', [
            'report' => $request->user()->school ? $analytics->schoolReport($request->user()->school) : null,
        ]);
    }
}
