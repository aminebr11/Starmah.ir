<?php

namespace Tests\Feature\Learning;

use App\Models\MissionCompletion;
use App\Models\PracticeAnswer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** بازخوردِ همان لحظه در مأموریت: دو فرصت، راهنما، نمره‌دهیِ سمتِ سرور. */
class MissionFeedbackTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    private function start(int $n = 2): array
    {
        $this->seedRoles();
        $school = $this->makeSchool();
        [$teacher, $classroom, $student] = $this->makeClass($school);
        $qs = $this->makeQuestions($teacher, 'کسرهای مساوی', $n);
        $mission = $this->makeMission($teacher, $classroom, $qs);

        $page = $this->actingAs($student)->get(route('missions.play', $mission->id))->assertOk()->viewData('page');

        return [$student, $mission, $page['props']['token'], $page['props']['questions']];
    }

    /** پاسخِ درستِ یک سؤالِ نمایش‌داده‌شده (از روی برچسبِ «درست» در متنِ گزینه‌ی تست). */
    private static function right(array $q): string
    {
        return collect($q['choices'])->pluck('value')->first(fn ($v) => str_starts_with($v, 'درست'));
    }

    private static function wrong(array $q, int $k = 0): string
    {
        return collect($q['choices'])->pluck('value')->filter(fn ($v) => str_starts_with($v, 'غلط'))->values()[$k];
    }

    public function test_answer_key_never_reaches_the_browser(): void
    {
        [, , , $questions] = $this->start();

        foreach ($questions as $q) {
            $this->assertArrayNotHasKey('answer', $q);
            foreach ($q['choices'] as $c) {
                $this->assertSame(['value'], array_keys($c), 'گزینه‌ها نباید نشانه‌ی «درست» داشته باشند');
            }
        }
    }

    public function test_wrong_answer_gets_a_second_try_without_revealing_the_answer(): void
    {
        [$student, , $token, $qs] = $this->start();

        $this->actingAs($student)->postJson(route('missions.check'), ['token' => $token, 'i' => 0, 'value' => self::wrong($qs[0])])
            ->assertOk()->assertJson(['ok' => false, 'tries' => 1, 'done' => false, 'retry' => true])
            ->assertJsonMissing(['answer' => self::right($qs[0])]);

        $this->postJson(route('missions.check'), ['token' => $token, 'i' => 0, 'value' => self::right($qs[0])])
            ->assertOk()->assertJson(['ok' => true, 'tries' => 2, 'done' => true, 'answer' => self::right($qs[0])]);
    }

    public function test_two_wrong_answers_reveal_the_answer_and_a_third_try_is_ignored(): void
    {
        [$student, , $token, $qs] = $this->start();

        $this->actingAs($student)->postJson(route('missions.check'), ['token' => $token, 'i' => 0, 'value' => self::wrong($qs[0], 0)]);
        $this->postJson(route('missions.check'), ['token' => $token, 'i' => 0, 'value' => self::wrong($qs[0], 1)])
            ->assertJson(['ok' => false, 'tries' => 2, 'done' => true, 'answer' => self::right($qs[0])]);

        // تلاشِ سوم هیچ چیزی را عوض نمی‌کند
        $this->postJson(route('missions.check'), ['token' => $token, 'i' => 0, 'value' => self::right($qs[0])])
            ->assertJson(['ok' => false, 'tries' => 2, 'done' => true]);
    }

    public function test_hint_gives_text_and_removes_a_wrong_choice_never_the_answer(): void
    {
        [$student, , $token, $qs] = $this->start();

        $res = $this->actingAs($student)->postJson(route('missions.hint'), ['token' => $token, 'i' => 0])->assertOk()->json();

        $this->assertSame('راهنمای کسرهای مساوی', $res['hint']);
        $this->assertNotNull($res['remove']);
        $this->assertNotSame(self::right($qs[0]), $res['remove']);
    }

    public function test_score_comes_from_server_state_with_half_credit_for_second_try(): void
    {
        [$student, $mission, $token, $qs] = $this->start(2);

        $this->actingAs($student)->postJson(route('missions.check'), ['token' => $token, 'i' => 0, 'value' => self::right($qs[0])]);
        $this->postJson(route('missions.check'), ['token' => $token, 'i' => 1, 'value' => self::wrong($qs[1])]);
        $this->postJson(route('missions.check'), ['token' => $token, 'i' => 1, 'value' => self::right($qs[1])]);

        // پاسخ‌های جعلیِ مرورگر نادیده گرفته می‌شوند
        $res = $this->postJson(route('missions.submit'), ['token' => $token, 'answers' => [['i' => 0, 'value' => 'x'], ['i' => 1, 'value' => 'y']]])
            ->assertOk()->json();

        $this->assertSame(2, $res['correct']);
        $this->assertSame(75, $res['percent']);
        $this->assertSame(15, $res['xp']);   // ۲۰ × (۱ + ۰٫۵) / ۲
        $this->assertSame(1, MissionCompletion::where('mission_id', $mission->id)->count());

        $answers = PracticeAnswer::withoutGlobalScopes()->where('student_id', $student->id)->orderBy('id')->get();
        $this->assertCount(2, $answers);
        $this->assertTrue($answers[0]->first_try);
        $this->assertFalse($answers[1]->first_try);
        $this->assertTrue($answers[1]->correct);

        // پاداشِ «اصلاحِ اشتباه»
        $this->assertSame('🔁', $res['growth'][0]['icon']);
    }

    public function test_points_and_evidence_only_once_per_day(): void
    {
        [$student, $mission, $token, $qs] = $this->start(1);
        $this->actingAs($student)->postJson(route('missions.check'), ['token' => $token, 'i' => 0, 'value' => self::right($qs[0])]);
        $this->postJson(route('missions.submit'), ['token' => $token])->assertJson(['already' => false, 'xp' => 20]);

        $page = $this->get(route('missions.play', $mission->id))->viewData('page');
        $t2 = $page['props']['token'];
        $this->postJson(route('missions.check'), ['token' => $t2, 'i' => 0, 'value' => self::right($page['props']['questions'][0])]);
        $this->postJson(route('missions.submit'), ['token' => $t2])->assertJson(['already' => true, 'xp' => 0]);

        $this->assertSame(1, PracticeAnswer::withoutGlobalScopes()->where('student_id', $student->id)->count());
    }

    public function test_another_students_token_is_useless(): void
    {
        [, , $token] = $this->start();
        $other = $this->makeUser(\App\Models\School::first(), \App\Support\Roles::STUDENT);

        // جلسه‌ی دانش‌آموزِ دیگر این توکن را ندارد
        $this->flushSession();
        $this->actingAs($other)->postJson(route('missions.check'), ['token' => $token, 'i' => 0, 'value' => 'x'])->assertStatus(419);
    }
}
