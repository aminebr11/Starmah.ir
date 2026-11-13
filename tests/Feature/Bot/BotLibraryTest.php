<?php

namespace Tests\Feature\Bot;

use App\Models\BotLink;
use App\Models\EduGame;
use App\Models\EduGameQuestion;
use App\Models\LiveContest;
use App\Models\Setting;
use App\Models\SmartExam;
use App\Models\SmartExamQuestion;
use App\Services\Bot\BotQuiz;
use App\Services\Bot\TeacherBot;
use App\Support\Bot\BotApi;
use App\Support\Bot\BotConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** 📚 «کارهای من» در بات: فهرست، سؤال‌ها، وضعیت، حذف و ساختن از سؤال‌های قبلی (دکمه‌ای و با دستیار). */
class BotLibraryTest extends TestCase
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
        parent::tearDown();
    }

    private function setupTeacher(): array
    {
        $this->seedRoles();
        BotConfig::put('enabled', '1');
        BotConfig::put('bale_token', 'TEST:token');
        Setting::put('smart_lab_enabled', true);
        Setting::put('smart_scope', 'all');
        $school = $this->makeSchool();
        [$teacher, $classroom] = $this->makeClass($school);
        BotLink::create(['user_id' => $teacher->id, 'school_id' => $school->id, 'platform' => 'bale', 'chat_id' => '555', 'brief' => false, 'notify' => false]);

        return [$teacher, $classroom];
    }

    private function game($teacher, string $title, array $prompts): EduGame
    {
        $g = EduGame::create(['school_id' => $teacher->school_id, 'teacher_id' => $teacher->id, 'template_key' => 'board-space', 'title' => $title, 'status' => 'draft']);
        foreach ($prompts as $i => $p) {
            EduGameQuestion::create(['edu_game_id' => $g->id, 'type' => $i === 2 ? 'short' : 'mc', 'prompt' => $p, 'points' => 10, 'sort' => $i,
                'choices' => $i === 2 ? [['value' => 'تهران', 'correct' => true]] : [['value' => 'یک', 'correct' => false], ['value' => 'دو', 'correct' => true], ['value' => 'سه', 'correct' => false]]]);
        }

        return $g;
    }

    private function exam($teacher): SmartExam
    {
        $e = SmartExam::create(['school_id' => $teacher->school_id, 'teacher_id' => $teacher->id, 'title' => 'آزمونِ کسرها', 'kind' => 'practice', 'status' => 'draft', 'rules' => []]);
        SmartExamQuestion::create(['smart_exam_id' => $e->id, 'type' => 'tf', 'prompt' => 'نیم برابر با دو چهارم است.', 'choices' => [['value' => 'درست', 'correct' => true], ['value' => 'نادرست', 'correct' => false]], 'points' => 1, 'sort' => 0]);
        SmartExamQuestion::create(['smart_exam_id' => $e->id, 'type' => 'desc', 'prompt' => 'کسر را با یک مثال توضیح بده.', 'answer' => ['بخشی از یک کل'], 'points' => 1, 'sort' => 1]);
        // تکراری با یکی از سؤال‌های بازی
        SmartExamQuestion::create(['smart_exam_id' => $e->id, 'type' => 'mc', 'prompt' => 'بازی ۱ — سؤالِ اول', 'choices' => [['value' => 'یک', 'correct' => false], ['value' => 'دو', 'correct' => true]], 'points' => 1, 'sort' => 2]);

        return $e;
    }

    private function press(string $data): void
    {
        $this->postJson('/bot/bale/' . BotConfig::secret(), ['update_id' => $this->upd++, 'callback_query' => ['id' => 'cb' . $this->upd, 'data' => $data, 'from' => ['id' => '555'],
            'message' => ['message_id' => 77, 'chat' => ['id' => '555', 'type' => 'private']]]])->assertOk();
    }

    private function text(string $t): void
    {
        $this->postJson('/bot/bale/' . BotConfig::secret(), ['update_id' => $this->upd++, 'message' => ['message_id' => $this->upd, 'text' => $t,
            'chat' => ['id' => '555', 'type' => 'private'], 'from' => ['id' => '555']]])->assertOk();
    }

    private function texts(): string
    {
        return collect(BotApi::$fake)->pluck('params.text')->filter()->implode("\n");
    }

    private function has(string $data): bool
    {
        return collect(BotApi::$fake)->flatMap(fn ($c) => collect($c['params']['reply_markup']['inline_keyboard'] ?? [])->flatten(1))->pluck('callback_data')->contains($data);
    }

    private function quiz(): array
    {
        return (new BotQuiz(new TeacherBot('bale'), BotApi::for('bale'), 'bale', '555'))->get() ?? [];
    }

    public function test_browse_view_publish_delete_and_build_a_live_contest_from_a_game(): void
    {
        [$teacher] = $this->setupTeacher();
        $g1 = $this->game($teacher, 'بازی ۱ کسرها', ['بازی ۱ — سؤالِ اول', 'بازی ۱ — سؤالِ دوم', 'پایتختِ ایران کجاست؟']);
        $g2 = $this->game($teacher, 'بازی ۲ ضرب', ['ضرب — سؤالِ اول']);

        $this->press('m:main');
        $this->assertTrue($this->has('lb:home'));
        $this->press('lb:home');
        $this->assertTrue($this->has('lb:l:game:0'));
        $this->press('lb:l:game:0');
        $this->assertStringContainsString('بازی ۲ ضرب', $this->texts());
        $this->press('lb:v:game:' . $g1->id);
        $this->press('lb:q:game:' . $g1->id);
        $this->assertStringContainsString('بازی ۱ — سؤالِ دوم', $this->texts());
        $this->assertStringContainsString('✔ تهران', $this->texts());

        $this->press("lb:s:game:{$g1->id}:published");
        $this->assertSame('published', $g1->fresh()->status);

        // مسابقه‌ی زنده از همین بازی: پاسخ‌کوتاه (غیرِ گزینه‌ای) کنار می‌رود
        $this->press("lb:mk:game:{$g1->id}:live");
        $s = $this->quiz();
        $this->assertSame('live', $s['kind']);
        $this->assertSame(['بازی ۱ — سؤالِ اول', 'بازی ۱ — سؤالِ دوم'], array_column($s['qs'], 'prompt'));
        $this->assertStringContainsString('مسابقه فقط گزینه‌ای است', $this->texts());
        $this->press('q:pub:1');
        $c = LiveContest::where('teacher_id', $teacher->id)->firstOrFail();
        $this->assertSame(2, count($c->questions));
        $this->assertSame(1, $c->questions[0]['answer']);

        // حذف با تأیید
        $this->press('lb:d:game:' . $g2->id);
        $this->assertNotNull(EduGame::find($g2->id));
        $this->press('lb:dy:game:' . $g2->id);
        $this->assertNull(EduGame::find($g2->id));
    }

    public function test_basket_of_games_and_exam_becomes_one_exam_without_duplicates(): void
    {
        [$teacher] = $this->setupTeacher();
        $g1 = $this->game($teacher, 'بازی ۱ کسرها', ['بازی ۱ — سؤالِ اول', 'بازی ۱ — سؤالِ دوم', 'پایتختِ ایران کجاست؟']);
        $e = $this->exam($teacher);
        $this->press('lb:pk:game:' . $g1->id);
        $this->press('lb:pk:exam:' . $e->id);
        $this->press('lb:basket');
        $this->assertStringContainsString('آزمونِ کسرها', $this->texts());
        $this->press('lb:mkp:exam');
        $qs = $this->quiz()['qs'];
        $this->assertCount(5, $qs);                        // ۳ + ۳ − ۱ تکراری
        $this->assertSame(['mc', 'mc', 'blank', 'tf', 'desc'], array_column($qs, 'type'));
        $this->press('q:pub:0');
        $new = SmartExam::where('teacher_id', $teacher->id)->latest('id')->firstOrFail();
        $this->assertNotSame($e->id, $new->id);
        $this->assertSame(5, $new->questions()->count());
        $this->assertSame('draft', $new->status);
    }

    public function test_assistant_reads_last_games_questions_and_builds_from_them(): void
    {
        [$teacher] = $this->setupTeacher();
        $this->game($teacher, 'بازی ۱', ['اولی — سؤالِ الف']);
        $this->game($teacher, 'بازی ۲', ['دومی — سؤالِ ب']);
        $this->game($teacher, 'بازی ۳', ['سومی — سؤالِ ج']);
        $this->game($teacher, 'بازی ۴', ['چهارمی — سؤالِ د']);
        Setting::put('ai_provider', 'openai');
        Setting::put('openai_key', 'sk-test');
        $call = fn ($n, $a) => ['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => 'c1', 'type' => 'function',
            'function' => ['name' => $n, 'arguments' => json_encode($a, JSON_UNESCAPED_UNICODE)]]]]]]];
        Http::fake(['*' => Http::sequence()
            ->push($call('item_questions', ['type' => 'game', 'last' => 3]))
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => "سؤال‌های سه بازیِ آخر:\n• دومی — سؤالِ ب\n• سومی — سؤالِ ج\n• چهارمی — سؤالِ د"]]]])
            ->push($call('build_from_items', ['kind' => 'live', 'from_type' => 'game', 'last_n' => 3]))]);

        $this->text('سؤال‌های سه بازیِ آخر رو بده');
        Http::assertSent(function (HttpRequest $r) {
            $tool = collect($r->data()['messages'] ?? [])->firstWhere('role', 'tool');

            return $tool && str_contains($tool['content'], 'چهارمی — سؤالِ د') && str_contains($tool['content'], 'دومی — سؤالِ ب') && ! str_contains($tool['content'], 'اولی');
        });
        $this->assertStringContainsString('سه بازیِ آخر', $this->texts());

        $this->text('با همین‌ها یک مسابقه‌ی زنده بساز');
        $s = $this->quiz();
        $this->assertSame('live', $s['kind']);
        $this->assertEqualsCanonicalizing(['دومی — سؤالِ ب', 'سومی — سؤالِ ج', 'چهارمی — سؤالِ د'], array_column($s['qs'], 'prompt'));
    }
}
