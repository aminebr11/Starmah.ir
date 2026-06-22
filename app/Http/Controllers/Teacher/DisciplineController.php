<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\DisciplineRecord;
use App\Models\User;
use App\Services\GamificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DisciplineController extends Controller
{
    public function store(Request $request, GamificationService $game): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:users,id'],
            'type'       => ['required', 'in:star,warning,note'],
            'points'     => ['nullable', 'integer'],
            'note'       => ['nullable', 'string', 'max:255'],
        ]);

        $student = User::findOrFail($data['student_id']);
        $points  = $data['points'] ?? ($data['type'] === 'star' ? 1 : 0);

        DisciplineRecord::create([
            'school_id'    => $request->user()->school_id,
            'student_id'   => $student->id,
            'classroom_id' => $student->classrooms()->value('classrooms.id'),
            'recorded_by'  => $request->user()->id,
            'type'         => $data['type'],
            'points'       => $points,
            'note'         => $data['note'] ?? null,
        ]);

        // ستاره‌ی انضباطی کمی XP هم می‌دهد
        if ($data['type'] === 'star') {
            $game->awardXp($student, 20, 'ستاره‌ی انضباطی', $request->user());
        }

        return back()->with('flash', 'ثبت شد ✅');
    }
}
