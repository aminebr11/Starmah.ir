<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Skill;
use App\Services\ThemeEngine;
use App\Support\Jalali;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** داشبورد دانش‌آموز — تم‌دار (بر اساس تیم/گروه) با رقابت گروه‌ها. */
class DashboardController extends Controller
{
    public function __invoke(Request $request, ThemeEngine $engine): Response
    {
        $user = $request->user();
        app(\App\Services\BirthdayService::class)->runForSchool($user->school_id);
        $theme = $engine->for($user);
        $classroom = $user->classrooms()->with('teacher')->first();

        // همه‌ی اعضای کلاس با تیم و امتیازشان
        $members = collect();
        if ($classroom) {
            $members = $classroom->students()->with('theme')->get()->map(fn ($s) => [
                'id' => $s->id, 'name' => $s->name, 'xp' => $s->totalXp(),
                'theme_id' => $s->theme_id,
                'group' => $s->theme?->name, 'emoji' => $s->theme?->emoji,
                'p1' => data_get($s->theme?->skin, 'p1', '#888'),
            ])->sortByDesc('xp')->values();
        }

        // رتبه‌ی من در کلاس
        $idxClass = $members->search(fn ($m) => $m['id'] === $user->id);
        $myRankClass = $idxClass === false ? null : $idxClass + 1;

        // رقابت گروه‌ها: جمع امتیاز هر تیم + نفرات برتر
        $groups = $members->whereNotNull('theme_id')->groupBy('theme_id')->map(function ($g) use ($user) {
            $first = $g->first();
            return [
                'name'  => $first['group'],
                'emoji' => $first['emoji'],
                'color' => $first['p1'],
                'total' => $g->sum('xp'),
                'count' => $g->count(),
                'mine'  => $g->contains(fn ($m) => $m['id'] === $user->id),
                'top'   => $g->take(5)->map(fn ($m) => ['name' => $m['name'], 'xp' => $m['xp'], 'me' => $m['id'] === $user->id])->values(),
            ];
        })->sortByDesc('total')->values();

        // رتبه‌ی من در تیم خودم
        $myGroup = $groups->firstWhere('mine', true);
        $myRankGroup = null;
        if ($myGroup) {
            $i = collect($myGroup['top'])->search(fn ($m) => $m['me']);
            $myRankGroup = $i === false ? null : $i + 1;
        }

        // نمونه سؤال روکش‌خورده برای «مأموریت امروز»
        $sample = null;
        $skill = Skill::with('questions')->whereHas('questions')->inRandomOrder()->first();
        if ($skill && $q = $skill->questions->random()) {
            $sample = ['skill' => $skill->name, ...$engine->renderQuestion($q, $theme)];
        }

        return Inertia::render('Student/Dashboard', [
            'me' => [
                'xp' => $user->totalXp(),
                'badges' => $user->badges()->count(),
                'classroom' => $classroom?->name,
                'teacher' => $classroom?->teacher?->name,
                'rank_class' => $myRankClass,
                'rank_group' => $myRankGroup,
                'class_size' => $members->count(),
                'group' => $myGroup['name'] ?? null,
            ],
            'groups' => $groups,
            'sample' => $sample,
            'notices' => Announcement::forUser($user)->with('sender:id,name')->latest()->limit(4)->get()
                ->map(fn ($a) => [
                    'id' => $a->id, 'title' => $a->title, 'body' => $a->body,
                    'personal' => $a->audience === 'personal',
                    'sender' => $a->sender?->name, 'date' => Jalali::format($a->created_at),
                ]),
        ]);
    }
}
