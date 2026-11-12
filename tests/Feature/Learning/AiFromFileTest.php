<?php

namespace Tests\Feature\Learning;

use App\Models\Setting;
use App\Services\SmartExamAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** 📎 طراحیِ سؤال از روی فایل: عکسِ برگه / صفحه‌ی کتاب / PDF. */
class AiFromFileTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private function openai(array $questions): void
    {
        Setting::put('ai_provider', 'openai');
        Setting::put('openai_key', 'sk-test');
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => json_encode(['questions' => $questions], JSON_UNESCAPED_UNICODE)]]],
            'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1]])]);
    }

    public function test_same_mode_sends_the_image_and_keeps_the_sheet_as_is(): void
    {
        $this->openai([
            ['type' => 'mc', 'prompt' => 'حاصلِ ۲ + ۳ کدام است؟', 'choices' => [['text' => '۵', 'correct' => true], ['text' => '۶', 'correct' => false]], 'is_true' => null, 'answer' => null, 'explanation' => '', 'hint' => null, 'difficulty' => 'easy', 'bloom' => 'remember', 'topic' => 'جمع'],
            ['type' => 'tf', 'prompt' => 'عددِ ۷ زوج است.', 'choices' => [], 'is_true' => false, 'answer' => null, 'explanation' => '', 'hint' => null, 'difficulty' => 'easy', 'bloom' => 'remember', 'topic' => 'زوج و فرد'],
        ]);

        $r = app(SmartExamAiService::class)->generate([
            'count' => 20, 'types' => ['mc', 'tf', 'blank', 'desc'], 'grade' => 'دوم', 'subject' => 'ریاضی',
            'sources' => ['data:image/png;base64,' . self::PNG], 'source_mode' => 'same',
        ]);

        $this->assertTrue($r['ok']);
        $this->assertCount(2, $r['questions']);
        // در حالتِ «همین سؤال‌ها» دو گزینه هم پذیرفته می‌شود و ترتیبِ گزینه‌ها عوض نمی‌شود
        $this->assertSame('۵', $r['questions'][0]['choices'][0]['value']);
        $this->assertStringContainsString('از روی فایل', $r['message']);

        Http::assertSent(function (HttpRequest $req) {
            $user = $req->data()['messages'][1]['content'] ?? null;

            return is_array($user)
                && ($user[0]['type'] ?? '') === 'image_url'
                && str_starts_with($user[0]['image_url']['url'], 'data:image/png;base64,')
                && str_contains(end($user)['text'], 'دقیقاً همان‌طور که هست')
                && str_contains($req->data()['messages'][0]['content'], 'بازنویسیِ دقیق');
        });
        Http::assertSentCount(1);   // بدونِ فراخوانیِ تکمیلی
    }

    public function test_similar_mode_and_pdf_part_for_openai(): void
    {
        $this->openai([['type' => 'mc', 'prompt' => 'حاصلِ ۴ + ۳ کدام است؟', 'choices' => [['text' => '۷', 'correct' => true], ['text' => '۸', 'correct' => false], ['text' => '۶', 'correct' => false], ['text' => '۹', 'correct' => false]], 'is_true' => null, 'answer' => null, 'explanation' => '', 'hint' => null, 'difficulty' => 'easy', 'bloom' => 'apply', 'topic' => 'جمع']]);
        $r = app(SmartExamAiService::class)->generate(['count' => 1, 'types' => ['mc'], 'subject' => 'ریاضی',
            'sources' => [['mime' => 'application/pdf', 'data' => base64_encode('%PDF-1.4 test')]], 'source_mode' => 'similar']);
        $this->assertTrue($r['ok']);
        Http::assertSent(fn (HttpRequest $req) => ($req->data()['messages'][1]['content'][0]['type'] ?? '') === 'file'
            && str_contains($req->data()['messages'][0]['content'], 'سؤالِ مشابه'));
    }

    public function test_anthropic_gets_image_and_document_blocks(): void
    {
        Setting::put('ai_provider', 'anthropic');
        Setting::put('anthropic_key', 'sk-ant');
        Http::fake(['*' => Http::response(['content' => [['type' => 'tool_use', 'input' => ['questions' => [
            ['type' => 'blank', 'prompt' => 'پایتختِ ایران …… است.', 'choices' => [], 'is_true' => null, 'answer' => 'تهران', 'explanation' => '', 'hint' => null, 'difficulty' => 'easy', 'bloom' => 'remember', 'topic' => 'جغرافیا'],
        ]]]]])]);
        $r = app(SmartExamAiService::class)->generate(['count' => 1, 'types' => ['blank'], 'subject' => 'مطالعات اجتماعی',
            'sources' => ['data:image/png;base64,' . self::PNG, ['mime' => 'application/pdf', 'data' => base64_encode('%PDF-1.4')]], 'source_mode' => 'content']);
        $this->assertTrue($r['ok']);
        Http::assertSent(function (HttpRequest $req) {
            $c = $req->data()['messages'][0]['content'];

            return $c[0]['type'] === 'image' && $c[1]['type'] === 'document' && $c[2]['type'] === 'text'
                && str_contains($req->data()['system'], 'سؤال‌سازی از متنِ صفحه');
        });
    }

    public function test_text_only_model_gets_a_clear_message(): void
    {
        Setting::put('ai_provider', 'deepseek');
        Setting::put('ai_key_deepseek', 'sk-ds');
        Http::fake();
        $r = app(SmartExamAiService::class)->generate(['count' => 3, 'types' => ['mc'], 'sources' => ['data:image/png;base64,' . self::PNG]]);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('تصویرخوان', $r['message']);
        Http::assertNothingSent();
    }

    public function test_bad_sources_are_ignored(): void
    {
        $clean = SmartExamAiService::cleanSources(['data:text/html;base64,PGI+', 'not a data url', ['mime' => 'image/png', 'data' => '!!!'], 'data:image/png;base64,' . self::PNG]);
        $this->assertCount(1, $clean);
        $this->assertSame('image/png', $clean[0]['mime']);
    }

    public function test_exam_builder_endpoint_accepts_photo_of_a_book_page(): void
    {
        $this->seedRoles();
        Setting::put('smart_lab_enabled', true);
        Setting::put('smart_scope', 'all');
        Setting::put('smart_ai_enabled', true);
        [$teacher] = $this->makeClass($this->makeSchool());
        $this->openai([['type' => 'mc', 'prompt' => 'گیاهان غذای خود را از کجا می‌سازند؟', 'choices' => [['text' => 'نور خورشید', 'correct' => true], ['text' => 'خاک', 'correct' => false], ['text' => 'سنگ', 'correct' => false], ['text' => 'باد', 'correct' => false]], 'is_true' => null, 'answer' => null, 'explanation' => 'فتوسنتز', 'hint' => null, 'difficulty' => 'easy', 'bloom' => 'understand', 'topic' => 'گیاهان']]);

        $this->actingAs($teacher)->postJson(route('teacher.smart.ai'), [
            'count' => 1, 'types' => ['mc'], 'sources' => ['data:image/png;base64,' . self::PNG], 'source_mode' => 'content',
        ])->assertOk()->assertJsonPath('ok', true)->assertJsonPath('questions.0.prompt', 'گیاهان غذای خود را از کجا می‌سازند؟');

        $this->actingAs($teacher)->postJson(route('teacher.smart.ai'), ['count' => 1, 'source_mode' => 'nope'])->assertStatus(422);
    }
}
