<?php

namespace App\Http\Controllers;

use App\Models\DisciplineRecord;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** موارد انضباطی دانش‌آموز: جدید (۲۴ ساعت اخیر) + سوابق. */
class StudentDisciplineController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $all = DisciplineRecord::where('student_id', $user->id)->latest()->limit(100)->get();

        $map = fn ($r) => [
            'title' => $r->title ?? $r->note ?? ($r->points >= 0 ? 'تشویق' : 'تذکر'),
            'points' => $r->points,
            'kind' => $r->points >= 0 ? 'positive' : 'negative',
            'note' => $r->note,
            'date' => $r->jalaliDate(),
        ];

        $recent = $all->filter(fn ($r) => $r->created_at->gt(now()->subDay()))->map($map)->values();
        $history = $all->filter(fn ($r) => $r->created_at->lte(now()->subDay()))->map($map)->values();

        return Inertia::render('Student/Discipline', [
            'recent'  => $recent,
            'history' => $history,
            'totals'  => [
                'positive' => $all->where('points', '>', 0)->sum('points'),
                'negative' => $all->where('points', '<', 0)->sum('points'),
            ],
        ]);
    }
}
