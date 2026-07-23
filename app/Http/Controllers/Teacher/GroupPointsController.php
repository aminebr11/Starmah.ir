<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\TeamPoint;
use App\Services\TeamScoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * مدیریتِ امتیازِ گروه‌ها (تیم‌های تم‌دار) — معلم می‌تواند به کلِ یک تیم امتیاز اضافه/کم کند
 * و دفترِ ریزِ هر تیم را ببیند (امتیاز از کجا آمده: بازی/مأموریت/فعالیت/امتیازِ دستی).
 */
class GroupPointsController extends Controller
{
    public function index(Request $request, TeamScoreService $svc): Response
    {
        $teacher = $request->user();
        $classroom = Classroom::where('teacher_id', $teacher->id)->first();

        $teams = $classroom ? $svc->teams($classroom) : [];
        $selectedId = (int) $request->query('team');
        $selectedTeam = collect($teams)->firstWhere('theme_id', $selectedId);

        // فیلترِ تک‌نفره: فقط ریزِ همان دانش‌آموز (اگر عضوِ همین تیم باشد)
        $studentId = (int) $request->query('student');
        if ($studentId && $selectedTeam && ! collect($selectedTeam['members'])->firstWhere('id', $studentId)) {
            $studentId = 0; // دانش‌آموز عضوِ این تیم نیست → نادیده
        }

        $ledger = ($classroom && $selectedId && $selectedTeam)
            ? $svc->ledger($selectedId, $classroom, 80, $studentId ?: null) : [];

        return Inertia::render('Teacher/GroupPoints', [
            'classroom' => $classroom?->only('id', 'name'),
            'teams' => $teams,
            'selectedId' => $selectedId ?: null,
            'studentId' => $studentId ?: null,
            'members' => $selectedTeam['members'] ?? [],
            'ledger' => $ledger,
        ]);
    }

    /** افزودن/کسرِ امتیاز برای کلِ یک تیم (amount مثبت = افزودن، منفی = کسر). */
    public function adjust(Request $request): RedirectResponse
    {
        $teacher = $request->user();
        $classroom = Classroom::where('teacher_id', $teacher->id)->firstOrFail();
        $data = $request->validate([
            'theme_id' => ['required', 'integer', 'exists:themes,id'],
            'amount'   => ['required', 'integer', 'min:-1000', 'max:1000', 'not_in:0'],
            'reason'   => ['nullable', 'string', 'max:200'],
        ]);

        TeamPoint::create([
            'school_id' => $teacher->school_id,
            'classroom_id' => $classroom->id,
            'theme_id' => $data['theme_id'],
            'amount' => $data['amount'],
            'reason' => $data['reason'] ?? null,
            'awarded_by' => $teacher->id,
        ]);

        return back()->with('flash', $data['amount'] >= 0 ? 'امتیازِ گروهی افزوده شد ✅' : 'امتیازِ گروهی کسر شد ✅');
    }

    /** حذفِ یک ردیفِ امتیازِ دستیِ گروهی. */
    public function destroyEntry(Request $request, TeamPoint $teamPoint): RedirectResponse
    {
        $classroom = Classroom::where('teacher_id', $request->user()->id)->first();
        abort_unless($classroom && $teamPoint->classroom_id === $classroom->id, 403);
        $teamPoint->delete();

        return back()->with('flash', 'ردیفِ امتیازِ گروهی حذف شد');
    }
}
