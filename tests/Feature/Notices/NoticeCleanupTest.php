<?php

namespace Tests\Feature\Notices;

use App\Models\Announcement;
use App\Models\AudioTask;
use App\Models\LiveContest;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** اعلان‌ها: حذفِ چندتایی/همه، پاک‌شدنِ کاملِ اعلان‌های یک موضوع با حذفِ آن؛ صدای پیوسته؛ نتایجِ مسابقه. */
class NoticeCleanupTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    private function audioTask(): array
    {
        $this->seedRoles();
        Storage::fake('public');
        [$teacher, $classroom, $student] = $this->makeClass($this->makeSchool());
        $this->actingAs($teacher)->post(route('teacher.audio.store'), [
            'kind' => 'dictation', 'title' => 'املای بهار', 'classroom_id' => $classroom->id, 'source' => 'voice',
            'audio' => UploadedFile::fake()->create('voice.m4a', 40, 'audio/mp4'), 'score_type' => 'descriptive', 'publish' => 1,
        ])->assertSessionHasNoErrors();

        return [$teacher, $classroom, $student, AudioTask::firstOrFail()];
    }

    public function test_deleting_a_task_removes_its_notifications_completely(): void
    {
        [$teacher, , $student, $task] = $this->audioTask();
        $this->actingAs($student)->post(route('listen.submit', $task), ['file' => UploadedFile::fake()->image('a.jpg')]);
        $ids = Announcement::withoutGlobalScopes()->pluck('id');
        $this->assertCount(2, $ids); // به بچه‌ها: «املای تازه»؛ به معلم: «املا رسید»
        $this->assertSame(AudioTask::class, Announcement::withoutGlobalScopes()->first()->source_type);
        $this->actingAs($student)->post(route('notices.read', 'a' . $ids[0]));

        $this->actingAs($teacher)->delete(route('teacher.audio.destroy', $task))->assertRedirect();

        $this->assertSame(0, Announcement::withoutGlobalScopes()->count());
        $this->assertSame(0, DB::table('announcement_recipients')->count());
        $this->assertSame(0, DB::table('notification_reads')->where('key', 'a' . $ids[0])->count());
    }

    public function test_deleting_a_live_contest_removes_its_notifications(): void
    {
        $this->seedRoles();
        [$teacher, $classroom, $student] = $this->makeClass($this->makeSchool());
        $this->actingAs($teacher)->post(route('teacher.live.store'), [
            'title' => 'مسابقه', 'classroom_id' => $classroom->id, 'mode' => 'manual', 'seconds' => 20,
            'questions' => [['prompt' => '۲+۲', 'choices' => ['۴', '۵'], 'answer' => 0]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, Announcement::withoutGlobalScopes()->count());
        $this->actingAs($teacher)->delete(route('teacher.live.destroy', LiveContest::firstOrFail()));
        $this->assertSame(0, Announcement::withoutGlobalScopes()->count());
    }

    public function test_user_can_remove_selected_or_all_notifications(): void
    {
        [$teacher, , $student, $task] = $this->audioTask();
        foreach (['یک', 'دو', 'سه'] as $t) {
            Announcement::create(['school_id' => $student->school_id, 'sender_id' => $teacher->id, 'audience' => 'personal', 'title' => $t, 'body' => 'متن'])
                ->recipients()->sync([$student->id]);
        }
        $feed = fn () => collect($this->actingAs($student)->get(route('notices'))->viewData('page')['props']['notices']);
        $this->assertCount(4, $feed());

        $two = $feed()->take(2)->pluck('id')->all();
        $this->actingAs($student)->post(route('notices.remove'), ['keys' => $two])->assertRedirect();
        $this->assertCount(2, $feed());
        // برای معلم/بقیه پاک نمی‌شود
        $this->assertSame(4, Announcement::withoutGlobalScopes()->count());

        $this->actingAs($student)->post(route('notices.remove'), ['all' => true]);
        $this->assertCount(0, $feed());
    }

    public function test_tts_builds_one_continuous_audio_with_pauses(): void
    {
        $this->seedRoles();
        Storage::fake('public');
        Setting::put('openai_key', 'sk-test');
        $speech = str_repeat("\x10\x00", 24000); // ۱ ثانیه صدای ساختگی (PCM)
        Http::fake(['api.openai.com/*' => Http::response($speech, 200)]);
        [$teacher, $classroom] = $this->makeClass($this->makeSchool());

        $this->actingAs($teacher)->post(route('teacher.audio.store'), [
            'kind' => 'dictation', 'title' => 'املا', 'classroom_id' => $classroom->id, 'source' => 'tts', 'pace' => 'normal',
            'text' => 'بهار آمد. درخت‌ها، سبز شدند و پرنده‌ها خواندند. ما شاد هستیم.', 'score_type' => 'descriptive',
        ])->assertSessionHasNoErrors();

        $task = AudioTask::firstOrFail();
        // صدا در صفحه‌ی تکلیف، قدم‌به‌قدم ساخته می‌شود
        $this->actingAs($teacher)->postJson(route('teacher.audio.build', $task))->assertJsonPath('done', true);
        $task->refresh();
        $this->assertNotNull($task->audio_path);
        $this->assertStringEndsWith('.wav', $task->audio_path);
        Http::assertSentCount(3); // سه جمله (ویرگول جمله را نمی‌شکند)
        Http::assertSent(fn ($r) => $r['response_format'] === 'pcm');
        $bytes = strlen(Storage::disk('public')->get($task->audio_path));
        $seconds = ($bytes - 44) / 48000;
        // ۳ ثانیه صدا + دو مکثِ املا (۳ + ۰٫۷ × کلمه) + سکوتِ آغاز و پایان
        $this->assertGreaterThan(3 + 2 * 3, $seconds);
        $props = $this->actingAs($teacher)->get(route('teacher.audio.show', $task))->viewData('page')['props']['task'];
        $this->assertNotNull($props['audio']);
    }

    public function test_teacher_sees_contest_results(): void
    {
        $this->seedRoles();
        [$teacher, $classroom, $student] = $this->makeClass($this->makeSchool());
        $this->actingAs($teacher)->post(route('teacher.live.store'), [
            'title' => 'مسابقه', 'classroom_id' => $classroom->id, 'mode' => 'manual', 'seconds' => 20,
            'questions' => [['prompt' => '۲+۲', 'choices' => ['۴', '۵'], 'answer' => 0]],
        ]);
        $c = LiveContest::firstOrFail();
        $this->actingAs($teacher)->postJson(route('teacher.live.go', $c), ['action' => 'start']);
        $this->travel(4)->seconds();
        $this->actingAs($student)->postJson(route('live.answer', $c), ['q' => 0, 'choice' => 0])->assertOk();
        $res = $this->actingAs($teacher)->getJson(route('teacher.live.results', $c))->assertOk()->json();
        $this->assertSame(100, $res['ranking'][0]['accuracy']);
        $this->assertSame(1, $res['questions'][0]['correct']);
        $this->actingAs($student)->getJson(route('teacher.live.results', $c))->assertForbidden();
    }
}
