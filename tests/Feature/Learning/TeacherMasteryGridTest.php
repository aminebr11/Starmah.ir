<?php

namespace Tests\Feature\Learning;

use App\Services\LearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** نقشه‌ی تسلطِ معلم و فهرستِ «چه کسی کمک لازم دارد». */
class TeacherMasteryGridTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    public function test_grid_shows_only_own_class_and_flags_students_needing_help(): void
    {
        $this->seedRoles();
        $school = $this->makeSchool();
        [$teacher, $classroom, $good] = $this->makeClass($school);
        $weak = $this->addStudent($school, $classroom);
        [$otherTeacher, , $stranger] = $this->makeClass($this->makeSchool('other'));

        $svc = app(LearningService::class);
        foreach (['کسر', 'ضرب'] as $topic) {
            $qs = $this->makeQuestions($teacher, $topic, 4);
            $svc->record($good, array_map(fn ($q) => ['objective_id' => $q->objective_id, 'bank_id' => $q->id, 'correct' => true], $qs), 'mission', 1);
            $svc->record($weak, array_map(fn ($q) => ['objective_id' => $q->objective_id, 'bank_id' => $q->id, 'correct' => false], $qs), 'mission', 1);
            $theirs = $this->makeQuestions($otherTeacher, $topic, 4);
            $svc->record($stranger, array_map(fn ($q) => ['objective_id' => $q->objective_id, 'bank_id' => $q->id, 'correct' => false], $theirs), 'mission', 1);
        }

        $this->actingAs($teacher)->get(route('teacher.reports'))->assertOk()
            ->assertInertia(function ($p) use ($good, $weak, $stranger) {
                $grid = $p->toArray()['props']['masteryGrid'];
                $ids = collect($grid['subjects'][0]['rows'])->pluck('id')->all();
                $this->assertEqualsCanonicalizing([$good->id, $weak->id], $ids);
                $this->assertNotContains($stranger->id, $ids);
                $this->assertSame([$weak->id], collect($grid['needsHelp'])->pluck('id')->all());
                $row = collect($grid['subjects'][0]['rows'])->firstWhere('id', $weak->id);
                $this->assertSame('beginning', $row['cells']['کسر']['key']);
            });
    }
}
