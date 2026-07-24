<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\ParentNote;
use App\Models\User;
use App\Support\Jalali;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ارتباطِ محرمانه‌ی کادرِ مدرسه با والدینِ هر دانش‌آموز.
 * معلم: دانش‌آموزانِ کلاسِ خودش · مدیرِ مدرسه: همه‌ی دانش‌آموزانِ مدرسه.
 * پیام‌ها فقط در «بخشِ والدینِ» پرتالِ دانش‌آموز (پشتِ رمزِ والدین) دیده می‌شوند.
 */
class ParentNoteController extends Controller
{
    /** دانش‌آموزانِ قابلِ‌دسترسِ کاربرِ جاری. */
    private function students(Request $request)
    {
        $u = $request->user();
        if ($u->hasRole(Roles::TEACHER)) {
            return User::whereHas('classrooms', fn ($q) => $q->where('teacher_id', $u->id))
                ->orderBy('name')->get(['id', 'name', 'theme_id']);
        }
        if ($u->hasRole(Roles::SCHOOL_ADMIN) || $u->hasRole(Roles::SUPER_ADMIN)) {
            return User::role(Roles::STUDENT)->where('school_id', $u->school_id)
                ->orderBy('name')->get(['id', 'name', 'theme_id']);
        }
        abort(403);
    }

    public function index(Request $request): Response
    {
        $students = $this->students($request);
        $ids = $students->pluck('id');

        // پاسخ‌های خوانده‌نشده‌ی والدین به تفکیکِ دانش‌آموز
        $unread = ParentNote::whereIn('student_id', $ids)->where('from_parent', true)
            ->whereNull('read_at')->selectRaw('student_id, COUNT(*) as c')
            ->groupBy('student_id')->pluck('c', 'student_id');

        $selectedId = (int) $request->query('student') ?: null;
        $thread = [];
        if ($selectedId && $ids->contains($selectedId)) {
            // بازکردنِ گفت‌وگو → پاسخ‌های والد خوانده‌شده می‌شوند
            ParentNote::where('student_id', $selectedId)->where('from_parent', true)
                ->whereNull('read_at')->update(['read_at' => now()]);

            $thread = ParentNote::where('student_id', $selectedId)->with('sender:id,name')
                ->latest()->limit(60)->get()
                ->map(fn ($n) => [
                    'id' => $n->id, 'from_parent' => $n->from_parent,
                    'sender' => $n->from_parent ? 'والدِ دانش‌آموز' : ($n->sender?->name ?? 'مدرسه'),
                    'mine' => $n->sender_id === $request->user()->id,
                    'title' => $n->title, 'body' => $n->body,
                    'seen' => $n->from_parent ? true : $n->read_at !== null,
                    'date' => Jalali::format($n->created_at, true),
                ])->values();
        } else {
            $selectedId = null;
        }

        return Inertia::render('Shared/FamilyNotes', [
            'students' => $students->map(fn ($s) => [
                'id' => $s->id, 'name' => $s->name, 'unread' => (int) ($unread[$s->id] ?? 0),
            ])->values(),
            'selectedId' => $selectedId,
            'thread' => $thread,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'title' => ['nullable', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:3000'],
        ]);
        $students = $this->students($request);
        abort_unless($students->pluck('id')->contains((int) $data['student_id']), 403);

        ParentNote::create([
            'school_id' => $request->user()->school_id,
            'student_id' => $data['student_id'],
            'sender_id' => $request->user()->id,
            'from_parent' => false,
            'title' => $data['title'] ?? null,
            'body' => $data['body'],
        ]);

        return back()->with('flash', 'پیامِ محرمانه برای والدین ارسال شد ✅');
    }

    public function destroy(Request $request, ParentNote $parentNote): RedirectResponse
    {
        abort_unless($parentNote->sender_id === $request->user()->id, 403);
        $parentNote->delete();

        return back()->with('flash', 'پیام حذف شد');
    }
}
