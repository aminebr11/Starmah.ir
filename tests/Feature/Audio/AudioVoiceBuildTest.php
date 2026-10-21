<?php

namespace Tests\Feature\Audio;

use App\Models\Announcement;
use App\Models\AudioTask;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** املا با صدای هوش مصنوعی: ساختِ قدم‌به‌قدم، تأیید پیش از انتشار، ویرایش و ساختِ دوباره، انتشارِ زمان‌بندی‌شده. */
class AudioVoiceBuildTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    public function test_sentences_merge_stray_fragments(): void
    {
        $this->assertSame(['بچه‌ها به پارک رفتند. آن‌ها.', 'هوا آفتابی بود و همه شاد بودند.'],
            AudioTask::speechSentences("بچه‌ها به پارک رفتند. آن‌ها . هوا آفتابی بود و همه شاد بودند."));
        $this->assertSame(['سلام. امروز هوا خیلی خوب است.'], AudioTask::speechSentences('سلام . امروز هوا خیلی خوب است.'));
    }

    public function test_voice_is_built_step_by_step_then_confirmed_edited_and_scheduled(): void
    {
        $this->seedRoles();
        Storage::fake('public');
        Setting::put('openai_key', 'sk-test');
        Http::fake(['api.openai.com/*' => Http::response(str_repeat("\x01\x02", 600), 200)]);
        [$teacher, $classroom, $student] = $this->makeClass($this->makeSchool());

        $this->actingAs($teacher)->post(route('teacher.audio.store'), [
            'kind' => 'dictation', 'title' => 'املای نوروز', 'classroom_id' => $classroom->id, 'source' => 'tts', 'score_type' => 'descriptive',
            'text' => 'نوروز جشنِ آغازِ بهار است. مردم خانه‌هایشان را تمیز می‌کنند. بچه‌ها لباسِ نو می‌پوشند.', 'publish_mode' => 'review',
        ])->assertSessionHasNoErrors();
        $task = AudioTask::firstOrFail();
        $this->assertTrue($task->needsBuild());
        $this->assertFalse($task->is_published);
        Http::assertNothingSent();                                   // ذخیره دیگر منتظرِ ساختِ صدا نمی‌ماند

        // پیش از ساختِ صدا منتشر نمی‌شود
        $this->actingAs($teacher)->post(route('teacher.audio.publish', $task));
        $this->assertFalse($task->fresh()->is_published);

        $r = $this->actingAs($teacher)->postJson(route('teacher.audio.build', $task))->assertOk()->json();
        $this->assertTrue($r['done']);
        $this->assertSame(3, $r['total']);
        $task->refresh();
        $this->assertNotNull($task->audio_path);
        $this->assertFalse($task->needsBuild());
        Http::assertSentCount(3);

        // تأیید → انتشار و اعلان برای بچه‌ها
        $this->actingAs($teacher)->post(route('teacher.audio.publish', $task));
        $this->assertTrue($task->fresh()->is_published);
        $this->assertTrue(Announcement::whereHas('recipients', fn ($q) => $q->where('users.id', $student->id))->exists());

        // ویرایشِ متن → صدای قبلی می‌ماند تا صدای تازه ساخته شود؛ فقط جمله‌ی تازه ساخته می‌شود
        $oldAudio = $task->fresh()->audio_path;
        $this->actingAs($teacher)->put(route('teacher.audio.update', $task), ['title' => 'املای نوروز ۲', 'source' => 'tts', 'pace' => 'normal',
            'text' => 'نوروز جشنِ آغازِ بهار است. مردم خانه‌هایشان را تمیز می‌کنند. ماهی‌های قرمز در تنگ شنا می‌کنند.'])->assertSessionHasNoErrors();
        $task->refresh();
        $this->assertTrue($task->needsBuild());
        $this->assertSame($oldAudio, $task->audio_path);
        $this->actingAs($teacher)->postJson(route('teacher.audio.build', $task))->assertJsonPath('done', true);
        Http::assertSentCount(4);
        $this->assertNotSame($oldAudio, $task->fresh()->audio_path);

        // انتشارِ زمان‌بندی‌شده
        $this->actingAs($teacher)->post(route('teacher.audio.store'), [
            'kind' => 'dictation', 'title' => 'املای فردا', 'classroom_id' => $classroom->id, 'source' => 'tts', 'score_type' => 'descriptive',
            'text' => 'نوروز جشنِ آغازِ بهار است.', 'publish_mode' => 'schedule', 'publish_at' => now()->addHour()->format('Y-m-d H:i'),
        ])->assertSessionHasNoErrors();
        $later = AudioTask::latest('id')->firstOrFail();
        $this->actingAs($teacher)->postJson(route('teacher.audio.build', $later))->assertJsonPath('done', true);
        $this->assertFalse($later->fresh()->is_published);
        $this->travel(61)->minutes();
        $this->actingAs($student)->get(route('listen'))->assertOk();
        $this->assertTrue($later->fresh()->is_published);
    }

    public function test_build_reports_a_clear_reason_when_the_ai_service_fails(): void
    {
        $this->seedRoles();
        Storage::fake('public');
        Setting::put('openai_key', 'sk-bad');
        Http::fake(['api.openai.com/*' => Http::response(['error' => 'bad key'], 401)]);
        [$teacher, $classroom] = $this->makeClass($this->makeSchool());
        $this->actingAs($teacher)->post(route('teacher.audio.store'), [
            'kind' => 'dictation', 'title' => 'املا', 'classroom_id' => $classroom->id, 'source' => 'tts', 'score_type' => 'descriptive',
            'text' => 'نوروز جشنِ آغازِ بهار است.',
        ])->assertSessionHasNoErrors();
        $r = $this->actingAs($teacher)->postJson(route('teacher.audio.build', AudioTask::firstOrFail()))->assertOk()->json();
        $this->assertFalse($r['done']);
        $this->assertStringContainsString('کلید', $r['error']);
        $this->assertSame(1, $r['failed']);
    }
}
