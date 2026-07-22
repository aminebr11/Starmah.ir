<?php

namespace App\Http\Controllers;

use App\Models\GradeColumn;
use App\Models\XpEntry;
use App\Support\Jalali;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** نمرات کلاسیِ دانش‌آموز — همه‌ی نمره‌ها با ارزیابی، بازخورد و امتیاز. */
class StudentGradesController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Student/Grades', $this->gradesData($request->user()));
    }

    /** داده‌ی خامِ نمرات کلاسی — قابلِ استفاده در صفحه‌ی کارنامه‌ی یکپارچه. */
    public function gradesData(\App\Models\User $user): array
    {
        $cols = GradeColumn::whereHas('grades', fn ($q) => $q->where('student_id', $user->id))
            ->with(['grades' => fn ($q) => $q->where('student_id', $user->id)])
            ->orderByDesc('graded_at')->orderByDesc('id')->get();

        // XP کسب‌شده از نمرات کلاسی
        $gradeIds = $cols->flatMap->grades->pluck('id');
        $xpByGrade = XpEntry::where('source_type', \App\Models\Grade::class)
            ->whereIn('source_id', $gradeIds)->pluck('amount', 'source_id');

        $items = $cols->map(function ($c) use ($xpByGrade) {
            $g = $c->grades->first();
            $type = $c->score_type ?: $c->type;
            return [
                'title' => $c->title, 'lesson' => $c->lesson ?: 'سایر', 'topic' => $c->topic,
                'score_type' => $type,
                'value' => $type === 'numeric' ? ($g->score !== null ? (float) $g->score : null) : $g->text,
                'max' => (float) $c->max,
                'feedback' => $g->feedback,
                'xp' => (int) ($xpByGrade[$g->id] ?? 0),
                'jdate' => $c->graded_at ? Jalali::format($c->graded_at) : null,
            ];
        })->values();

        // گروه‌بندی بر اساس درس
        $bySubject = $items->groupBy('lesson')->map(fn ($g, $lesson) => [
            'lesson' => $lesson,
            'count' => $g->count(),
            'items' => $g->values(),
        ])->values();

        $totalXp = (int) $xpByGrade->sum();

        return [
            'subjects' => $bySubject,
            'stats' => [
                'total' => $items->count(),
                'subjects' => $bySubject->count(),
                'xp' => $totalXp,
            ],
        ];
    }
}
