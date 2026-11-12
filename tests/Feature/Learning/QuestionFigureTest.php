<?php

namespace Tests\Feature\Learning;

use App\Models\Setting;
use App\Models\SmartExam;
use App\Services\SmartExamAiService;
use App\Support\Figure;
use App\Support\QuestionMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** 🖼️ شکلِ سؤال: رسم از توصیف، ساخت/برش با هوش مصنوعی، ذخیره در آزمون، و ابزارِ معلم. */
class QuestionFigureTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function stored(?string $url): bool
    {
        return $url !== null && Storage::disk('public')->exists(ltrim(preg_replace('#^.*?/storage/#', '', $url), '/'));
    }

    public function test_every_figure_type_renders_and_text_is_escaped(): void
    {
        $specs = [
            ['type' => 'pizza', 'parts' => 8, 'shaded' => 3], ['type' => 'pizza', 'parts' => 4, 'shaded' => 5],
            ['type' => 'fraction_bar', 'parts' => 5, 'shaded' => 2], ['type' => 'grid', 'rows' => 10, 'cols' => 10, 'shaded' => 35],
            ['type' => 'number_line', 'from' => 0, 'to' => 1, 'step' => '۱/۴', 'points' => [['at' => '3/4', 'label' => '؟']]],
            ['type' => 'bar_chart', 'labels' => ['الف', 'ب'], 'values' => ['۳', 5]], ['type' => 'pie_chart', 'labels' => ['الف', 'ب'], 'values' => [1, 3]],
            ['type' => 'pictograph', 'groups' => [['emoji' => '🍎', 'count' => 4], ['emoji' => '🍎', 'count' => 4]]], ['type' => 'pictograph', 'rows' => 2, 'cols' => 3],
            ['type' => 'base10', 'hundreds' => 1, 'tens' => 2, 'ones' => 3], ['type' => 'clock', 'hour' => 15, 'minute' => 45],
            ['type' => 'shape', 'kind' => 'right_triangle', 'side_labels' => ['۳', '۴', '؟']], ['type' => 'shape', 'kind' => 'circle', 'side_labels' => ['۵']],
            ['type' => 'angle', 'degrees' => 90], ['type' => 'ruler', 'length' => 6.5], ['type' => 'table', 'headers' => ['نام', 'سن'], 'rows' => [['سارا', '۹']]],
        ];
        foreach ($specs as $sp) {
            $svg = Figure::svg($sp);
            $this->assertNotNull($svg, $sp['type']);
            $this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg"', $svg);
        }
        // یک‌چهارمِ محور ← ۵ درجه
        $this->assertSame(0.25, Figure::normalize(['type' => 'number_line', 'from' => 0, 'to' => 1, 'step' => '۱/۴'])['step']);

        $evil = Figure::svg(['type' => 'table', 'headers' => ['<script>alert(1)</script>'], 'rows' => [['"><img onerror=x>']], 'caption' => '<b>x</b>']);
        $this->assertStringNotContainsString('<script', $evil);
        $this->assertStringNotContainsString('<img', $evil);
        $this->assertNull(Figure::svg(['type' => 'video']));
        $this->assertNull(Figure::svg(['type' => 'bar_chart', 'labels' => ['a'], 'values' => []]));
        $this->assertNull(Figure::svg('not json'));
    }

    public function test_ai_draws_figures_and_crops_them_from_the_sheet(): void
    {
        Setting::put('ai_provider', 'openai');
        Setting::put('openai_key', 'sk-test');
        $q = fn ($prompt, $figure = null, $box = null) => ['type' => 'mc', 'prompt' => $prompt,
            'choices' => [['text' => '۳/۸', 'correct' => true], ['text' => '۵/۸', 'correct' => false], ['text' => '۳/۵', 'correct' => false], ['text' => '۱/۸', 'correct' => false]],
            'is_true' => null, 'answer' => null, 'explanation' => '', 'hint' => null, 'difficulty' => 'easy', 'bloom' => 'understand', 'topic' => 'کسر',
            'figure' => $figure, 'figure_box' => $box];
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => json_encode(['questions' => [
            $q('با توجه به شکل، چه کسری از پیتزا رنگی است؟', '{"type":"pizza","parts":8,"shaded":3}'),
            $q('با توجه به شکل، کدام کسر نشان داده شده؟', null, [1, 100, 100, 600, 700]),
            $q('در شکلِ روبه‌رو چند قسمت رنگی است؟'),                       // شکل ندارد ← کنار گذاشته می‌شود
        ]], JSON_UNESCAPED_UNICODE)]]]])]);

        $r = app(SmartExamAiService::class)->generate(['count' => 3, 'types' => ['mc'], 'subject' => 'ریاضی', 'grade' => 'چهارم']);
        $this->assertTrue($r['ok']);
        // «با توجه به شکل» بدونِ شکلِ واقعی (کادرِ برش بدونِ فایل، یا هیچ) کنار گذاشته می‌شود
        $this->assertCount(1, $r['questions']);
        $this->assertStringEndsWith('.svg', $r['questions'][0]['media_url']);
        $this->assertTrue($this->stored($r['questions'][0]['media_url']));
        Http::assertSent(fn ($req) => str_contains($req->data()['messages'][0]['content'], '"type":"pizza"'));

        // «دقیقاً همین سؤال‌ها» از روی عکس: شکل از خودِ برگه بریده می‌شود
        $im = imagecreatetruecolor(400, 500);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        imagefilledrectangle($im, 50, 60, 250, 280, imagecolorallocate($im, 200, 30, 30));
        ob_start();
        imagepng($im);
        $png = ob_get_clean();
        $r2 = app(SmartExamAiService::class)->generate(['count' => 20, 'types' => ['mc'], 'sources' => ['data:image/png;base64,' . base64_encode($png)], 'source_mode' => 'same']);
        $crop = $r2['questions'][1]['media_url'] ?? null;
        $this->assertNotNull($crop);
        $this->assertStringEndsWith('.jpg', $crop);
        $this->assertTrue($this->stored($crop));
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get(ltrim(preg_replace('#^.*?/storage/#', '', $crop), '/')));
        $this->assertTrue($w > 200 && $w < 300 && $h > 230 && $h < 290, "crop {$w}x{$h}");
        // در حالتِ «همین سؤال‌ها» سؤالِ «شکلِ روبه‌رو» بدونِ شکل هم می‌ماند (معلم خودش شکل می‌گذارد)
        $this->assertCount(3, $r2['questions']);
    }

    public function test_exam_keeps_the_figure_and_student_sees_it(): void
    {
        $this->seedRoles();
        Setting::put('smart_lab_enabled', true);
        Setting::put('smart_scope', 'all');
        [$teacher, $classroom] = $this->makeClass($this->makeSchool());
        $url = QuestionMedia::storeFigure(['type' => 'clock', 'hour' => 3, 'minute' => 0]);
        $this->actingAs($teacher)->post(route('teacher.smart.store'), [
            'title' => 'ساعت', 'status' => 'published', 'rules' => ['duration' => 10], 'target_classrooms' => [$classroom->id],
            'questions' => [
                ['type' => 'mc', 'prompt' => 'ساعت چند است؟', 'media_url' => $url, 'choices' => [['value' => '۳', 'correct' => true], ['value' => '۴', 'correct' => false]]],
                ['type' => 'mc', 'prompt' => 'سؤالِ دوم', 'media_url' => 'javascript:alert(1)', 'choices' => [['value' => 'الف', 'correct' => true], ['value' => 'ب', 'correct' => false]]],
            ],
        ])->assertRedirect();
        $exam = SmartExam::firstOrFail();
        $this->assertSame([$url, null], $exam->questions()->orderBy('sort')->pluck('media_path')->all());
        $this->assertSame($url, \App\Models\SmartQuestionBank::where('prompt', 'ساعت چند است؟')->value('media_path'));

        $this->actingAs($teacher)->get(route('teacher.smart.edit', $exam))->assertOk()
            ->assertInertia(fn ($p) => $p->where('editing.questions.0.media_url', $url));
    }

    public function test_teacher_figure_tools(): void
    {
        $this->seedRoles();
        [$teacher] = $this->makeClass($this->makeSchool());
        $this->actingAs($teacher);

        $this->postJson(route('teacher.figures.preview'), ['spec' => ['type' => 'pizza', 'parts' => 6, 'shaded' => 2]])
            ->assertOk()->assertJsonPath('ok', true)->assertJsonPath('spec.parts', 6);
        $url = $this->postJson(route('teacher.figures.store'), ['spec' => ['type' => 'angle', 'degrees' => 45]])->assertOk()->json('url');
        $this->assertTrue($this->stored($url));
        $this->postJson(route('teacher.figures.store'), ['spec' => ['type' => 'nope']])->assertStatus(422);

        $up = $this->post(route('teacher.figures.upload'), ['image' => UploadedFile::fake()->image('fig.png', 300, 200)], ['Accept' => 'application/json'])->assertOk()->json('url');
        $this->assertTrue($this->stored($up));
        $this->post(route('teacher.figures.upload'), ['image' => UploadedFile::fake()->create('x.svg', 2, 'image/svg+xml')], ['Accept' => 'application/json'])->assertStatus(422);

        Setting::put('ai_provider', 'openai');
        Setting::put('openai_key', 'sk-test');
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '{"type":"bar_chart","labels":["سیب","موز"],"values":[3,5]}']]]])]);
        $this->postJson(route('teacher.figures.ai'), ['prompt' => 'نمودار نشان می‌دهد چند نفر موز دوست دارند؟', 'subject' => 'ریاضی'])
            ->assertOk()->assertJsonPath('ok', true)->assertJsonPath('spec.type', 'bar_chart');
    }
}
