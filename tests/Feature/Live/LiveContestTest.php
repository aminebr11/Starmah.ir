<?php

namespace Tests\Feature\Live;

use App\Models\LiveContest;
use App\Models\LiveContestPlayer;
use App\Models\XpEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** «🏆 مسابقه‌ی زنده»: ساخت، اجرا روی تخته، پاسخِ گوشی، امتیاز و حالتِ خودکار. */
class LiveContestTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    private function questions(): array
    {
        return [
            ['prompt' => '۲ + ۳ = ?', 'choices' => ['۴', '۵', '۶'], 'answer' => 1],
            ['prompt' => 'پایتختِ ایران؟', 'choices' => ['تهران', 'شیراز'], 'answer' => 0],
        ];
    }

    private function make(array $extra = []): array
    {
        $this->seedRoles();
        [$teacher, $classroom, $student] = $this->makeClass($this->makeSchool());
        $this->actingAs($teacher)->post(route('teacher.live.store'), array_merge([
            'title' => 'مسابقه‌ی ریاضی', 'classroom_id' => $classroom->id, 'mode' => 'manual', 'seconds' => 20, 'questions' => $this->questions(),
        ], $extra))->assertSessionHasNoErrors()->assertRedirect();

        return [$teacher, $classroom, $student, LiveContest::firstOrFail()];
    }

    public function test_full_manual_round_with_scoring_and_xp(): void
    {
        [$teacher, $classroom, $student, $c] = $this->make();
        $slow = $this->addStudent($teacher->school, $classroom);
        $outsider = $this->makeClass($this->makeSchool('other'))[2];
        $this->assertDatabaseHas('announcement_recipients', ['user_id' => $student->id]);

        $this->actingAs($student)->get(route('live'))->assertOk();
        $this->actingAs($student)->get(route('live.play', $c))->assertOk();
        $this->actingAs($slow)->get(route('live.play', $c))->assertOk();
        $this->actingAs($outsider)->get(route('live.play', $c))->assertForbidden();
        $this->actingAs($teacher)->get(route('teacher.live.host', $c))->assertOk();
        $this->actingAs($teacher)->getJson(route('teacher.live.state', $c))->assertJsonPath('players', 2)->assertJsonPath('phase', 'lobby');

        // پیش از شروع جواب پذیرفته نمی‌شود
        $this->actingAs($student)->postJson(route('live.answer', $c), ['q' => 0, 'choice' => 1])->assertStatus(422);

        $this->actingAs($teacher)->postJson(route('teacher.live.go', $c), ['action' => 'start'])->assertJsonPath('phase', 'question');
        // «۳، ۲، ۱»: هنوز گزینه‌ها باز نیستند
        $this->actingAs($student)->postJson(route('live.answer', $c), ['q' => 0, 'choice' => 1])->assertStatus(422);
        $this->travel(4)->seconds();
        // دانش‌آموز جواب را پیش از نمایش نمی‌بیند
        $poll = $this->actingAs($student)->getJson(route('live.poll', $c))->assertOk()->json();
        $this->assertNull($poll['answer']);
        $this->assertSame(['۴', '۵', '۶'], $poll['question']['choices']);

        $this->actingAs($student)->postJson(route('live.answer', $c), ['q' => 0, 'choice' => 1])->assertOk();
        $this->actingAs($student)->postJson(route('live.answer', $c), ['q' => 0, 'choice' => 0])->assertOk()->assertJsonPath('already', true);
        $this->actingAs($slow)->postJson(route('live.answer', $c), ['q' => 0, 'choice' => 2])->assertOk();

        $state = $this->actingAs($teacher)->postJson(route('teacher.live.go', $c), ['action' => 'reveal'])->json();
        $this->assertSame('reveal', $state['phase']);
        $this->assertSame([0, 1, 1], $state['dist']);
        $this->assertSame(1, $state['answer']);
        $this->assertSame($student->id, $state['top'][0]['id']);
        $me = $this->actingAs($student)->getJson(route('live.poll', $c))->json();
        $this->assertTrue($me['mine']['correct']);
        $this->assertGreaterThanOrEqual(500, $me['mine']['points']);
        $this->assertSame(1, $me['me']['rank']);

        // سؤالِ دوم و پایان
        $this->actingAs($teacher)->postJson(route('teacher.live.go', $c), ['action' => 'next'])->assertJsonPath('current', 1);
        $this->travel(4)->seconds();
        $this->actingAs($student)->postJson(route('live.answer', $c), ['q' => 1, 'choice' => 0])->assertOk();
        $this->actingAs($teacher)->postJson(route('teacher.live.go', $c), ['action' => 'reveal']);
        $end = $this->actingAs($teacher)->postJson(route('teacher.live.go', $c), ['action' => 'next'])->json();
        $this->assertSame('end', $end['phase']);
        $this->assertSame(2, LiveContestPlayer::where('live_contest_id', $c->id)->where('student_id', $student->id)->value('correct'));

        // امتیاز یک بار: ۲ + ۳×۲ + ۱۵ = ۲۳ برای اول؛ ۲ + ۱۰ برای دوم
        $this->assertSame(23, (int) XpEntry::where('student_id', $student->id)->where('source_type', 'live_contest')->sum('amount'));
        $this->assertSame(12, (int) XpEntry::where('student_id', $slow->id)->where('source_type', 'live_contest')->sum('amount'));
        $this->actingAs($teacher)->postJson(route('teacher.live.go', $c), ['action' => 'end']);
        $c->fresh()->reward();
        $this->assertSame(2, XpEntry::where('source_type', 'live_contest')->count());

        $list = $this->actingAs($student)->get(route('live'))->viewData('page')['props']['contests'];
        $this->assertSame(1, $list[0]['rank']);
    }

    public function test_time_runs_out_and_late_answers_are_rejected(): void
    {
        [$teacher, , $student, $c] = $this->make(['seconds' => 5]);
        $this->actingAs($teacher)->postJson(route('teacher.live.go', $c), ['action' => 'start']);
        LiveContest::whereKey($c->id)->update(['phase_at' => LiveContest::nowMs() - 9000]);
        $this->actingAs($student)->postJson(route('live.answer', $c), ['q' => 0, 'choice' => 1])->assertStatus(422);
        $this->assertSame('reveal', $this->actingAs($student)->getJson(route('live.poll', $c))->json('phase'));
    }

    public function test_auto_mode_starts_on_time_and_advances(): void
    {
        [$teacher, , $student, $c] = $this->make(['mode' => 'auto', 'starts_at' => now()->addMinutes(5)->format('Y-m-d H:i:s')]);
        $this->assertSame('lobby', $this->actingAs($student)->getJson(route('live.poll', $c))->json('phase'));
        $this->assertGreaterThan(200, $this->actingAs($student)->getJson(route('live.poll', $c))->json('starts_in'));

        LiveContest::whereKey($c->id)->update(['starts_at' => now()->subSecond()]);
        $this->assertSame('question', $this->actingAs($student)->getJson(route('live.poll', $c))->json('phase'));
        LiveContest::whereKey($c->id)->update(['phase_at' => LiveContest::nowMs() - 21000]);
        $this->assertSame('reveal', $this->actingAs($student)->getJson(route('live.poll', $c))->json('phase'));
        LiveContest::whereKey($c->id)->update(['phase_at' => LiveContest::nowMs() - LiveContest::REVEAL_MS - 10]);
        $this->assertSame(1, $this->actingAs($student)->getJson(route('live.poll', $c))->json('current'));
    }

    public function test_validation_and_import_from_bank(): void
    {
        $this->seedRoles();
        [$teacher, $classroom] = $this->makeClass($this->makeSchool());
        $this->actingAs($teacher)->post(route('teacher.live.store'), [
            'title' => 'x', 'classroom_id' => $classroom->id, 'mode' => 'auto', 'seconds' => 20, 'questions' => $this->questions(),
        ])->assertSessionHasErrors('starts_at');
        $this->actingAs($teacher)->post(route('teacher.live.store'), [
            'title' => 'x', 'classroom_id' => $classroom->id, 'mode' => 'manual', 'seconds' => 20,
            'questions' => [['prompt' => 'بی‌گزینه', 'choices' => ['', 'الف'], 'answer' => 0]],
        ])->assertSessionHasErrors('questions');

        $this->makeQuestions($teacher, 'کسر', 4);
        $got = $this->actingAs($teacher)->getJson(route('teacher.live.import', ['source' => 'bank', 'n' => 3]))->assertOk()->json('questions');
        $this->assertCount(3, $got);
        $this->assertSame(4, count($got[0]['choices']));
        $this->assertStringStartsWith('درست', $got[0]['choices'][$got[0]['answer']]);
        $this->actingAs($teacher)->get(route('teacher.live'))->assertOk();
    }

    public function test_manual_contest_with_time_starts_itself_and_runs_to_the_end_without_the_board(): void
    {
        [$teacher, , $student, $c] = $this->make(['starts_at' => now()->addMinutes(5)->format('Y-m-d H:i')]);
        $this->actingAs($student)->getJson(route('live.poll', $c))->assertJsonPath('phase', 'lobby');

        // سرِ ساعت، بدونِ اینکه معلم تخته را باز کند
        $this->travel(5)->minutes();
        $this->actingAs($student)->getJson(route('live.poll', $c))->assertJsonPath('phase', 'question')->assertJsonPath('current', 0);
        $this->travel(4)->seconds();
        $this->actingAs($student)->postJson(route('live.answer', $c), ['q' => 0, 'choice' => 1])->assertOk();
        // تنها شرکت‌کننده جواب داد → جواب نشان داده می‌شود، بعد خودش می‌رود سؤالِ بعد
        $this->actingAs($student)->getJson(route('live.poll', $c))->assertJsonPath('phase', 'reveal');
        $this->travel(8)->seconds();
        $this->actingAs($student)->getJson(route('live.poll', $c))->assertJsonPath('current', 1);
        $this->travel(4)->seconds();
        $this->actingAs($student)->postJson(route('live.answer', $c), ['q' => 1, 'choice' => 0])->assertOk();
        $this->actingAs($student)->getJson(route('live.poll', $c));
        $this->travel(8)->seconds();
        $end = $this->actingAs($student)->getJson(route('live.poll', $c))->assertJsonPath('phase', 'end')->json();

        $this->assertSame(1, $end['me']['rank']);
        $this->assertSame(2, $end['summary']['correct']);
        $this->assertSame(100, $end['summary']['accuracy']);
        $this->assertSame(23, $end['summary']['xp']);
        $this->assertSame(23, (int) XpEntry::where('student_id', $student->id)->where('source_type', 'live_contest')->sum('amount'));
    }

    public function test_abandoned_contest_is_finished_and_points_are_recorded(): void
    {
        [$teacher, , $student, $c] = $this->make();
        $this->actingAs($teacher)->postJson(route('teacher.live.go', $c), ['action' => 'start']);
        $this->travel(4)->seconds();
        $this->actingAs($student)->postJson(route('live.answer', $c), ['q' => 0, 'choice' => 1])->assertOk();
        $this->actingAs($teacher)->postJson(route('teacher.live.go', $c), ['action' => 'reveal']);

        // معلم تخته را بست؛ کسی هم دیگر وارد نشد
        $this->travel(LiveContest::STALE_MIN + 1)->minutes();
        $this->actingAs($student)->get(route('live'))->assertOk();
        $this->assertSame('end', $c->fresh()->phase);
        $this->assertTrue(XpEntry::where('student_id', $student->id)->where('source_type', 'live_contest')->exists());
    }

    public function test_last_question_is_golden_and_worth_double(): void
    {
        $qs = array_merge($this->questions(), [['prompt' => '۹ − ۴ = ?', 'choices' => ['۵', '۶'], 'answer' => 0]]);
        [$teacher, , $student, $c] = $this->make(['questions' => $qs]);
        $c->update(['phase' => 'question', 'current' => 2, 'phase_at' => LiveContest::nowMs()]);
        $this->actingAs($teacher)->getJson(route('teacher.live.state', $c))->assertJsonPath('golden', true);
        $this->actingAs($student)->postJson(route('live.answer', $c), ['q' => 2, 'choice' => 0])->assertOk();
        $this->assertGreaterThanOrEqual(1900, (int) LiveContestPlayer::where('student_id', $student->id)->value('score'));
    }
}
