<?php

namespace Tests\Feature\Learning;

use App\Models\CurriculumChapter;
use App\Models\Remediation;
use App\Models\Setting;
use App\Models\SmartExam;
use App\Models\SmartExamAnswer;
use App\Models\SmartExamAttempt;
use App\Models\SmartExamQuestion;
use App\Services\RemediationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/**
 * «مرورِ اشتباه‌ها» از همه‌ی منابع: نمره‌ی ضعیفِ معلم، مرورِ دستیِ معلم، اشتباه‌های گذشته؛
 * و صدای فارسیِ سرور برای «بخوان برایم».
 */
class ReviewFromMistakesTest extends TestCase
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

    public function test_weak_grade_creates_a_personal_review_from_that_chapter(): void
    {
        [$teacher, , $student, $ch, $bank] = $this->setUpSchool();
        $this->actingAs($teacher)->post(route('teacher.gradebook.activities'), [
            'title' => 'پرسشِ کسر', 'score_type' => 'numeric', 'lesson' => 'ریاضی', 'chapter_id' => $ch->id, 'max' => 20,
            'grades' => [['student_id' => $student->id, 'score' => 5]],
        ])->assertRedirect();

        $rem = Remediation::where('student_id', $student->id)->firstOrFail();
        $this->assertSame(['grade', 15, 7], [$rem->source, $rem->lost_xp, $rem->cap_xp]); // XPِ نمره ۵ از ۲۰، سقف = نصفِ ۱۵

        $props = $this->actingAs($student)->get(route('missions.remedial.play'))->viewData('page')['props'];
        $this->assertSame('مرورِ اشتباه‌های من', $props['mission']['title']);
        $this->assertGreaterThanOrEqual(3, count($props['questions']), 'بدونِ سؤالِ اصلی → سؤال‌های بیشتری از همان فصل');
        $chapterPrompts = collect($bank)->pluck('prompt')->all();
        foreach ($props['questions'] as $q) {
            $this->assertContains($q['prompt'], $chapterPrompts);
        }
    }

    public function test_teacher_can_send_a_review_to_chosen_students(): void
    {
        [$teacher, $classroom, $student, $ch] = $this->setUpSchool();
        $other = $this->addStudent($teacher->school, $classroom);

        $this->actingAs($teacher)->post(route('teacher.remediations.store'), [
            'student_ids' => [$student->id], 'subject' => 'ریاضی', 'chapter_id' => $ch->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $rem = Remediation::where('student_id', $student->id)->firstOrFail();
        $this->assertSame(['teacher', 6, 'فصل ۲ — کسرها'], [$rem->source, $rem->cap_xp, $rem->objective_label]);
        $this->assertSame(0, Remediation::where('student_id', $other->id)->count());

        // دوباره همان فصل → تکراری ساخته نمی‌شود
        $this->actingAs($teacher)->post(route('teacher.remediations.store'), ['student_ids' => [$student->id], 'subject' => 'ریاضی', 'chapter_id' => $ch->id]);
        $this->assertSame(1, Remediation::where('student_id', $student->id)->count());

        // بستن
        $this->actingAs($teacher)->delete(route('teacher.remediations.destroy', $rem))->assertRedirect();
        $this->assertSame('closed', $rem->fresh()->status);
    }

    public function test_teacher_is_told_when_the_chapter_has_no_questions(): void
    {
        [$teacher, , $student] = $this->setUpSchool();
        $empty = CurriculumChapter::create(['grade' => 'چهارم', 'subject' => 'ریاضی', 'number' => 9, 'title' => 'فصلِ خالی', 'is_active' => true]);
        $this->actingAs($teacher)->post(route('teacher.remediations.store'), ['student_ids' => [$student->id], 'subject' => 'ریاضی', 'chapter_id' => $empty->id])
            ->assertSessionHasErrors('chapter_id');
        $this->assertSame(0, Remediation::count());
    }

    public function test_past_mistakes_are_turned_into_reviews_once(): void
    {
        [$teacher, , $student, $ch, $bank] = $this->setUpSchool();
        $exam = SmartExam::create(['school_id' => $teacher->school_id, 'teacher_id' => $teacher->id, 'title' => 'آزمونِ قدیمی', 'grade' => 'چهارم',
            'subject' => 'ریاضی', 'chapter_id' => $ch->id, 'kind' => 'practice', 'status' => 'published', 'rules' => []]);
        $q = [];
        foreach ([$bank[0], $bank[1]] as $k => $b) {
            $q[] = SmartExamQuestion::create(['smart_exam_id' => $exam->id, 'bank_id' => $b->id, 'type' => 'mc', 'prompt' => $b->prompt, 'choices' => $b->choices, 'points' => 1, 'sort' => $k]);
        }
        $att = SmartExamAttempt::create(['smart_exam_id' => $exam->id, 'student_id' => $student->id, 'attempt_no' => 1, 'token' => 't', 'token_expires_at' => now(),
            'started_at' => now()->subDays(10), 'finished_at' => now()->subDays(10), 'status' => 'completed', 'rewarded' => true, 'score' => 1, 'max_score' => 2]);
        SmartExamAnswer::create(['attempt_id' => $att->id, 'question_id' => $q[0]->id, 'q_index' => 0, 'value' => ['value' => 'x'], 'correct' => true, 'awarded' => 1]);
        SmartExamAnswer::create(['attempt_id' => $att->id, 'question_id' => $q[1]->id, 'q_index' => 1, 'value' => ['value' => 'y'], 'correct' => false, 'awarded' => 0]);

        Setting::put('remediation_backfill', ''); // مایگریشن روی پایگاهِ خالیِ تست «تمام» علامت زده است
        // سقفِ زمانِ صفر: کار نیمه‌کاره می‌ماند و جایش ذخیره می‌شود
        $this->assertFalse(app(RemediationService::class)->backfill(60, -1)['done']);
        $r = app(RemediationService::class)->backfill(60);
        $this->assertSame(['students' => 1, 'items' => 1, 'done' => true], $r);
        $rem = Remediation::firstOrFail();
        $this->assertSame([$bank[1]->id, 50, 25], [$rem->bank_id, $rem->lost_xp, $rem->cap_xp]);
        $this->assertDatabaseHas('announcement_recipients', ['user_id' => $student->id]);
        // دوباره اجرا → تکراری نمی‌سازد
        $this->assertSame(0, app(RemediationService::class)->backfill(60)['items']);
    }

    public function test_server_persian_voice_is_used_when_available_and_cached(): void
    {
        [, , $student] = $this->setUpSchool();
        Storage::fake('public');
        Setting::put('openai_key', 'sk-test');
        Http::fake(['api.openai.com/v1/audio/speech' => Http::response(str_repeat('ID3', 200), 200, ['Content-Type' => 'audio/mpeg'])]);

        $this->actingAs($student)->get(route('missions'))->assertOk();
        $this->assertTrue($this->actingAs($student)->get(route('missions'))->viewData('page')['props']['tts']);

        $url = $this->actingAs($student)->postJson(route('speech'), ['text' => 'سلام 🌟 بچه‌ها'])->assertOk()->json('url');
        $this->assertNotEmpty($url);
        $this->actingAs($student)->postJson(route('speech'), ['text' => 'سلام 🌟 بچه‌ها'])->assertOk();
        Http::assertSentCount(1); // بارِ دوم از فایلِ ذخیره‌شده

        // بدونِ کلید → در دسترس نیست (دکمه پنهان می‌شود، نه پیامِ «صدای فارسی نیست»)
        Setting::put('openai_key', '');
        $this->actingAs($student)->postJson(route('speech'), ['text' => 'متنِ دیگر'])->assertStatus(503);
    }

    public function test_ai_center_survives_usage_of_deleted_users(): void
    {
        $this->seedRoles();
        $admin = $this->makeUser(null, \App\Support\Roles::SUPER_ADMIN);
        \Illuminate\Support\Facades\DB::table('ai_usages')->insert([
            ['user_id' => 999999, 'school_id' => null, 'teacher_id' => 888888, 'role' => 'teacher', 'provider' => 'openai', 'model' => 'gpt-4o-mini',
                'feature' => 'questions', 'input_tokens' => 100, 'output_tokens' => 50, 'ok' => 1, 'status' => 200, 'ms' => 900, 'created_at' => now()],
        ]);
        $page = $this->actingAs($admin)->get(route('admin.ai'))->assertOk();
        $this->assertSame('کاربرِ حذف‌شده', $page->viewData('page')['props']['usage']['topUsers'][0]['name']);
    }
}
