<?php

namespace Tests\Feature\Audio;

use App\Models\AudioSubmission;
use App\Models\AudioTask;
use App\Models\Grade;
use App\Models\GradeColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** «🎧 املا و روخوانی»: ساخت، محدودیتِ پایه، فرستادن، تصحیح و نمره در دفتر. */
class AudioTaskTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    private function dictation(array $extra = []): array
    {
        $this->seedRoles();
        Storage::fake('public');
        [$teacher, $classroom, $student] = $this->makeClass($this->makeSchool());
        $this->actingAs($teacher)->post(route('teacher.audio.store'), array_merge([
            'kind' => 'dictation', 'title' => 'املای درسِ نوروز', 'classroom_id' => $classroom->id, 'source' => 'voice',
            'audio' => UploadedFile::fake()->create('voice.webm', 40, 'audio/webm'),
            'text' => 'نوروز جشنِ آغازِ بهار است. مردم خانه‌ها را تمیز می‌کنند.',
            'score_type' => 'numeric', 'penalty' => 0.5, 'publish' => 1,
        ], $extra))->assertSessionHasNoErrors()->assertRedirect();

        return [$teacher, $classroom, $student, AudioTask::firstOrFail()];
    }

    public function test_teacher_creates_and_class_is_notified_without_leaking_text(): void
    {
        [$teacher, , $student, $task] = $this->dictation();
        $this->assertTrue($task->is_published);
        $this->assertCount(2, $task->sentences);
        $this->assertDatabaseHas('announcement_recipients', ['user_id' => $student->id]);

        $props = $this->actingAs($student)->get(route('listen.show', $task))->assertOk()->viewData('page')['props'];
        $json = json_encode($props['task'], JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('نوروز جشن', $json);
        $this->actingAs($student)->get(route('audio.task-audio', $task))->assertOk();

        $list = $this->actingAs($student)->get(route('listen'))->viewData('page')['props']['tasks'];
        $this->assertSame('todo', $list[0]['status']);
        $this->actingAs($teacher)->get(route('teacher.audio'))->assertOk();
        $this->actingAs($teacher)->get(route('teacher.audio.show', $task))->assertOk();
    }

    public function test_dictation_blocked_for_grades_without_dictation(): void
    {
        $this->seedRoles();
        [$teacher, $classroom] = $this->makeClass($this->makeSchool(), 'هفتم');
        $this->actingAs($teacher)->post(route('teacher.audio.store'), [
            'kind' => 'dictation', 'title' => 'املا', 'classroom_id' => $classroom->id, 'source' => 'tts',
            'text' => 'یک جمله.', 'score_type' => 'descriptive',
        ])->assertSessionHasErrors('classroom_id');
        $this->assertSame(0, AudioTask::count());

        // روخوانی برای همه‌ی پایه‌ها
        $this->actingAs($teacher)->post(route('teacher.audio.store'), [
            'kind' => 'reading', 'title' => 'روخوانی', 'classroom_id' => $classroom->id, 'source' => 'tts',
            'text' => 'یک جمله.', 'score_type' => 'descriptive',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, AudioTask::count());
    }

    public function test_student_submits_teacher_grades_into_gradebook(): void
    {
        [$teacher, $classroom, $student, $task] = $this->dictation();
        $other = $this->addStudent($teacher->school, $classroom);
        $before = $student->totalXp();

        $this->actingAs($student)->post(route('listen.submit', $task), ['file' => UploadedFile::fake()->image('sheet.jpg', 600, 800)])
            ->assertSessionHasNoErrors();
        $sub = AudioSubmission::firstOrFail();
        $this->assertSame($before + 5, $student->fresh()->totalXp());

        // فایل فقط برای خودش و معلم
        $this->actingAs($student)->get(route('audio.file', [$sub->id, 'file']))->assertOk();
        $this->actingAs($teacher)->get(route('audio.file', [$sub->id, 'file']))->assertOk();
        $this->assertContains($this->actingAs($other)->get(route('audio.file', [$sub->id, 'file']))->status(), [403, 404]);
        $this->assertContains($this->actingAs($student)->postJson(route('audio.grade', $sub), ['score' => 20])->status(), [403, 404]);

        // نمره‌ی عددی لازم است
        $this->actingAs($teacher)->postJson(route('audio.grade', $sub), ['mistakes' => 2])->assertStatus(422);
        $this->actingAs($teacher)->post(route('audio.grade', $sub), [
            'mistakes' => 2, 'score' => 19, 'feedback' => 'آفرین! «آغاز» را با «آ» بنویس',
            'marked' => UploadedFile::fake()->image('marked.jpg', 600, 800),
        ])->assertOk()->assertJsonPath('submission.graded', true);

        $col = GradeColumn::firstOrFail();
        $this->assertSame($classroom->id, (int) $col->classroom_id);
        $this->assertStringContainsString('املا', $col->title);
        $this->assertEquals(19, (float) Grade::where('grade_column_id', $col->id)->where('student_id', $student->id)->value('score'));
        $this->assertSame($col->id, (int) $task->fresh()->grade_column_id);

        // تصحیحِ دوباره همان ستون را به‌روز می‌کند
        $this->actingAs($teacher)->post(route('audio.grade', $sub), ['mistakes' => 1, 'score' => 19.5])->assertOk();
        $this->assertSame(1, GradeColumn::count());
        $this->assertSame(1, Grade::count());

        $mine = $this->actingAs($student)->get(route('listen.show', $task))->viewData('page')['props']['submitted'];
        $this->assertTrue($mine['graded']);
        $this->assertEquals(19.5, $mine['score']);
        $this->actingAs($student)->get(route('audio.file', [$sub->id, 'marked']))->assertOk();
        $this->assertSame('graded', $this->actingAs($student)->get(route('listen'))->viewData('page')['props']['tasks'][0]['status']);
    }

    public function test_reading_accepts_audio_and_descriptive_grade(): void
    {
        $this->seedRoles();
        Storage::fake('public');
        [$teacher, $classroom, $student] = $this->makeClass($this->makeSchool());
        $this->actingAs($teacher)->post(route('teacher.audio.store'), [
            'kind' => 'reading', 'title' => 'روخوانیِ کوچه‌ی ما', 'classroom_id' => $classroom->id, 'source' => 'tts',
            'text' => 'کوچه‌ی ما پر از درخت است.', 'score_type' => 'descriptive', 'publish' => 1,
        ])->assertSessionHasNoErrors();
        $task = AudioTask::firstOrFail();

        $this->actingAs($student)->post(route('listen.submit', $task), ['file' => UploadedFile::fake()->image('x.jpg')])
            ->assertSessionHasErrors('file');
        $this->actingAs($student)->post(route('listen.submit', $task), ['file' => UploadedFile::fake()->create('me.webm', 30, 'audio/webm')])
            ->assertSessionHasNoErrors();
        $sub = AudioSubmission::firstOrFail();
        $this->assertSame('audio', $sub->file_kind);

        $this->actingAs($teacher)->postJson(route('audio.grade', $sub), ['grade' => 'عالی'])->assertStatus(422);
        $this->actingAs($teacher)->post(route('audio.grade', $sub), ['grade' => 'خیلی خوب', 'feedback' => 'شمرده و رسا'])->assertOk();
        $this->assertSame('خیلی خوب', Grade::firstOrFail()->text);
    }

    public function test_teacher_audio_is_served_as_audio_and_can_be_replaced(): void
    {
        [$teacher, , $student, $task] = $this->dictation();
        $res = $this->actingAs($student)->get(route('audio.task-audio', $task))->assertOk();
        $this->assertSame('audio/webm', $res->headers->get('Content-Type'));
        $this->assertTrue($this->actingAs($teacher)->get(route('teacher.audio.show', $task))->viewData('page')['props']['task']['audio_legacy']);

        $this->actingAs($teacher)->post(route('teacher.audio.replace', $task), [
            'audio' => UploadedFile::fake()->create('voice.m4a', 40, 'audio/mp4'),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $res = $this->actingAs($student)->get(route('audio.task-audio', $task->fresh()))->assertOk();
        $this->assertSame('audio/mp4', $res->headers->get('Content-Type'));
        $this->assertFalse($this->actingAs($teacher)->get(route('teacher.audio.show', $task))->viewData('page')['props']['task']['audio_legacy']);
        $this->actingAs($student)->post(route('teacher.audio.replace', $task), [
            'audio' => UploadedFile::fake()->create('x.m4a', 10, 'audio/mp4'),
        ])->assertForbidden();
    }

    public function test_teacher_is_notified_when_student_submits(): void
    {
        [$teacher, , $student, $task] = $this->dictation();
        $this->actingAs($student)->post(route('listen.submit', $task), [
            'file' => UploadedFile::fake()->image('sheet.jpg'),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('announcement_recipients', ['user_id' => $teacher->id]);
        $ann = \App\Models\Announcement::where('sender_id', $student->id)->latest('id')->firstOrFail();
        $this->assertStringContainsString($student->name, $ann->title);
        $this->assertSame(route('teacher.audio.show', $task->id, false), $ann->link);
        $this->assertSame(1, $this->actingAs($teacher)->get(route('teacher.audio'))->viewData('page')['props']['audioPending']);
    }
}
