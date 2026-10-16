<?php

namespace Tests\Feature\Learning;

use App\Models\EduGame;
use App\Models\EduGameQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** گزارشِ بازی: چه کسی کدام سؤال را غلط زده؛ و ساختِ «آزمونِ جبرانی» از سؤال‌های دشوار. */
class GameRemedialTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    public function test_hard_questions_list_who_was_wrong_and_build_a_remedial_game(): void
    {
        $this->seedRoles();
        [$teacher, $classroom, $ali] = $this->makeClass($this->makeSchool());
        $sara = $this->addStudent($teacher->school, $classroom);
        $reza = $this->addStudent($teacher->school, $classroom);
        $bank = $this->makeQuestions($teacher, 'اعداد', 4);
        $game = EduGame::create(['school_id' => $teacher->school_id, 'teacher_id' => $teacher->id, 'template_key' => 'snakes', 'title' => 'شهرکِ اعداد', 'subject' => 'ریاضی', 'grade' => 'چهارم', 'status' => 'published']);
        foreach ($bank as $k => $b) {
            EduGameQuestion::create(['edu_game_id' => $game->id, 'type' => 'mc', 'prompt' => $b->prompt, 'choices' => $b->choices, 'points' => 10, 'sort' => $k, 'bank_id' => $b->id]);
        }
        foreach ([$ali, $sara, $reza] as $st) {
            $game->targets()->create(['student_id' => $st->id]);   // مخاطبِ بازی: همین سه نفر
        }
        $qs = $game->questions()->get();
        // پاسخِ درستِ همه گزینه‌ی ۰ است. علی: سؤالِ ۲ غلط؛ سارا: سؤالِ ۲ و ۳ غلط؛ رضا: همه درست
        $this->actingAs($ali)->post(route('gameworld.finish', $game), ['answers' => [0, 0, 1, 0]]);
        $this->actingAs($sara)->post(route('gameworld.finish', $game), ['answers' => [0, 0, 2, 1]]);
        $this->actingAs($reza)->post(route('gameworld.finish', $game), ['answers' => [0, 0, 0, 0]]);

        $hard = $this->actingAs($teacher)->get(route('teacher.studio.report', $game))->assertOk()->viewData('page')['props']['hardQuestions'];
        $this->assertCount(2, $hard);                          // فقط سؤال‌هایی که کسی غلط زده — حتی یک نفر
        $this->assertSame($qs[2]->id, $hard[0]['id']);         // پرغلط‌ترین اول
        $this->assertSame(2, $hard[0]['wrong']);
        $this->assertSame(3, $hard[0]['answered']);
        $this->assertEqualsCanonicalizing([$ali->id, $sara->id], array_column($hard[0]['students'], 'id'));
        $this->assertSame($qs[2]->choices[1]['value'], collect($hard[0]['students'])->firstWhere('id', $ali->id)['picked']);
        $this->assertSame($qs[3]->id, $hard[1]['id']);
        $this->assertSame([$sara->id], array_column($hard[1]['students'], 'id'));

        // آزمونِ جبرانی از یک سؤال، فقط برای اشتباه‌زده‌ها
        $this->actingAs($teacher)->post(route('teacher.studio.remedial', $game), ['questions' => [$qs[3]->id], 'audience' => 'wrong'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $r1 = EduGame::where('id', '!=', $game->id)->latest('id')->firstOrFail();
        $this->assertSame('draft', $r1->status);
        $this->assertStringContainsString('جبرانی', $r1->title);
        $this->assertSame([$qs[3]->prompt], $r1->questions()->pluck('prompt')->all());
        $this->assertSame([$sara->id], $r1->targets()->pluck('student_id')->all());

        // از همه‌ی سؤال‌های دشوار، برای همه‌ی مخاطبانِ بازی، با عنوانِ دلخواه
        $this->actingAs($teacher)->post(route('teacher.studio.remedial', $game), ['questions' => [$qs[2]->id, $qs[3]->id], 'audience' => 'all', 'title' => 'مرورِ جمع'])
            ->assertRedirect();
        $r2 = EduGame::latest('id')->firstOrFail();
        $this->assertSame('مرورِ جمع', $r2->title);
        $this->assertSame(2, $r2->questions()->count());
        $this->assertSame(3, $r2->targets()->count());
        $this->assertSame(4, $game->questions()->count());     // بازیِ اصلی دست نخورد

        // بی‌سؤال یا سؤالی که کسی غلط نزده → خطا؛ معلمِ دیگر → ممنوع
        $this->actingAs($teacher)->post(route('teacher.studio.remedial', $game), ['questions' => []])->assertSessionHasErrors('questions');
        $this->actingAs($teacher)->post(route('teacher.studio.remedial', $game), ['questions' => [$qs[0]->id], 'audience' => 'wrong'])->assertSessionHasErrors('questions');
        [$other] = $this->makeClass($teacher->school);
        $this->actingAs($other)->post(route('teacher.studio.remedial', $game), ['questions' => [$qs[2]->id]])->assertForbidden();
    }
}
