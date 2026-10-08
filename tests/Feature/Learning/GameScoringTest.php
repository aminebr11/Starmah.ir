<?php

namespace Tests\Feature\Learning;

use App\Models\EduGame;
use App\Models\EduGameAttempt;
use App\Models\EduGameQuestion;
use App\Models\Remediation;
use App\Models\XpEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** امتیازِ بازی: سؤالِ بی‌پاسخ (بعد از تمام‌شدنِ جان‌ها) هرگز درست حساب نمی‌شود. */
class GameScoringTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    /** بازیِ ۶ سؤالی، هر سؤال ۱۰ امتیاز، پاسخِ درستِ همه «گزینه‌ی اول» (اندیس ۰). */
    private function game(): array
    {
        $this->seedRoles();
        [$teacher, , $student] = $this->makeClass($this->makeSchool());
        $bank = $this->makeQuestions($teacher, 'اعداد', 6);
        $game = EduGame::create(['school_id' => $teacher->school_id, 'teacher_id' => $teacher->id, 'template_key' => 'snakes', 'title' => 'شهرکِ اعداد', 'subject' => 'ریاضی', 'grade' => 'چهارم', 'status' => 'published']);
        foreach ($bank as $k => $b) {
            EduGameQuestion::create(['edu_game_id' => $game->id, 'type' => 'mc', 'prompt' => $b->prompt, 'choices' => $b->choices, 'points' => 10, 'sort' => $k, 'bank_id' => $b->id]);
        }
        $this->assertTrue((bool) $bank[0]->choices[0]['correct'], 'پیش‌فرضِ تست: پاسخِ درست گزینه‌ی ۰ است');

        return [$teacher, $student, $game];
    }

    public function test_out_of_lives_with_no_correct_answer_gives_zero_points(): void
    {
        [, $student, $game] = $this->game();
        // سه پاسخِ غلط (جان‌ها تمام شد) و سه سؤالِ بی‌پاسخ
        $res = $this->actingAs($student)->post(route('gameworld.finish', $game), ['answers' => [0 => 1, 1 => 1, 2 => 1], 'hints_used' => 0, 'duration_sec' => 40]);
        $res->assertRedirect();

        $att = EduGameAttempt::firstOrFail();
        $this->assertSame(0, (int) $att->score);
        $this->assertSame(0, (int) XpEntry::where('student_id', $student->id)->sum('amount'));
        $flash = session('flash');
        $this->assertStringNotContainsString('آفرین', $flash);
        $this->assertStringContainsString('۰ از ۶', $flash);
        // مرور فقط برای ۳ سؤالی که دیده و غلط زده، نه سؤال‌های بی‌پاسخ
        $this->assertSame(3, Remediation::where('student_id', $student->id)->count());
    }

    public function test_null_and_empty_answers_are_not_counted_as_the_first_choice(): void
    {
        [, $student, $game] = $this->game();
        $this->actingAs($student)->post(route('gameworld.finish', $game), ['answers' => [0 => 0, 1 => null, 2 => '', 3 => 0], 'hints_used' => 0])->assertRedirect();
        $this->assertSame(20, (int) EduGameAttempt::firstOrFail()->score);
        $this->assertSame(20, (int) XpEntry::where('student_id', $student->id)->sum('amount'));
    }

    public function test_past_wrongly_scored_games_are_corrected(): void
    {
        [$teacher, $student, $game] = $this->game();
        // نتیجه‌ی ذخیره‌شده با باگِ قبلی: ۳ غلط، ۳ بی‌پاسخ که «درست» ثبت شده بود → +۳۰
        $detail = [['picked' => 1, 'correct' => false], ['picked' => 1, 'correct' => false], ['picked' => 1, 'correct' => false],
            ['picked' => null, 'correct' => true], ['picked' => null, 'correct' => true], ['picked' => null, 'correct' => true]];
        $att = EduGameAttempt::create(['edu_game_id' => $game->id, 'student_id' => $student->id, 'score' => 30, 'max_score' => 60,
            'progress' => ['answers' => $detail], 'status' => 'completed', 'completed_at' => now()]);
        XpEntry::create(['student_id' => $student->id, 'amount' => 30, 'reason' => '🎮 بازی', 'source_type' => EduGameAttempt::class, 'source_id' => $att->id]);
        // یک بازیِ درست‌حساب‌شده نباید تغییر کند
        $other = EduGameAttempt::create(['edu_game_id' => $game->id, 'student_id' => $this->addStudent($teacher->school, $student->classrooms()->first())->id,
            'score' => 10, 'max_score' => 60, 'progress' => ['answers' => [['picked' => 0, 'correct' => true]]], 'status' => 'completed', 'completed_at' => now()]);

        (require database_path('migrations/2026_10_13_010000_fix_game_unanswered_scores.php'))->up();

        $att->refresh();
        $this->assertSame(0, (int) $att->score);
        $this->assertFalse((bool) $att->progress['answers'][3]['correct']);
        $this->assertSame(0, XpEntry::where('source_type', EduGameAttempt::class)->where('source_id', $att->id)->count());
        $this->assertSame(10, (int) $other->fresh()->score);
    }
}
