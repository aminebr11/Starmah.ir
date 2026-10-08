<?php

namespace Tests\Feature\Learning;

use App\Models\CurriculumChapter;
use App\Models\GradeColumn;
use App\Models\ObjectiveReview;
use App\Models\Remediation;
use App\Models\Setting;
use App\Models\SmartExam;
use App\Models\SmartExamAttempt;
use App\Models\SmartExamQuestion;
use App\Models\SmartQuestionBank;
use App\Models\XpEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/**
 * هسته‌ی یادگیری — فاز ۲: فصلِ هر سؤال، دفترِ نمره‌ی فصل‌دار و «جبرانِ اشتباه».
 */
class RemediationTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    private function chapter(string $title, int $n): CurriculumChapter
    {
        return CurriculumChapter::create(['grade' => 'چهارم', 'subject' => 'ریاضی', 'number' => $n, 'title' => $title, 'is_active' => true]);
    }

    /** آزمونِ دو سؤالی (هر کدام ۱ نمره) از بانک؛ فصلِ آزمون = $chapter. */
    private function exam($teacher, array $bank, ?CurriculumChapter $chapter, array $rules = [], array $extra = []): SmartExam
    {
        $exam = SmartExam::create(array_merge(['school_id' => $teacher->school_id, 'teacher_id' => $teacher->id, 'title' => 'آزمونِ کسر', 'grade' => 'چهارم',
            'subject' => 'ریاضی', 'chapter_id' => $chapter?->id, 'kind' => 'practice', 'status' => 'published', 'rules' => $rules], $extra));
        foreach ($bank as $k => $b) {
            SmartExamQuestion::create(['smart_exam_id' => $exam->id, 'bank_id' => $b->id, 'type' => 'mc', 'prompt' => $b->prompt, 'choices' => $b->choices, 'points' => 1, 'sort' => $k]);
        }

        return $exam;
    }

    private function submit($student, SmartExam $exam, array $answers, string $tok = 'tok-1')
    {
        SmartExamAttempt::create(['smart_exam_id' => $exam->id, 'student_id' => $student->id, 'attempt_no' => 1, 'token' => $tok,
            'token_expires_at' => now()->addHour(), 'started_at' => now(), 'status' => 'in_progress']);

        return $this->actingAs($student)->post(route('student.smart.submit', $exam), ['token' => $tok, 'duration_sec' => 30, 'answers' => $answers]);
    }

    private function setUpSchool(): array
    {
        $this->seedRoles();
        Setting::put('smart_lab_enabled', true);
        Setting::put('smart_scope', 'all');
        [$teacher, $classroom, $student] = $this->makeClass($this->makeSchool());
        $ch = $this->chapter('کسرها', 2);
        // ۲ سؤالِ آزمون + ۴ سؤالِ مشابه در همان فصل
        $bank = $this->makeQuestions($teacher, 'کسر', 6, ['chapter_id' => $ch->id]);

        return [$teacher, $classroom, $student, $ch, $bank];
    }

    public function test_wrong_answer_creates_private_remediation_with_half_the_lost_points(): void
    {
        [$teacher, $classroom, $student, $ch, $bank] = $this->setUpSchool();
        $other = $this->addStudent($teacher->school, $classroom);
        $exam = $this->exam($teacher, [$bank[0], $bank[1]], $ch);

        $this->submit($student, $exam, [['i' => 0, 'value' => $bank[0]->choices[0]['value']], ['i' => 1, 'value' => $bank[1]->choices[1]['value']]])->assertRedirect();

        $rems = Remediation::where('student_id', $student->id)->get();
        $this->assertCount(1, $rems, 'فقط برای سؤالِ اشتباه');
        $rem = $rems->first();
        // XPِ آزمون = ۱۰۰ × درست/کل؛ سؤالِ غلط ۵۰ امتیاز از دست داده؛ سقفِ جبران نصفِ آن
        $this->assertSame([50, 25, 'open', 0], [$rem->lost_xp, $rem->cap_xp, $rem->status, $rem->step]);
        $this->assertSame(now()->toDateString(), $rem->due_on->toDateString());
        $this->assertSame($bank[1]->id, $rem->bank_id);
        $this->assertNotNull($rem->objective_id);
        $this->assertSame('فصل ۲ — کسرها', $rem->objective_label);
        // اعلان فقط برای خودِ دانش‌آموز
        $this->assertDatabaseHas('announcement_recipients', ['user_id' => $student->id]);
        $this->assertDatabaseMissing('announcement_recipients', ['user_id' => $other->id]);
        $this->assertSame(0, Remediation::where('student_id', $other->id)->count());
    }

    public function test_three_spaced_steps_recover_at_most_half_and_never_beat_a_perfect_score(): void
    {
        [$teacher, , $student, $ch, $bank] = $this->setUpSchool();
        $exam = $this->exam($teacher, [$bank[0], $bank[1]], $ch);
        $this->submit($student, $exam, [['i' => 0, 'value' => $bank[0]->choices[0]['value']], ['i' => 1, 'value' => $bank[1]->choices[1]['value']]]);
        $rem = Remediation::where('student_id', $student->id)->firstOrFail();

        foreach ([1, 2, 3] as $step) {
            $page = $this->actingAs($student)->get(route('missions.remedial.play'))->assertOk();
            $props = $page->viewData('page')['props'];
            $this->assertSame('remedial', $props['mission']['kind']);
            // همان سؤالِ اشتباه + سؤالِ مشابه از همان فصل
            $prompts = collect($props['questions'])->pluck('prompt');
            $this->assertContains($bank[1]->prompt, $prompts->all());
            $this->assertGreaterThanOrEqual(2, $prompts->count());
            // کلیدِ پاسخ به مرورگر نمی‌رود
            $this->assertStringNotContainsString('"correct"', json_encode($props['questions']));

            foreach ($props['questions'] as $q) {
                $right = SmartQuestionBank::withoutGlobalScopes()->where('prompt', $q['prompt'])->first();
                $value = collect($right->choices)->firstWhere('correct', true)['value'];
                $this->postJson(route('missions.check'), ['token' => $props['token'], 'i' => $q['i'], 'value' => $value])->assertJson(['ok' => true]);
            }
            $res = $this->postJson(route('missions.submit'), ['token' => $props['token']])->assertOk()->json();
            $this->assertSame('remedial', $res['kind']);

            $rem->refresh();
            $this->assertSame($step, $rem->step);
            if ($step < 3) {
                $this->assertSame('open', $rem->status);
                $this->assertTrue($rem->due_on->isFuture(), 'نوبتِ بعد با فاصله');
                $this->travelTo($rem->due_on->copy()->setTime(9, 0));
            }
        }

        $this->assertSame('done', $rem->status);
        $this->assertSame($rem->cap_xp, $rem->recovered_xp);
        $this->assertSame(25, (int) XpEntry::where('source_type', Remediation::class)->where('student_id', $student->id)->sum('amount'));
        // ۵۰ (آزمون) + ۲۵ (جبران) < ۱۰۰ (همه درست)
        $examXp = (int) XpEntry::where('student_id', $student->id)->where('source_type', \App\Models\SmartExamReward::class)->sum('amount');
        $this->assertLessThan(100, $examXp + $rem->recovered_xp);
        $this->assertNull($this->actingAs($student)->get(route('missions.remedial.play'))->assertRedirect()->headers->get('X-Inertia'));
    }

    public function test_failed_step_comes_back_tomorrow_without_points(): void
    {
        [$teacher, , $student, $ch, $bank] = $this->setUpSchool();
        $exam = $this->exam($teacher, [$bank[0], $bank[1]], $ch);
        $this->submit($student, $exam, [['i' => 0, 'value' => $bank[0]->choices[1]['value']], ['i' => 1, 'value' => $bank[1]->choices[0]['value']]]);

        $props = $this->actingAs($student)->get(route('missions.remedial.play'))->viewData('page')['props'];
        foreach ($props['questions'] as $q) {
            $wrong = collect(SmartQuestionBank::withoutGlobalScopes()->where('prompt', $q['prompt'])->first()->choices)->firstWhere('correct', false)['value'];
            $this->postJson(route('missions.check'), ['token' => $props['token'], 'i' => $q['i'], 'value' => $wrong]);
            $this->postJson(route('missions.check'), ['token' => $props['token'], 'i' => $q['i'], 'value' => $wrong]);
        }
        $this->postJson(route('missions.submit'), ['token' => $props['token']])->assertOk()->assertJson(['xp' => 0]);

        $rem = Remediation::where('student_id', $student->id)->firstOrFail();
        $this->assertSame([0, 1, 'open'], [$rem->step, $rem->tries, $rem->status]);
        $this->assertSame(now()->addDay()->toDateString(), $rem->due_on->toDateString());
        $this->assertSame(0, XpEntry::where('source_type', Remediation::class)->count());
    }

    public function test_answers_do_not_leak_while_the_exam_is_open_for_others(): void
    {
        [$teacher, , $student, $ch, $bank] = $this->setUpSchool();
        $exam = $this->exam($teacher, [$bank[0], $bank[1]], $ch, [], ['closes_at' => now()->addDays(3)]);
        $this->submit($student, $exam, [['i' => 0, 'value' => $bank[0]->choices[0]['value']], ['i' => 1, 'value' => $bank[1]->choices[1]['value']]]);
        $rem = Remediation::where('student_id', $student->id)->firstOrFail();
        $this->assertSame(now()->addDays(4)->toDateString(), $rem->due_on->toDateString(), 'بعد از بسته‌شدنِ آزمون');

        // آزمونِ بی‌مهلت با «نمایشِ پاسخ» خاموش: فقط سؤال‌های مشابه، نه خودِ سؤالِ آزمون
        $exam2 = $this->exam($teacher, [$bank[2], $bank[3]], $ch, ['show_answer' => false]);
        $exam2->update(['title' => 'آزمونِ دوم']);
        $this->submit($student, $exam2, [['i' => 0, 'value' => $bank[2]->choices[0]['value']], ['i' => 1, 'value' => $bank[3]->choices[1]['value']]], 'tok-2');
        $props = $this->actingAs($student)->get(route('missions.remedial.play'))->viewData('page')['props'];
        $this->assertNotContains($bank[3]->prompt, collect($props['questions'])->pluck('prompt')->all());
        $this->assertNotEmpty($props['questions']);
    }

    public function test_restart_removes_remediations_and_their_points(): void
    {
        [$teacher, , $student, $ch, $bank] = $this->setUpSchool();
        $exam = $this->exam($teacher, [$bank[0], $bank[1]], $ch);
        $this->submit($student, $exam, [['i' => 0, 'value' => $bank[0]->choices[0]['value']], ['i' => 1, 'value' => $bank[1]->choices[1]['value']]]);
        $this->assertSame(1, Remediation::count());

        $this->actingAs($teacher)->post(route('teacher.smart.release', $exam), ['student_ids' => [$student->id]])->assertRedirect();
        $this->assertSame(0, Remediation::count());
    }

    public function test_each_question_is_tracked_in_its_own_chapter(): void
    {
        [$teacher, $classroom, $student] = $this->setUpSchool();
        $a = $this->chapter('عددها', 1);
        $b = $this->chapter('هندسه', 3);
        $mc = fn ($p) => ['type' => 'mc', 'prompt' => $p, 'points' => 1, 'choices' => [['value' => 'بله', 'correct' => true], ['value' => 'خیر', 'correct' => false]]];

        $this->actingAs($teacher)->post(route('teacher.smart.store'), [
            'title' => 'آزمونِ دوفصلی', 'grade' => 'چهارم', 'subject' => 'ریاضی', 'chapter_id' => $a->id, 'status' => 'published',
            'target_classrooms' => [$classroom->id],
            'questions' => [$mc('سؤالِ فصلِ عددها'), $mc('سؤالِ فصلِ هندسه') + ['chapter_id' => $b->id]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $exam = SmartExam::where('title', 'آزمونِ دوفصلی')->firstOrFail();
        $qs = $exam->questions()->orderBy('sort')->get();
        $this->assertSame([null, $b->id], [$qs[0]->chapter_id, $qs[1]->chapter_id]);
        // ردیفِ بانکِ هر سؤال در فصلِ خودش
        $this->assertSame($a->id, (int) SmartQuestionBank::withoutGlobalScopes()->find($qs[0]->bank_id)->chapter_id);
        $this->assertSame($b->id, (int) SmartQuestionBank::withoutGlobalScopes()->find($qs[1]->bank_id)->chapter_id);

        $this->submit($student, $exam, [['i' => 0, 'value' => 'بله'], ['i' => 1, 'value' => 'خیر']]);
        $labels = \App\Models\LearningObjective::whereIn('id', ObjectiveReview::withoutGlobalScopes()->where('student_id', $student->id)->pluck('objective_id'))->pluck('label')->sort()->values()->all();
        $this->assertSame(['فصل ۱ — عددها', 'فصل ۳ — هندسه'], $labels);
        $this->assertSame('فصل ۳ — هندسه', Remediation::where('student_id', $student->id)->firstOrFail()->objective_label);
    }

    public function test_weak_teacher_grade_with_a_chapter_schedules_review(): void
    {
        [$teacher, , $student, $ch] = $this->setUpSchool();

        $this->actingAs($teacher)->post(route('teacher.gradebook.activities'), [
            'title' => 'پرسشِ کلاسی', 'score_type' => 'numeric', 'lesson' => 'ریاضی', 'chapter_id' => $ch->id, 'max' => 20,
            'grades' => [['student_id' => $student->id, 'score' => 6]],
        ])->assertRedirect();

        $this->assertSame($ch->id, (int) GradeColumn::firstOrFail()->chapter_id);
        $r = ObjectiveReview::withoutGlobalScopes()->where('student_id', $student->id)->firstOrFail();
        $this->assertSame(1, $r->box);
        $this->assertSame('فصل ۲ — کسرها', $r->objective->label);
    }

    public function test_teacher_sees_the_remediation_overview(): void
    {
        [$teacher, , $student, $ch, $bank] = $this->setUpSchool();
        $exam = $this->exam($teacher, [$bank[0], $bank[1]], $ch);
        $this->submit($student, $exam, [['i' => 0, 'value' => $bank[0]->choices[1]['value']], ['i' => 1, 'value' => $bank[1]->choices[1]['value']]]);

        $this->actingAs($teacher)->get(route('teacher.reports'))->assertOk();
        $page = $this->actingAs($teacher)->get(route('teacher.review'))->assertOk();
        $rem = $page->viewData('page')['props']['remediation'];
        $this->assertSame(2, $rem['totals']['total']);
        $this->assertSame($student->id, $rem['students'][0]['id']);
        $this->assertSame('فصل ۲ — کسرها', $rem['chapters'][0]['label']);
    }

    public function test_game_and_mission_mistakes_also_create_remediation(): void
    {
        [$teacher, $classroom, $student, $ch, $bank] = $this->setUpSchool();
        $this->seed(\Database\Seeders\GameTemplateSeeder::class);

        // بازی: سؤالِ دوم (۱۰ امتیازی) غلط ← ۱۰ امتیاز از دست رفته، سقفِ جبران ۵
        $game = \App\Models\EduGame::create(['school_id' => $teacher->school_id, 'teacher_id' => $teacher->id,
            'template_key' => \App\Models\GameTemplate::value('key'), 'title' => 'بازیِ کسر', 'grade' => 'چهارم', 'subject' => 'ریاضی',
            'chapter_id' => $ch->id, 'status' => 'published']);
        foreach ([$bank[0], $bank[1]] as $k => $b) {
            \App\Models\EduGameQuestion::create(['edu_game_id' => $game->id, 'bank_id' => $b->id, 'type' => 'mc', 'prompt' => $b->prompt, 'choices' => $b->choices, 'points' => 10, 'sort' => $k]);
        }
        $this->actingAs($student)->post(route('gameworld.finish', $game), ['answers' => [0 => 0, 1 => 1]])->assertRedirect();
        $g = Remediation::where('student_id', $student->id)->where('source', 'game')->firstOrFail();
        $this->assertSame([10, 5, $bank[1]->id], [$g->lost_xp, $g->cap_xp, $g->bank_id]);

        // مأموریت: سؤالِ دیگری که دو بار غلط زده شد
        $mission = $this->makeMission($teacher, $classroom, [$bank[2]], ['xp_reward' => 20]);
        $props = $this->actingAs($student)->get(route('missions.play', $mission))->viewData('page')['props'];
        $wrong = collect($bank[2]->choices)->firstWhere('correct', false)['value'];
        $this->postJson(route('missions.check'), ['token' => $props['token'], 'i' => 0, 'value' => $wrong]);
        $this->postJson(route('missions.check'), ['token' => $props['token'], 'i' => 0, 'value' => $wrong]);
        $this->postJson(route('missions.submit'), ['token' => $props['token']])->assertOk()->assertJson(['remedial_made' => 1]);
        $m = Remediation::where('student_id', $student->id)->where('source', 'mission')->firstOrFail();
        $this->assertSame([20, 10], [$m->lost_xp, $m->cap_xp]);

        // همان سؤال در مأموریتِ فردا دوباره غلط شود → یادآوریِ تکراری ساخته نمی‌شود
        $this->travel(1)->days();
        $props = $this->actingAs($student)->get(route('missions.play', $mission))->viewData('page')['props'];
        $this->postJson(route('missions.check'), ['token' => $props['token'], 'i' => 0, 'value' => $wrong]);
        $this->postJson(route('missions.check'), ['token' => $props['token'], 'i' => 0, 'value' => $wrong]);
        $this->postJson(route('missions.submit'), ['token' => $props['token']])->assertOk();
        $this->assertSame(1, Remediation::where('student_id', $student->id)->where('source', 'mission')->count());
    }
}
