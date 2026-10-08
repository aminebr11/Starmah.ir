<?php

namespace Tests\Feature\Learning;

use App\Models\CurriculumChapter;
use App\Models\Remediation;
use App\Models\RemediationPlan;
use App\Models\Setting;
use App\Models\SmartExam;
use App\Models\SmartExamAttempt;
use App\Models\SmartExamQuestion;
use App\Models\SmartQuestionBank;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/**
 * زمان‌بندیِ «مرورِ اشتباه‌ها» که معلم برای کلاس اعلام می‌کند، و صفحه‌های اختصاصیِ مرور.
 */
class ReviewScheduleTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    private function setUpSchool(): array
    {
        $this->seedRoles();
        Setting::put('smart_lab_enabled', true);
        Setting::put('smart_scope', 'all');
        [$teacher, $classroom, $student] = $this->makeClass($this->makeSchool());
        $ch = CurriculumChapter::create(['grade' => 'چهارم', 'subject' => 'ریاضی', 'number' => 2, 'title' => 'کسرها', 'is_active' => true]);
        $bank = $this->makeQuestions($teacher, 'کسر', 6, ['chapter_id' => $ch->id]);

        return [$teacher, $classroom, $student, $ch, $bank];
    }

    /** آزمونِ دو سؤالی؛ سؤالِ دوم اشتباه جواب داده می‌شود. */
    private function examWithOneMistake($teacher, $student, $ch, array $bank): void
    {
        $exam = SmartExam::create(['school_id' => $teacher->school_id, 'teacher_id' => $teacher->id, 'title' => 'آزمونِ کسر', 'grade' => 'چهارم',
            'subject' => 'ریاضی', 'chapter_id' => $ch->id, 'kind' => 'practice', 'status' => 'published', 'rules' => []]);
        foreach ([$bank[0], $bank[1]] as $k => $b) {
            SmartExamQuestion::create(['smart_exam_id' => $exam->id, 'bank_id' => $b->id, 'type' => 'mc', 'prompt' => $b->prompt, 'choices' => $b->choices, 'points' => 1, 'sort' => $k]);
        }
        SmartExamAttempt::create(['smart_exam_id' => $exam->id, 'student_id' => $student->id, 'attempt_no' => 1, 'token' => 'tok',
            'token_expires_at' => now()->addHour(), 'started_at' => now(), 'status' => 'in_progress']);
        $this->actingAs($student)->post(route('student.smart.submit', $exam), ['token' => 'tok', 'duration_sec' => 30, 'answers' => [
            ['i' => 0, 'value' => $bank[0]->choices[0]['value']], ['i' => 1, 'value' => $bank[1]->choices[1]['value']],
        ]])->assertRedirect();
    }

    private function playSession($student, bool $right): array
    {
        $props = $this->actingAs($student)->get(route('missions.remedial.play'))->assertOk()->viewData('page')['props'];
        foreach ($props['questions'] as $q) {
            $choices = collect(SmartQuestionBank::withoutGlobalScopes()->where('prompt', $q['prompt'])->first()->choices);
            $value = $choices->firstWhere('correct', $right)['value'];
            $this->postJson(route('missions.check'), ['token' => $props['token'], 'i' => $q['i'], 'value' => $value]);
            if (! $right) {
                $this->postJson(route('missions.check'), ['token' => $props['token'], 'i' => $q['i'], 'value' => $value]);
            }
        }
        $this->postJson(route('missions.submit'), ['token' => $props['token']])->assertOk();

        return $props;
    }

    public function test_teacher_schedule_drives_the_automatic_review(): void
    {
        [$teacher, $classroom, $student, $ch, $bank] = $this->setUpSchool();

        $this->actingAs($teacher)->put(route('teacher.review.plan'), [
            'classroom_id' => $classroom->id, 'enabled' => true,
            'sources' => ['exam' => true, 'game' => true, 'mission' => true, 'grade' => true],
            'first_delay' => 1, 'rounds' => 2, 'gaps' => [4], 'retry' => 3, 'per_session' => 4, 'similar' => 1, 'share' => 30, 'pass' => 60,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->examWithOneMistake($teacher, $student, $ch, $bank);
        $rem = Remediation::where('student_id', $student->id)->firstOrFail();
        // نوبتِ اول «فردا»، ۲ نوبت، سقفِ جبران ۳۰٪ از ۵۰
        $this->assertSame([now()->addDay()->toDateString(), 2, 15], [$rem->due_on->toDateString(), $rem->steps, $rem->cap_xp]);
        $this->assertSame(['gaps' => [4], 'retry' => 3, 'pass' => 60], $rem->plan);

        // امروز هنوز نوبتش نیست
        $this->actingAs($student)->get(route('missions.remedial.play'))->assertRedirect(route('review'));

        // فردا: رد شد → ۳ روز بعد دوباره
        $this->travelTo(now()->addDay()->setTime(9, 0));
        $props = $this->playSession($student, false);
        // همان سؤال + ۱ مشابه (تنظیمِ معلم)
        $this->assertCount(2, $props['questions']);
        $this->assertContains($bank[1]->prompt, collect($props['questions'])->pluck('prompt')->all());
        $rem->refresh();
        $this->assertSame(now()->addDays(3)->toDateString(), $rem->due_on->toDateString());

        // قبول → فاصله‌ی ۴ روز
        $this->travelTo($rem->due_on->copy()->setTime(9, 0));
        $this->playSession($student, true);
        $rem->refresh();
        $this->assertSame([1, now()->addDays(4)->toDateString()], [$rem->step, $rem->due_on->toDateString()]);

        // نوبتِ دوم (آخر) → جبران شد
        $this->travelTo($rem->due_on->copy()->setTime(9, 0));
        $this->playSession($student, true);
        $rem->refresh();
        $this->assertSame(['done', 15], [$rem->status, $rem->recovered_xp]);
    }

    public function test_teacher_can_turn_off_a_source_or_the_whole_review(): void
    {
        [$teacher, $classroom, $student, $ch, $bank] = $this->setUpSchool();
        RemediationPlan::put($classroom->id, $teacher->id, ['sources' => ['exam' => false]]);
        $this->examWithOneMistake($teacher, $student, $ch, $bank);
        $this->assertSame(0, Remediation::count());

        // مرورِ دستیِ معلم حتی با خاموش‌بودنِ خودکار کار می‌کند
        RemediationPlan::put($classroom->id, $teacher->id, ['enabled' => false]);
        $this->actingAs($teacher)->post(route('teacher.remediations.store'), ['student_ids' => [$student->id], 'subject' => 'ریاضی', 'chapter_id' => $ch->id])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Remediation::where('source', 'teacher')->count());
    }

    public function test_changing_the_schedule_updates_open_reviews(): void
    {
        [$teacher, $classroom, $student, $ch, $bank] = $this->setUpSchool();
        $this->examWithOneMistake($teacher, $student, $ch, $bank);
        $rem = Remediation::firstOrFail();
        $this->assertSame(3, $rem->steps);

        $this->actingAs($teacher)->put(route('teacher.review.plan'), [
            'classroom_id' => $classroom->id, 'enabled' => true, 'first_delay' => 0, 'rounds' => 4, 'gaps' => [1, 2, 7],
            'retry' => 2, 'per_session' => 4, 'similar' => 2, 'share' => 50, 'pass' => 70,
        ])->assertSessionHasNoErrors();
        $rem->refresh();
        $this->assertSame([4, [1, 2, 7], 2, 70], [$rem->steps, $rem->plan['gaps'], $rem->plan['retry'], $rem->plan['pass']]);

        // سقفِ جبران بیش از ۵۰٪ پذیرفته نمی‌شود
        $this->actingAs($teacher)->put(route('teacher.review.plan'), [
            'classroom_id' => $classroom->id, 'first_delay' => 0, 'rounds' => 3, 'retry' => 1, 'per_session' => 4, 'similar' => 2, 'share' => 80, 'pass' => 60,
        ])->assertSessionHasErrors('share');
    }

    public function test_review_pages_and_menus(): void
    {
        [$teacher, $classroom, $student, $ch, $bank] = $this->setUpSchool();

        // پیش از هر اشتباه: صفحه باز می‌شود و زمان‌بندیِ پیش‌فرض را نشان می‌دهد
        $board = $this->actingAs($student)->get(route('review'))->assertOk()->viewData('page')['props']['board'];
        $this->assertTrue($board['enabled']);
        $this->assertSame([], $board['ready']);
        $this->assertSame(3, $board['plan']['rounds']);

        $this->examWithOneMistake($teacher, $student, $ch, $bank);
        $page = $this->actingAs($student)->get(route('review'))->assertOk()->viewData('page')['props'];
        $this->assertSame(1, $page['reviewDue']);
        $this->assertCount(1, $page['board']['ready']);
        $this->assertSame('🧪 آزمون', $page['board']['ready'][0]['source']);
        // اعلان به صفحه‌ی مرور می‌رود
        $this->assertDatabaseHas('announcements', ['link' => '/review']);

        $t = $this->actingAs($teacher)->get(route('teacher.review'))->assertOk()->viewData('page')['props'];
        $this->assertSame($classroom->id, $t['classroom']['id']);
        $this->assertSame(1, $t['remediation']['totals']['total']);
    }
}
