<?php

namespace Tests\Feature\Learning;

use App\Models\ObjectiveReview;
use App\Models\PracticeAnswer;
use App\Models\Setting;
use App\Models\SmartExam;
use App\Models\SmartExamAttempt;
use App\Models\SmartExamQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** ارسالِ آزمونِ هوشمند موعدِ مرورِ هدف‌های بانکیِ آن را به‌روز می‌کند. */
class SmartExamScheduleTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    public function test_smart_exam_submit_schedules_review_for_bank_objectives(): void
    {
        $this->seedRoles();
        Setting::put('smart_lab_enabled', true);
        Setting::put('smart_scope', 'all');
        [$teacher, , $student] = $this->makeClass($this->makeSchool());
        $bank = $this->makeQuestions($teacher, 'کسر', 2);

        $exam = SmartExam::create(['school_id' => $teacher->school_id, 'teacher_id' => $teacher->id, 'title' => 'آزمونِ کسر', 'grade' => 'چهارم', 'subject' => 'ریاضی', 'kind' => 'practice', 'status' => 'published', 'rules' => []]);
        foreach ($bank as $k => $b) {
            SmartExamQuestion::create(['smart_exam_id' => $exam->id, 'bank_id' => $b->id, 'type' => 'mc', 'prompt' => $b->prompt, 'choices' => $b->choices, 'points' => 1, 'sort' => $k]);
        }
        $attempt = SmartExamAttempt::create(['smart_exam_id' => $exam->id, 'student_id' => $student->id, 'attempt_no' => 1, 'token' => 'tok-1', 'token_expires_at' => now()->addHour(), 'started_at' => now(), 'status' => 'in_progress']);

        $this->actingAs($student)->post(route('student.smart.submit', $exam), [
            'token' => 'tok-1', 'duration_sec' => 60,
            'answers' => [['i' => 0, 'value' => $bank[0]->choices[0]['value']], ['i' => 1, 'value' => $bank[1]->choices[1]['value']]],
        ])->assertRedirect();

        $this->assertSame('completed', $attempt->fresh()->status);
        $r = ObjectiveReview::withoutGlobalScopes()->where('student_id', $student->id)->first();
        $this->assertNotNull($r);
        $this->assertSame([2, 1, 1], [$r->attempts, $r->correct, $r->box]);
        $this->assertSame(0, PracticeAnswer::withoutGlobalScopes()->count());
    }
}
