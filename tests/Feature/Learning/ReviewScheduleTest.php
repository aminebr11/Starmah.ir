<?php

namespace Tests\Feature\Learning;

use App\Models\ObjectiveReview;
use App\Services\LearningService;
use App\Services\ReviewMissionBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** جعبه‌ی لایتنر و «مرورِ امروز». */
class ReviewScheduleTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    private function ev($q, bool $ok, bool $first = true, bool $hint = false): array
    {
        return ['objective_id' => $q->objective_id, 'bank_id' => $q->id, 'correct' => $ok, 'first_try' => $first, 'hinted' => $hint];
    }

    public function test_leitner_box_moves_once_per_session(): void
    {
        $this->seedRoles();
        [$teacher, , $student] = $this->makeClass($this->makeSchool());
        $qs = $this->makeQuestions($teacher, 'ضرب', 5);
        $svc = app(LearningService::class);

        // ۵ پاسخِ درست در یک جلسه: فقط یک جعبه جلو برود، نه پنج
        $svc->record($student, array_map(fn ($q) => $this->ev($q, true), $qs), 'mission', 1);
        $r = ObjectiveReview::withoutGlobalScopes()->first();
        $this->assertSame(2, $r->box);
        $this->assertSame(now()->startOfDay()->addDays(3)->toDateString(), $r->due_at->toDateString());

        // درست با تلاشِ دوم: همان جعبه
        $svc->record($student, [$this->ev($qs[0], true, false)], 'mission', 1);
        $this->assertSame(2, $r->fresh()->box);

        // یک غلط: برگشت به جعبه‌ی ۱ و مرورِ فردا
        $svc->record($student, [$this->ev($qs[0], true), $this->ev($qs[1], false)], 'mission', 1);
        $this->assertSame(1, $r->fresh()->box);
        $this->assertSame(now()->startOfDay()->addDay()->toDateString(), $r->fresh()->due_at->toDateString());
    }

    public function test_exam_and_game_answers_schedule_review_but_are_not_logged_twice(): void
    {
        $this->seedRoles();
        [$teacher, , $student] = $this->makeClass($this->makeSchool());
        $qs = $this->makeQuestions($teacher, 'ضرب', 2);

        app(LearningService::class)->record($student, [$this->ev($qs[0], true)], 'smart_exam', 9, false);

        $this->assertSame(1, ObjectiveReview::withoutGlobalScopes()->count());
        $this->assertSame(0, \App\Models\PracticeAnswer::withoutGlobalScopes()->count(), 'آزمون شواهدِ خودش را دارد');
    }

    public function test_no_history_means_no_review_card(): void
    {
        $this->seedRoles();
        [$teacher, $classroom, $student] = $this->makeClass($this->makeSchool());
        $this->makeQuestions($teacher, 'کسر', 6);

        $this->actingAs($student)->get(route('missions'))->assertOk()
            ->assertInertia(fn ($p) => $p->where('review.available', false));
        $this->get(route('missions.review.play'))->assertRedirect(route('missions'));
    }

    public function test_review_mixes_objectives_without_back_to_back_repeats(): void
    {
        $this->seedRoles();
        [$teacher, , $student] = $this->makeClass($this->makeSchool());
        $a = $this->makeQuestions($teacher, 'کسر', 6);
        $b = $this->makeQuestions($teacher, 'ضرب', 6);
        $c = $this->makeQuestions($teacher, 'محیط', 6);
        $svc = app(LearningService::class);
        $svc->record($student, [$this->ev($a[0], true), $this->ev($b[0], false), $this->ev($c[0], true)], 'mission', 1);
        // «محیط» موعدِ مرورش رسیده
        ObjectiveReview::withoutGlobalScopes()->where('objective_id', $c[0]->objective_id)->update(['due_at' => now()->subDay(), 'last_seen_at' => now()->subDays(20)]);

        $items = app(ReviewMissionBuilder::class)->build($student);

        $this->assertCount(8, $items);
        $oids = array_column($items, 'objective_id');
        $this->assertContains($c[0]->objective_id, $oids, 'هدفِ سررسید باید در مرور باشد');
        for ($k = 1; $k < count($oids); $k++) {
            $this->assertNotSame($oids[$k - 1], $oids[$k], 'یک هدف نباید پشتِ سرِ هم بیاید');
        }

        $this->actingAs($student)->get(route('missions'))->assertInertia(fn ($p) => $p->where('review.available', true)->where('review.count', 8));
        $this->get(route('missions.review.play'))->assertOk()->assertInertia(fn ($p) => $p->component('Student/MissionPlay')->where('mission.kind', 'review'));
    }

    public function test_review_only_uses_questions_the_class_teacher_can_see(): void
    {
        $this->seedRoles();
        $school = $this->makeSchool();
        [$teacher, , $student] = $this->makeClass($school);
        [$otherTeacher] = $this->makeClass($this->makeSchool('other'));
        $mine = $this->makeQuestions($teacher, 'کسر', 2);
        // سؤالِ همان هدف ولی مالِ مدرسه‌ی دیگر
        $this->makeQuestions($otherTeacher, 'کسر', 6, ['scope' => 'teacher']);
        app(LearningService::class)->record($student, [$this->ev($mine[0], true)], 'mission', 1);

        $items = app(ReviewMissionBuilder::class)->build($student);
        foreach ($items as $it) {
            $this->assertSame($school->id, $it['bank']->school_id);
        }
    }
}
