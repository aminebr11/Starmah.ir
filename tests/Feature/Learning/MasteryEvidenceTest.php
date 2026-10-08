<?php

namespace Tests\Feature\Learning;

use App\Models\Badge;
use App\Models\MissionCompletion;
use App\Services\GamificationService;
use App\Services\LearningService;
use App\Services\MasteryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** پاسخ‌های سؤال‌به‌سؤال شاهدِ موتورِ تسلط‌اند، بدونِ دوبار شمردن؛ و نشانِ ۷ روزه. */
class MasteryEvidenceTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    public function test_levels_use_descriptive_evaluation_labels(): void
    {
        $this->assertSame(['خیلی خوب', 'خوب', 'قابل قبول', 'نیاز به تلاش بیشتر'], array_column(MasteryService::LEVELS, 'label'));
    }

    public function test_per_question_answers_feed_topic_mastery_without_double_counting_the_day(): void
    {
        $this->seedRoles();
        [$teacher, $classroom, $student] = $this->makeClass($this->makeSchool());
        $qs = $this->makeQuestions($teacher, 'کسرهای مساوی', 4);
        $mission = $this->makeMission($teacher, $classroom, $qs);

        $events = array_map(fn ($q) => ['objective_id' => $q->objective_id, 'bank_id' => $q->id, 'correct' => true, 'first_try' => true], $qs);
        app(LearningService::class)->record($student, $events, 'mission', $mission->id);
        MissionCompletion::create(['mission_id' => $mission->id, 'student_id' => $student->id, 'play_date' => now()->toDateString(), 'score' => 4, 'total' => 4]);

        MasteryService::forget($student->id);
        $rep = app(MasteryService::class)->forStudent($student->id);
        $math = collect($rep['subjects'])->firstWhere('name', 'ریاضی');

        $this->assertSame(4, $rep['observations'], 'نتیجه‌ی کلِ روزِ مأموریت نباید دوباره شمرده شود');
        $this->assertSame('کسرهای مساوی', $math['topics'][0]['name']);
        $this->assertNotNull($math['topics'][0]['mastery']);
    }

    public function test_seven_results_in_one_day_is_not_a_seven_day_streak(): void
    {
        $this->seedRoles();
        [$teacher, $classroom, $student] = $this->makeClass($this->makeSchool());
        $game = app(GamificationService::class);

        for ($k = 0; $k < 7; $k++) {
            $m = $this->makeMission($teacher, $classroom, [], ['title' => "م{$k}"]);
            MissionCompletion::create(['mission_id' => $m->id, 'student_id' => $student->id, 'play_date' => now()->toDateString(), 'score' => 1, 'total' => 1]);
        }
        $this->assertEmpty(collect($game->checkBadges($student))->where('name', '۷ روز پیاپی'));

        $m = $this->makeMission($teacher, $classroom, [], ['title' => 'روزانه']);
        for ($d = 1; $d <= 6; $d++) {
            MissionCompletion::create(['mission_id' => $m->id, 'student_id' => $student->id, 'play_date' => now()->subDays($d)->toDateString(), 'score' => 1, 'total' => 1]);
        }
        $this->assertNotEmpty(collect($game->checkBadges($student))->where('name', '۷ روز پیاپی'));
        $this->assertTrue($student->badges()->where('key', 'streak-7')->exists());
        $this->assertSame(1, Badge::where('key', 'streak-7')->count());
    }
}
