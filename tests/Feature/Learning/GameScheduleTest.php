<?php

namespace Tests\Feature\Learning;

use App\Models\EduGame;
use App\Models\EduGameQuestion;
use App\Models\ObjectiveReview;
use App\Models\PracticeAnswer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** پایانِ بازی موعدِ مرور را به‌روز می‌کند (فقط بارِ اول) و شاهدِ تکراری نمی‌سازد. */
class GameScheduleTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    public function test_first_game_completion_schedules_review_once(): void
    {
        $this->seedRoles();
        [$teacher, , $student] = $this->makeClass($this->makeSchool());
        $bank = $this->makeQuestions($teacher, 'ضرب', 2);
        $game = EduGame::create(['school_id' => $teacher->school_id, 'teacher_id' => $teacher->id, 'template_key' => 'snakes', 'title' => 'بازیِ ضرب', 'subject' => 'ریاضی', 'grade' => 'چهارم', 'status' => 'published']);
        foreach ($bank as $k => $b) {
            EduGameQuestion::create(['edu_game_id' => $game->id, 'type' => 'mc', 'prompt' => $b->prompt, 'choices' => $b->choices, 'points' => 1, 'sort' => $k, 'bank_id' => $b->id]);
        }

        // سؤالِ اول درست (گزینه‌ی ۰)، دوم غلط (گزینه‌ی ۱)
        $this->actingAs($student)->post(route('gameworld.finish', $game), ['answers' => [0 => 0, 1 => 1], 'hints_used' => 0, 'duration_sec' => 30])->assertRedirect();

        $r = ObjectiveReview::withoutGlobalScopes()->where('student_id', $student->id)->first();
        $this->assertNotNull($r);
        $this->assertSame(2, $r->attempts);
        $this->assertSame(1, $r->correct);
        $this->assertSame(1, $r->box, 'یک غلط ← جعبه‌ی ۱');
        $this->assertSame(0, PracticeAnswer::withoutGlobalScopes()->count(), 'بازی شواهدِ خودش را در موتورِ تسلط دارد');

        // تکرارِ بازی موعدِ مرور را دوباره جابه‌جا نمی‌کند
        $this->post(route('gameworld.finish', $game), ['answers' => [0 => 0, 1 => 0], 'hints_used' => 0, 'duration_sec' => 20]);
        $this->assertSame(2, $r->fresh()->attempts);
    }
}
