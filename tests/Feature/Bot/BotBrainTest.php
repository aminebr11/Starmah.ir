<?php

namespace Tests\Feature\Bot;

use App\Models\AttendanceRecord;
use App\Models\AudioSubmission;
use App\Models\AudioTask;
use App\Models\BotLink;
use App\Models\Setting;
use App\Services\Bot\BotData;
use App\Services\Bot\BotVoice;
use App\Services\Bot\TeacherBot;
use App\Support\Bot\BotApi;
use App\Support\Bot\BotConfig;
use App\Support\Bot\NameMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** 🧠 دستیار: خواندنِ داده‌ی واقعی با ابزار، نامِ اشتباه‌شنیده، صدا به متن با راهنمای نام‌ها. */
class BotBrainTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    private int $upd = 1;

    protected function setUp(): void
    {
        parent::setUp();
        BotApi::$fake = [];
    }

    protected function tearDown(): void
    {
        BotApi::$fake = null;
        BotVoice::$fake = null;
        parent::tearDown();
    }

    private function setupClass(): array
    {
        $this->seedRoles();
        BotConfig::put('enabled', '1');
        BotConfig::put('bale_token', 'TEST:token');
        $school = $this->makeSchool();
        [$teacher, $classroom, $s1] = $this->makeClass($school);
        $s1->update(['name' => 'زهرا رضایی']);
        $s2 = $this->addStudent($school, $classroom);
        $s2->update(['name' => 'سارا محمدی']);
        $s3 = $this->addStudent($school, $classroom);
        $s3->update(['name' => 'ثنا کریمی']);
        BotLink::create(['user_id' => $teacher->id, 'school_id' => $school->id, 'platform' => 'bale', 'chat_id' => '555', 'brief' => false, 'notify' => false]);

        return [$teacher, $classroom, $s1, $s2, $s3];
    }

    private function openai(array ...$responses): void
    {
        Setting::put('ai_provider', 'openai');
        Setting::put('openai_key', 'sk-test');
        $seq = Http::sequence();
        foreach ($responses as $r) {
            $seq->push($r['status'] ?? null ? $r['body'] : ['choices' => [['message' => $r]]], $r['status'] ?? 200);
        }
        Http::fake(['*' => $seq]);
    }

    private static function toolCall(string $name, array $args, string $id = 'c1'): array
    {
        return ['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => $id, 'type' => 'function', 'function' => ['name' => $name, 'arguments' => json_encode($args, JSON_UNESCAPED_UNICODE)]]]];
    }

    private function text(string $t): void
    {
        $this->postJson('/bot/bale/' . BotConfig::secret(), ['update_id' => $this->upd++, 'message' => ['message_id' => $this->upd, 'text' => $t,
            'chat' => ['id' => '555', 'type' => 'private'], 'from' => ['id' => '555']]])->assertOk();
    }

    private function lastText(): string
    {
        return (string) collect(BotApi::$fake)->pluck('params.text')->filter()->last();
    }

    public function test_question_is_answered_from_real_data_via_tools(): void
    {
        [$teacher, $classroom, $zahra, $sara, $sana] = $this->setupClass();
        $task = AudioTask::create(['school_id' => $teacher->school_id, 'teacher_id' => $teacher->id, 'classroom_id' => $classroom->id, 'kind' => 'reading',
            'title' => 'روخوانیِ درسِ ۵', 'score_type' => 'descriptive', 'is_published' => true]);
        AudioSubmission::create(['audio_task_id' => $task->id, 'student_id' => $sara->id, 'file_path' => 'x.mp3', 'file_kind' => 'audio', 'submitted_at' => now()]);

        $this->openai(
            self::toolCall('missing_submissions', ['kind' => 'reading']),
            ['role' => 'assistant', 'content' => "دو نفر هنوز روخوانی را نفرستاده‌اند:\n• زهرا رضایی\n• ثنا کریمی"],
        );
        $this->text('کی هنوز روخوانی رو نفرستاده؟');

        $this->assertStringContainsString('زهرا رضایی', $this->lastText());
        // درخواستِ دوم: نتیجه‌ی واقعیِ ابزار به مدل رسیده (نام‌ها از پایگاه‌داده)
        Http::assertSent(function (HttpRequest $r) {
            $tool = collect($r->data()['messages'] ?? [])->firstWhere('role', 'tool');

            return $tool && str_contains($tool['content'], 'زهرا رضایی') && str_contains($tool['content'], 'ثنا کریمی')
                && ! str_contains($tool['content'], '"not_sent":["سارا');
        });
        // پرامپت: قاعده‌ی «حدس نزن» و فهرستِ نام‌ها، دمای پایین، ابزارها
        Http::assertSent(fn (HttpRequest $r) => ($r->data()['temperature'] ?? null) === 0.2
            && str_contains($r->data()['messages'][0]['content'], 'هیچ عدد، نام، نمره')
            && collect($r->data()['tools'] ?? [])->pluck('function.name')->contains('grade_submissions'));
    }

    public function test_misheard_voice_name_is_matched_and_the_preview_says_so(): void
    {
        [, , $zahra] = $this->setupClass();
        $this->openai(self::toolCall('record_attendance', ['absent' => ['ظهرا'], 'late' => ['سنا']]));
        BotVoice::$fake = 'امروز ظهرا نیومده سنا هم دیر اومد';
        $this->postJson('/bot/bale/' . BotConfig::secret(), ['update_id' => $this->upd++, 'message' => ['message_id' => 9, 'voice' => ['file_id' => 'v1', 'duration' => 4000],
            'chat' => ['id' => '555', 'type' => 'private'], 'from' => ['id' => '555']]])->assertOk();

        $t = $this->lastText();
        $this->assertStringContainsString('غایب (۱): زهرا رضایی', $t);
        $this->assertStringContainsString('تأخیر (۱): ثنا کریمی', $t);
        $this->assertStringContainsString('«ظهرا» ← زهرا رضایی', $t);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->data()['messages'][array_key_last($r->data()['messages'])]['content'], '[پیامِ صوتی]')
            && str_contains($r->data()['messages'][0]['content'], 'صدا به متن'));
        $ok = collect(BotApi::$fake)->flatMap(fn ($c) => collect($c['params']['reply_markup']['inline_keyboard'] ?? [])->flatten(1))->pluck('callback_data')->filter(fn ($d) => str_starts_with((string) $d, 'ok:'))->last();
        $this->postJson('/bot/bale/' . BotConfig::secret(), ['update_id' => $this->upd++, 'callback_query' => ['id' => 'cb', 'data' => $ok, 'from' => ['id' => '555'],
            'message' => ['message_id' => 77, 'chat' => ['id' => '555', 'type' => 'private']]]])->assertOk();
        $this->assertSame('absent', AttendanceRecord::where('student_id', $zahra->id)->value('status'));
    }

    public function test_claude_tool_loop(): void
    {
        [$teacher, $classroom, $zahra] = $this->setupClass();
        \App\Models\XpEntry::create(['student_id' => $zahra->id, 'amount' => 40, 'reason' => 'x']);
        Setting::put('ai_provider', 'anthropic');
        Setting::put('anthropic_key', 'sk-ant');
        Http::fake(['*' => Http::sequence()
            ->push(['content' => [['type' => 'tool_use', 'id' => 'tu1', 'name' => 'points', 'input' => ['period' => 'week']]], 'stop_reason' => 'tool_use'])
            ->push(['content' => [['type' => 'text', 'text' => 'این هفته زهرا رضایی با ۴۰ امتیاز اول است.']], 'stop_reason' => 'end_turn'])]);
        $this->text('این هفته کی بیشترین امتیاز رو داره؟');
        $this->assertStringContainsString('۴۰ امتیاز', $this->lastText());
        Http::assertSent(function (HttpRequest $r) {
            $m = $r->data()['messages'] ?? [];
            $last = end($m);

            return ($last['content'][0]['type'] ?? '') === 'tool_result' && str_contains($last['content'][0]['content'], '"points":40');
        });
    }

    public function test_service_without_tools_falls_back_to_json_method(): void
    {
        $this->setupClass();
        $this->openai(['status' => 400, 'body' => ['error' => ['message' => 'tools are not supported for this model']]],
            ['role' => 'assistant', 'content' => '{"reply":"سلام! در خدمتم.","tool":"none","args":{}}']);
        $this->text('سلام');
        $this->assertStringContainsString('در خدمتم', $this->lastText());
    }

    public function test_long_sentences_go_to_the_assistant_and_stale_waits_expire(): void
    {
        $this->setupClass();
        // «تصحیح نشده» داخلِ یک جمله‌ی کامل نباید صفِ تصحیح را باز کند
        $this->openai(self::toolCall('grade_submissions', ['kind' => 'dictation', 'value' => 'خوب']), ['role' => 'assistant', 'content' => 'سلام! چه کمکی از دستم برمی‌آید؟']);
        $this->text('برای کسایی که املاشون تصحیح نشده خوب بزن');
        Http::assertSentCount(1);
        $this->assertStringContainsString('املا', $this->lastText());

        // دکمه‌ی «ساختِ آزمون» زده شد، ولی معلم یک ساعت بعد چیزِ دیگری نوشت
        $this->postJson('/bot/bale/' . BotConfig::secret(), ['update_id' => $this->upd++, 'callback_query' => ['id' => 'cb', 'data' => 'q:new:exam', 'from' => ['id' => '555'],
            'message' => ['message_id' => 77, 'chat' => ['id' => '555', 'type' => 'private']]]])->assertOk();
        $this->travel(61)->minutes();
        $this->text('سلام، امروز چه خبر؟');
        $this->assertStringContainsString('چه کمکی', $this->lastText());
    }

    public function test_data_tools_and_name_matching(): void
    {
        [$teacher, $classroom, $zahra, $sara] = $this->setupClass();
        AttendanceRecord::create(['school_id' => $teacher->school_id, 'classroom_id' => $classroom->id, 'student_id' => $sara->id, 'date' => now()->toDateString(), 'status' => 'absent']);
        \Illuminate\Support\Facades\Auth::setUser($teacher);
        $d = new BotData($teacher, new TeacherBot('bale'));
        $att = $d->run('attendance', []);
        $this->assertSame(['سارا محمدی'], $att['classes'][0]['absent']);
        $p = $d->run('student_profile', ['name' => 'ظهرا رزایی']);
        $this->assertSame('زهرا رضایی', $p['name']);
        $this->assertSame(['similar'], array_keys(array_intersect_key($d->run('student_profile', ['name' => 'مریم']), ['similar' => 1])));

        $this->assertSame(1.0, NameMatch::similarity('سنا', 'ثنا'));
        $this->assertNull(NameMatch::best('ولی', [1 => 'علی احمدی'])['id']);
    }

    public function test_voice_transcription_gets_the_class_names_and_filters_hallucinations(): void
    {
        Setting::put('ai_provider', 'openai');
        Setting::put('openai_key', 'sk-test');
        Http::fake(['*' => Http::sequence()
            ->push(['text' => 'زیرنویس توسط امیر'])                      // توهمِ رایج روی صدای مبهم → مدلِ بعدی
            ->push(['text' => 'زهرا رضایی امروز غایب است'])]);
        $r = BotVoice::transcribe('OggS' . str_repeat('x', 200), 'audio/ogg', 'زهرا رضایی، ثنا کریمی');
        $this->assertTrue($r['ok']);
        $this->assertSame('زهرا رضایی امروز غایب است', $r['text']);
        $this->assertSame('gpt-4o-mini-transcribe', $r['model']);
        Http::assertSent(fn (HttpRequest $req) => str_contains($req->body(), 'gpt-4o-transcribe') && str_contains($req->body(), 'ثنا کریمی'));
        $this->assertSame('', BotVoice::clean('Thanks for watching!'));
    }
}
