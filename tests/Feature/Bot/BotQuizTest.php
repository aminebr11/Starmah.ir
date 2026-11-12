<?php

namespace Tests\Feature\Bot;

use App\Models\BotLink;
use App\Models\EduGame;
use App\Models\Setting;
use App\Models\SmartExam;
use App\Services\Bot\BotQuiz;
use App\Services\SmartExamAiService;
use App\Support\Bot\BotApi;
use App\Support\Bot\BotConfig;
use App\Support\Bot\BotFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** 🧪 استودیوی سؤال در بات: از عکس/PDF یا موضوع → بازبینی (ویرایش، حذف، جایگزین) → قوانین و تم → انتشار. */
class BotQuizTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    private int $upd = 1;

    /** @var array<int,array> فراخوانی‌های سرویسِ سؤال‌ساز */
    private array $aiCalls = [];

    protected function setUp(): void
    {
        parent::setUp();
        BotApi::$fake = [];
        BotFile::$fake = ['ph1' => "\xFF\xD8" . str_repeat('x', 200), 'ph2' => "\xFF\xD8" . str_repeat('y', 200), 'pdf1' => '%PDF-1.4' . str_repeat('z', 200)];
    }

    protected function tearDown(): void
    {
        BotApi::$fake = null;
        BotFile::$fake = null;
        parent::tearDown();
    }

    private function setupTeacher(): array
    {
        $this->seedRoles();
        BotConfig::put('enabled', '1');
        BotConfig::put('bale_token', 'TEST:token');
        Setting::put('ai_provider', 'openai');
        Setting::put('openai_key', 'sk-test');
        Setting::put('smart_lab_enabled', true);
        Setting::put('smart_scope', 'all');
        \App\Models\GameTemplate::query()->exists() || $this->seed(\Database\Seeders\GameTemplateSeeder::class);
        $school = $this->makeSchool();
        [$teacher, $classroom] = $this->makeClass($school);
        BotLink::create(['user_id' => $teacher->id, 'school_id' => $school->id, 'platform' => 'bale', 'chat_id' => '555', 'brief' => true, 'notify' => true]);

        return [$teacher, $classroom];
    }

    /** سرویسِ سؤال‌ساز: هر بار n سؤالِ تازه برمی‌گرداند و ورودی را نگه می‌دارد. */
    private function fakeAi(): void
    {
        $mock = \Mockery::mock(SmartExamAiService::class);
        $mock->shouldReceive('generate')->andReturnUsing(function (array $o) {
            $this->aiCalls[] = $o;
            $k = count($this->aiCalls);
            $n = ($o['source_mode'] ?? '') === 'same' && ! empty($o['sources']) ? 3 : $o['count'];

            return ['ok' => true, 'questions' => collect(range(1, $n))->map(fn ($i) => [
                'type' => 'mc', 'prompt' => "سؤالِ {$k}-{$i}: حاصلِ {$i} + ۱ چند است؟",
                'choices' => [['value' => 'یک', 'correct' => true], ['value' => 'دو', 'correct' => false], ['value' => 'سه', 'correct' => false]],
                'explanation' => 'جمعِ ساده', 'difficulty' => 'easy', 'topic' => 'جمع',
            ])->all(), 'message' => null];
        });
        $this->app->instance(SmartExamAiService::class, $mock);
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
        $out = [];
        foreach (array_reverse(BotApi::$fake) as $call) {
            foreach ($call['params']['reply_markup']['inline_keyboard'] ?? [] as $row) {
                foreach ($row as $b) {
                    $out[] = [$b['text'], $b['callback_data'] ?? ''];
                }
            }
            if ($out) {
                return $out;
            }
        }

        return [];
    }

    private function has(string $data): bool
    {
        return in_array($data, array_column($this->buttons(), 1), true);
    }

    private function lastText(): string
    {
        foreach (array_reverse(BotApi::$fake) as $call) {
            if (isset($call['params']['text'])) {
                return (string) $call['params']['text'];
            }
        }

        return '';
    }

    private function quizState(): array
    {
        return (new BotQuiz(new \App\Services\Bot\TeacherBot('bale'), BotApi::for('bale'), 'bale', '555'))->get() ?? [];
    }

    public function test_photos_to_exact_questions_review_edit_and_publish_exam(): void
    {
        [$teacher, $classroom] = $this->setupTeacher();
        $this->fakeAi();

        // دو عکس در یک آلبوم: فقط یک بار جواب
        $this->hook(['photo' => [['file_id' => 'small', 'width' => 90, 'height' => 90], ['file_id' => 'ph1', 'width' => 1280, 'height' => 1600]], 'media_group_id' => 'g1']);
        $this->hook(['photo' => [['file_id' => 'ph2', 'width' => 1280, 'height' => 1600]], 'media_group_id' => 'g1']);
        $this->assertSame(['ph1', 'ph2'], array_column($this->quizState()['src'], 'fid'));
        $this->assertTrue($this->has('q:mode:same'));
        $this->assertSame(1, collect(BotApi::$fake)->filter(fn ($c) => str_contains((string) ($c['params']['text'] ?? ''), 'با آن چه کنم'))->count());

        $this->press('q:mode:same');               // یک کلاس دارد → مستقیم «آزمون یا بازی؟»
        $this->assertTrue($this->has('q:kind:exam'));
        $this->press('q:kind:exam');               // «همین سؤال‌ها» → بی‌درنگ ساخته می‌شود

        $o = $this->aiCalls[0];
        $this->assertSame('same', $o['source_mode']);
        $this->assertSame(['mc', 'tf', 'blank', 'desc'], $o['types']);
        $this->assertCount(2, $o['sources']);
        $this->assertSame('image/jpeg', $o['sources'][0]['mime']);
        $this->assertSame($classroom->grade, $o['grade']);
        $this->assertCount(3, $this->quizState()['qs']);
        $this->assertTrue($this->has('q:v:2') && $this->has('q:pub:1') && $this->has('q:rul'));

        // دیدن، عوض کردنِ گزینه‌ی درست، ویرایشِ متنی، حذف و جایگزین
        $this->press('q:v:0');
        $this->assertTrue($this->has('q:k:0:1'));
        $this->press('q:k:0:1');
        $this->assertSame([false, true, false], array_column($this->quizState()['qs'][0]['choices'], 'correct'));

        $this->press('q:e:1');
        $this->assertStringContainsString('الف) یک ✅', $this->lastText());
        $this->text("حاصلِ ۵ + ۵ چند است؟\nالف) نه\nب) ده ✅\nج) یازده\nد) دوازده");
        $q1 = $this->quizState()['qs'][1];
        $this->assertSame('حاصلِ ۵ + ۵ چند است؟', $q1['prompt']);
        $this->assertCount(4, $q1['choices']);
        $this->assertTrue($q1['choices'][1]['correct']);

        $this->press('q:d:2');
        $this->assertCount(2, $this->quizState()['qs']);
        $this->press('q:r:0');
        $this->assertSame(1, $this->aiCalls[1]['count']);
        $this->assertStringStartsWith('سؤالِ 2-1', $this->quizState()['qs'][0]['prompt']);

        // قوانین: مدت ۲۰ → ۳۰ و نمایشِ پاسخ خاموش؛ عنوان
        $this->press('q:rul');
        $this->press('q:ru:duration');
        $this->press('q:ru:show_answer');
        $this->press('q:ttl');
        $this->text('آزمونِ جمعِ فصل ۱');

        $this->press('q:pub:1');
        $exam = SmartExam::where('teacher_id', $teacher->id)->firstOrFail();
        $this->assertSame('published', $exam->status);
        $this->assertSame('آزمونِ جمعِ فصل ۱', $exam->title);
        $this->assertSame(30, (int) $exam->rules['duration']);
        $this->assertFalse((bool) $exam->rules['show_answer']);
        $this->assertSame(2, $exam->questions()->count());
        $this->assertSame([$classroom->id], $exam->targets()->pluck('classroom_id')->all());
        $this->assertSame([], $this->quizState());

        // برگرداندن
        $this->press(collect($this->buttons())->first(fn ($b) => str_starts_with($b[1], 'un:'))[1]);
        $this->assertSame(0, SmartExam::count());
    }

    public function test_pdf_with_caption_builds_similar_game_with_settings_theme_and_template(): void
    {
        [$teacher] = $this->setupTeacher();
        $this->fakeAi();
        $theme = \App\Models\Theme::create(['key' => 'jungle', 'name' => 'جنگل', 'emoji' => '🌳', 'skin' => [], 'narrative' => [], 'is_active' => true, 'sort' => 1]);

        $this->hook(['document' => ['file_id' => 'pdf1', 'mime_type' => 'application/pdf', 'file_name' => 'sheet.pdf', 'file_size' => 5000],
            'caption' => 'یک بازی ۶ سوالی مشابه این برگه بساز']);
        $s = $this->quizState();
        $this->assertSame('similar', $s['mode']);
        $this->assertSame('game', $s['kind']);
        $this->assertSame(6, $s['count']);
        // تنظیمات پیش از ساخت
        $this->assertTrue($this->has('q:go'));
        $this->assertFalse($this->has('q:typ:desc'));   // بازی تشریحی ندارد
        $this->press('q:cnt:+');
        $this->press('q:dif:hard');
        $this->press('q:typ:tf');                        // خاموش
        $this->press('q:thm');
        $this->press('q:th:' . $theme->id);
        $this->press('q:go');

        $o = $this->aiCalls[0];
        $this->assertEquals(['count' => 7, 'difficulty' => 'hard', 'types' => ['mc'], 'flavor' => 'جنگل', 'audience' => 'game', 'source_mode' => 'similar'],
            array_intersect_key($o, array_flip(['count', 'difficulty', 'types', 'flavor', 'audience', 'source_mode'])));
        $this->assertSame('application/pdf', $o['sources'][0]['mime']);

        $this->press('q:more');
        $this->assertCount(12, $this->quizState()['qs']);
        $this->press('q:tpls');
        $tpl = \App\Models\GameTemplate::where('is_active', true)->orderByDesc('id')->value('key');
        $this->assertTrue($this->has('q:tp:' . $tpl));
        $this->press('q:tp:' . $tpl);
        $this->press('q:rul');
        $this->press('q:ru:lives');
        $this->press('q:pub:0');

        $g = EduGame::where('teacher_id', $teacher->id)->firstOrFail();
        $this->assertSame('draft', $g->status);
        $this->assertSame($tpl, $g->template_key);
        $this->assertSame($theme->id, (int) $g->theme_id);
        $this->assertSame(4, (int) $g->rules['lives']);
        $this->assertSame(12, $g->questions()->count());
    }

    public function test_topic_from_the_menu_and_cancel(): void
    {
        $this->setupTeacher();
        $this->fakeAi();
        $this->press('q:home');
        $this->press('q:new:exam');
        $this->text('ریاضی — کسرها');
        $this->assertSame('ریاضی — کسرها', $this->quizState()['topic']);
        $this->assertTrue($this->has('q:go'));
        $this->press('q:x');
        $this->assertSame([], $this->quizState());
        $this->assertSame([], $this->aiCalls);
    }

    public function test_parse_edit_rules(): void
    {
        $mc = ['type' => 'mc', 'prompt' => 'قدیم', 'choices' => [['value' => 'الف', 'correct' => true], ['value' => 'ب', 'correct' => false]], 'answer' => null];
        $this->assertSame('فقط متن', BotQuiz::parseEdit('فقط متن', $mc)['prompt']);
        $this->assertTrue(BotQuiz::parseEdit("سؤال\n۱) یک\n۲) دو\nپاسخ: ۲", $mc)['choices'][1]['correct']);
        $this->assertIsString(BotQuiz::parseEdit("سؤال\nالف) یک ✅\nب) دو ✅", $mc));
        $tf = ['type' => 'tf', 'prompt' => 'آب در صفر درجه یخ می‌زند.', 'choices' => [['value' => 'درست', 'correct' => true], ['value' => 'نادرست', 'correct' => false]], 'answer' => 'درست'];
        $this->assertSame('نادرست', BotQuiz::parseEdit('پاسخ: غلط', $tf)['answer']);
        $blank = ['type' => 'blank', 'prompt' => 'پایتخت …… است.', 'choices' => [], 'answer' => 'تهران'];
        $this->assertIsString(BotQuiz::parseEdit('پایتخت ایران کجاست؟', $blank));
        $this->assertSame('تهران', BotQuiz::parseEdit("پایتختِ ایران …… است.\nپاسخ: تهران", $blank)['answer']);
    }
}
