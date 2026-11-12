<?php

namespace Tests\Feature\Bot;

use App\Models\AudioSubmission;
use App\Models\AudioTask;
use App\Models\BotLink;
use App\Models\LiveContest;
use App\Models\Setting;
use App\Services\Bot\BotGrading;
use App\Services\Bot\BotQuiz;
use App\Services\SmartExamAiService;
use App\Support\Bot\BotApi;
use App\Support\Bot\BotConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** ✍️ تصحیح در بات (صف و گروهی)، 🏆 مسابقه‌ی زنده، 📨 پیام‌ها و ⭐ امتیازها. */
class BotGradingTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    private int $upd = 1;

    protected function setUp(): void
    {
        parent::setUp();
        BotApi::$fake = [];
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        BotApi::$fake = null;
        parent::tearDown();
    }

    private function setupClass(): array
    {
        $this->seedRoles();
        BotConfig::put('enabled', '1');
        BotConfig::put('bale_token', 'TEST:token');
        Setting::put('ai_provider', 'off');
        $school = $this->makeSchool();
        [$teacher, $classroom, $s1] = $this->makeClass($school);
        $s1->update(['name' => 'علی کریمی']);
        $s2 = $this->addStudent($school, $classroom);
        $s2->update(['name' => 'سارا محمدی']);
        $s3 = $this->addStudent($school, $classroom);
        $s3->update(['name' => 'رضا احمدی']);
        BotLink::create(['user_id' => $teacher->id, 'school_id' => $school->id, 'platform' => 'bale', 'chat_id' => '555', 'brief' => false, 'notify' => false]);

        return [$teacher, $classroom, $s1, $s2, $s3];
    }

    private function task($teacher, $classroom, string $kind = 'reading', string $type = 'descriptive'): AudioTask
    {
        return AudioTask::create(['school_id' => $teacher->school_id, 'teacher_id' => $teacher->id, 'classroom_id' => $classroom->id, 'kind' => $kind,
            'title' => $kind === 'reading' ? 'روخوانیِ درسِ ۵' : 'املای درسِ ۵', 'score_type' => $type, 'is_published' => true, 'published_at' => now()->subDay()]);
    }

    private function submit(AudioTask $t, $student, $at): AudioSubmission
    {
        $path = 'audio-submissions/' . $student->id . '-' . $t->id . ($t->kind === 'reading' ? '.mp3' : '.jpg');
        Storage::disk('public')->put($path, str_repeat('x', 300));

        return AudioSubmission::create(['audio_task_id' => $t->id, 'student_id' => $student->id, 'file_path' => $path,
            'file_kind' => $t->kind === 'reading' ? 'audio' : 'image', 'submitted_at' => $at]);
    }

    private function hook(array $message): void
    {
        $this->postJson('/bot/bale/' . BotConfig::secret(), ['update_id' => $this->upd++, 'message' => $message + [
            'message_id' => $this->upd, 'chat' => ['id' => '555', 'type' => 'private'], 'from' => ['id' => '555']]])->assertOk();
    }

    private function text(string $t): void
    {
        $this->hook(['text' => $t]);
    }

    private function press(string $data): void
    {
        $this->postJson('/bot/bale/' . BotConfig::secret(), ['update_id' => $this->upd++, 'callback_query' => ['id' => 'cb' . $this->upd, 'data' => $data, 'from' => ['id' => '555'],
            'message' => ['message_id' => 77, 'chat' => ['id' => '555', 'type' => 'private']]]])->assertOk();
    }

    private function buttons(): array
    {
        foreach (array_reverse(BotApi::$fake) as $call) {
            $out = [];
            foreach ($call['params']['reply_markup']['inline_keyboard'] ?? [] as $row) {
                foreach ($row as $b) {
                    $out[] = $b['callback_data'] ?? '';
                }
            }
            if ($out) {
                return $out;
            }
        }

        return [];
    }

    private function button(string $prefix): ?string
    {
        foreach ($this->buttons() as $b) {
            if (str_starts_with($b, $prefix)) {
                return $b;
            }
        }

        return null;
    }

    private function texts(): string
    {
        return collect(BotApi::$fake)->pluck('params.text')->filter()->implode("\n");
    }

    public function test_bulk_grading_from_one_sentence_only_touches_tonights_ungraded_readings(): void
    {
        [$teacher, $classroom, $ali, $sara, $reza] = $this->setupClass();
        $this->travelTo(now()->setTime(21, 0));
        $read = $this->task($teacher, $classroom);
        $a = $this->submit($read, $ali, now()->setTime(19, 10));
        $b = $this->submit($read, $sara, now()->setTime(20, 30));
        $c = $this->submit($read, $reza, now()->subDay()->setTime(20, 0));     // دیروز
        $dict = $this->task($teacher, $classroom, 'dictation');
        $d = $this->submit($dict, $ali, now()->setTime(19, 0));                 // املا، نه روخوانی

        $this->text('برای کسایی که امشب روخوانی فرستادن خیلی خوب بزن');
        $t = $this->texts();
        $this->assertStringContainsString('علی کریمی', $t);
        $this->assertStringContainsString('سارا محمدی', $t);
        $this->assertStringNotContainsString('رضا احمدی', $t);
        $this->assertNull($a->fresh()->graded_at);                             // پیش از تأیید چیزی ثبت نمی‌شود

        $this->press($this->button('g:ok:'));
        $this->assertSame(['خیلی خوب', 'خیلی خوب', null, null], [$a->fresh()->grade, $b->fresh()->grade, $c->fresh()->grade, $d->fresh()->grade]);
        // مثلِ سایت: دفترِ نمره و اعلانِ دانش‌آموز
        $this->assertSame('خیلی خوب', \App\Models\Grade::where('student_id', $ali->id)->value('text'));
        $this->assertTrue(\App\Models\Announcement::whereHas('recipients', fn ($q) => $q->where('users.id', $sara->id))->where('title', 'like', '%روخوانی%')->exists());

        // برگرداندن
        $this->press($this->button('un:'));
        $this->assertNull($a->fresh()->graded_at);
        $this->assertNull($b->fresh()->grade);
        $this->assertSame(0, \App\Models\Grade::where('student_id', $ali->id)->whereNotNull('text')->count());
    }

    public function test_grading_queue_sends_the_students_file_and_grades_with_buttons(): void
    {
        [$teacher, $classroom, $ali, $sara] = $this->setupClass();
        $dict = $this->task($teacher, $classroom, 'dictation');
        $a = $this->submit($dict, $ali, now()->subHours(3));
        $b = $this->submit($dict, $sara, now()->subHours(2));

        $this->press('g:home');
        $this->assertStringContainsString('املا: ۲', $this->texts());
        $this->press('g:q:d');
        $photo = collect(BotApi::$fake)->firstWhere('method', 'sendPhoto');
        $this->assertNotNull($photo);
        $this->assertSame(300, $photo['params']['_bytes']);
        $this->assertStringContainsString('علی کریمی', $photo['params']['caption']);
        $this->press('g:a:' . $a->id . ':1');
        $this->assertSame('خوب', $a->fresh()->grade);
        // کارِ بعدی (سارا) خودش آمد؛ این بار با توضیح و تعدادِ غلط
        $this->press('g:t:d:' . $b->id);
        $this->text('قابل قبول، ۳ غلط، در نوشتنِ «ض» دقت کن');
        $b->refresh();
        $this->assertSame(['قابل قبول', 3], [$b->grade, $b->mistakes]);
        $this->assertStringContainsString('دقت کن', $b->feedback);
        $this->assertStringContainsString('کارِ دیگری برای تصحیح نمانده', $this->texts());
    }

    public function test_ai_understands_a_detailed_grading_command(): void
    {
        [$teacher, $classroom, $ali, $sara, $reza] = $this->setupClass();
        $read = $this->task($teacher, $classroom, 'reading', 'numeric');
        $subs = collect([$ali, $sara, $reza])->map(fn ($st) => $this->submit($read, $st, now()->subHour()));
        Setting::put('ai_provider', 'openai');
        Setting::put('openai_key', 'sk-test');
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['choices' => [['message' => ['content' =>
            '{"reply":"باشه، فهرست را ببین.","tool":"grade_work","args":{"kind":"reading","date":null,"after_hour":null,"days":null,"task":"درس ۵","names":[],"except":["سارا"],"regrade":false,"value":19,"feedback":"آفرین"}}']]]])]);
        $this->text('روخوانی درس ۵ همه رو ۱۹ بده جز سارا، بنویس آفرین');
        $this->assertStringNotContainsString('سارا محمدی', collect(BotApi::$fake)->pluck('params.text')->filter()->last());
        $this->press($this->button('g:ok:'));
        $this->assertSame([19.0, null, 19.0], $subs->map(fn ($x) => $x->fresh()->score)->all());
        $this->assertSame('آفرین', $subs[0]->fresh()->feedback);
    }

    public function test_parse_grade_and_bulk_phrases(): void
    {
        $this->assertSame(['خوب', 3, 'دقت کن'], BotGrading::parseGrade('خوب، ۳ غلط، دقت کن'));
        $this->assertSame([18.0, null, 'آفرین'], BotGrading::parseGrade('۱۸ آفرین'));
        $this->assertSame('خیلی خوب', BotGrading::parseGrade('خیلی خوب بزن')[0]);
        $p = BotGrading::looksBulk('برای اونایی که دیروز املا فرستادن به جز سارا قابل قبول بده');
        $this->assertSame(['dictation', 'قابل قبول', now()->subDay()->toDateString(), ['سارا']], [$p['kind'], $p['value'], $p['date'], $p['except']]);
        $this->assertNull(BotGrading::looksBulk('سلام خوبی؟'));
    }

    public function test_live_contest_from_the_bot_with_schedule_start_and_results(): void
    {
        [$teacher] = $this->setupClass();
        Setting::put('ai_provider', 'openai');
        Setting::put('openai_key', 'sk-test');
        $mock = \Mockery::mock(SmartExamAiService::class);
        $mock->shouldReceive('generate')->andReturnUsing(fn ($o) => ['ok' => true, 'questions' => collect(range(1, $o['count']))->map(fn ($i) => [
            'type' => 'mc', 'prompt' => "سؤالِ {$i} کسرها", 'choices' => [['value' => 'یک', 'correct' => false], ['value' => 'دو', 'correct' => true], ['value' => 'سه', 'correct' => false]],
        ])->all()]);
        $this->app->instance(SmartExamAiService::class, $mock);

        $this->press('q:new:live');
        $this->text('ریاضی — کسرها');
        $this->assertNull($this->button('q:typ:desc'));      // مسابقه فقط گزینه‌ای
        $this->press('q:go');
        $this->press('q:rul');
        $this->press('q:ru:seconds');                         // ۲۰ → ۳۰ ثانیه
        $this->press('q:ltime');
        $this->text('۸ شب');
        $this->press('q:ru:end');                             // ۰ → ۱۰ دقیقه
        $this->press('q:pub:1');

        $c = LiveContest::where('teacher_id', $teacher->id)->firstOrFail();
        $this->assertSame(['auto', 30, 8, 10], [$c->mode, $c->seconds, count($c->questions), (int) $c->duration_min]);
        $this->assertSame('20:00', $c->starts_at->format('H:i'));
        $this->assertSame(1, $c->questions[0]['answer']);
        $this->assertStringContainsString('کدِ ورود', $this->texts());

        $this->press('q:lvs:' . $c->id);
        $this->assertSame('question', $c->fresh()->phase);
        $this->press('q:lvr:' . $c->id);
        $this->assertStringContainsString('در حالِ اجرا', $this->texts());
        $this->press('q:lve:' . $c->id);
        $this->assertSame('end', $c->fresh()->phase);
        $this->assertSame('20:30', BotQuiz::parseTime('ساعت ۸:۳۰ شب'));
    }

    public function test_messages_and_points(): void
    {
        [$teacher, $classroom, $ali] = $this->setupClass();
        \App\Models\Message::create(['school_id' => $teacher->school_id, 'sender_id' => $ali->id, 'recipient_id' => $teacher->id, 'kind' => 'message', 'body' => 'خانم، تمرینِ صفحه‌ی ۲۰ را باید بنویسیم؟']);
        \App\Models\XpEntry::create(['student_id' => $ali->id, 'amount' => 25, 'reason' => 'تست']);

        $this->press('m:msg');
        $this->assertStringContainsString('صفحه‌ی ۲۰', $this->texts());
        $this->press('msg:r:' . $ali->id);
        $this->text('بله عزیزم، فقط تمرینِ ۱ و ۲');
        $this->assertTrue(\App\Models\Message::where('sender_id', $teacher->id)->where('recipient_id', $ali->id)->where('body', 'like', '%تمرینِ ۱%')->exists());
        $this->assertNotNull(\App\Models\Message::where('sender_id', $ali->id)->value('read_at'));

        $this->press('r:xp');
        $this->assertStringContainsString('علی کریمی — ۲۵', $this->texts());
    }
}
