<?php

namespace Tests\Feature\Live;

use App\Models\LiveContest;
use App\Models\LiveContestPlayer;
use App\Models\XpEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** «🏆 مسابقه‌ی زنده»: ساخت، اجرا روی تخته، پاسخِ گوشی، امتیاز و حالتِ خودکار. */
class LiveContestTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    private function questions(): array
    {
        return [
            ['prompt' => '۲ + ۳ = ?', 'choices' => ['۴', '۵', '۶'], 'answer' => 1],
            ['prompt' => 'پایتختِ ایران؟', 'choices' => ['تهران', 'شیراز'], 'answer' => 0],
        ];
    }

    private function make(array $extra = []): array
    {
        $this->seedRoles();
        [$teacher, $classroom, $student] = $this->makeClass($this->makeSchool());
        $this->actingAs($teacher)->post(route('teacher.live.store'), array_merge([
            'title' => 'مسابقه‌ی ریاضی', 'classroom_id' => $classroom->id, 'mode' => 'manual', 'seconds' => 20, 'questions' => $this->questions(),
        ], $extra))->assertSessionHasNoErrors()->assertRedirect();

        return [$teacher, $classroom, $student, LiveContest::firstOrFail()];
    }

    public function test_full_manual_round_with_scoring_and_xp(): void
    {
        [$teacher, $classroom, $student, $c] = $this->make();
        $slow = $this->addStudent($teacher->school, $classroom);
        $outsider = $this->makeClass($this->makeSchool('other'))[2];
        $this->assertDatabaseHas('announcement_recipients', ['user_id' => $student->id]);

        $this->actingAs($student)->get(route('live'))->assertOk();
        $this->actingAs($student)->get(route('live.play', $c))->assertOk();
        $this->actingAs($slow)->get(route('live.play', $c))->assertOk();
        $this->actingAs($outsider)->get(route('live.play', $c))->assertForbidden();
        $this->actingAs($teacher)->get(route('teacher.live.host', $c))->assertOk();
        $this->actingAs($teacher)->getJson(route('teacher.live.state', $c))->assertJsonPath('players', 2)->assertJsonPath('phase', 'lobby');

        // پیش از شروع جواب پذیرفته نمی‌شود
        $this->actingAs($student)->postJson(route('live.answer', $c), ['q' => 0, 'choice' => 1])->assertStatus(422);

        $this->actingAs($teacher)->postJson(route('teacher.live.go', $c), ['action' => 'start'])->assertJsonPath('phase', 'question');
        // «۳، ۲، ۱»: هنوز گزینه‌ها باز نیستند
        $this->actingAs($student)->postJson(route('live.answer', $c), ['q' => 0, 'choice' => 1])->assertStatus(422);
        $this->travel(4)->seconds();
        // دانش‌آموز جواب را پیش از نمایش نمی‌بیند
        $poll = $this->actingAs($student)->getJson(route('live.poll', $c))->assertOk()->json();
        $this->assertNull($poll['answer']);
        $this->assertSame(['۴', '۵', '۶'], $poll['question']['choices']);

        $this->actingAs($student)->postJson(route('live.answer', $c), ['q' => 0, 'choice' => 1])->assertOk();
        $this->actingAs($student)->postJson(route('live.answer', $c), ['q' => 0, 'choice' => 0])->assertOk()->assertJsonPath('already', true);
        $this->actingAs($slow)->postJson(route('live.answer', $c), ['q' => 0, 'choice' => 2])->assertOk();

        $state = $this->actingAs($teacher)->postJson(route('teacher.live.go', $c), ['action' => 'reveal'])->json();
        $this->assertSame('reveal', $state['phase']);
        $this->assertSame([0, 1, 1], $state['dist']);
        $this->assertSame(1, $state['answer']);
        $this->assertSame($student->id, $state['top'][0]['id']);
        $me = $this->actingAs($student)->getJson(route('live.poll', $c))->json();
        $this->assertTrue($me['mine']['correct']);
        $this->assertGreaterThanOrEqual(500, $me['mine']['points']);
        $this->assertSame(1, $me['me']['rank']);

        // سؤالِ دوم و پایان
        $this->actingAs($teacher)->postJson(route('teacher.live.go', $c), ['action' => 'next'])->assertJsonPath('current', 1);
        $this->travel(4)->seconds();
        $this->actingAs($student)->postJson(route('live.answer', $c), ['q' => 1, 'choice' => 0])->assertOk();
        $this->actingAs($teacher)->postJson(route('teacher.live.go', $c), ['action' => 'reveal']);
        $end = $this->actingAs($teacher)->postJson(route('teacher.live.go', $c), ['action' => 'next'])->json();
        $this->assertSame('end', $end['phase']);
        $this->assertSame(2, LiveContestPlayer::where('live_contest_id', $c->id)->where('student_id', $student->id)->value('correct'));

        // امتیاز یک بار: ۲ + ۳×۲ + ۱۵ = ۲۳ برای اول؛ ۲ + ۱۰ برای دوم
        $this->assertSame(23, (int) XpEntry::where('student_id', $student->id)->where('source_type', 'live_contest')->sum('amount'));
        $this->assertSame(12, (int) XpEntry::where('student_id', $slow->id)->where('source_type', 'live_contest')->sum('amount'));
        $this->actingAs($teacher)->postJson(route('teacher.live.go', $c), ['action' => 'end']);
        $c->fresh()->reward();
        $this->assertSame(2, XpEntry::where('source_type', 'live_contest')->count());

        $list = $this->actingAs($student)->get(route('live'))->viewData('page')['props']['contests'];
        $this->assertSame(1, $list[0]['rank']);
    }

    public function test_time_runs_out_and_late_answers_are_rejected(): void
    {
        [$teacher, , $student, $c] = $this->make(['seconds' => 5]);
        $this->actingAs($teacher)->postJson(route('teacher.live.go', $c), ['action' => 'start']);
        LiveContest::whereKey($c->id)->update(['phase_at' => LiveContest::nowMs() - 9000]);
        $this->actingAs($student)->postJson(route('live.answer', $c), ['q' => 0, 'choice' => 1])->assertStatus(422);
        $this->assertSame('reveal', $this->actingAs($student)->getJson(route('live.poll', $c))->json('phase'));
    }

    public function test_auto_mode_starts_on_time_and_advances(): void
    {
        [$teacher, , $student, $c] = $this->make(['mode' => 'auto', 'starts_at' => now()->addMinutes(5)->format('Y-m-d H:i:s')]);
        $this->assertSame('lobby', $this->actingAs($student)->getJson(route('live.poll', $c))->json('phase'));
        $this->assertGreaterThan(200, $this->actingAs($student)->getJson(route('live.poll', $c))->json('starts_in'));

        LiveContest::whereKey($c->id)->update(['starts_at' => now()->subSecond()]);
        $this->assertSame('question', $this->actingAs($student)->getJson(route('live.poll', $c))->json('phase'));
        LiveContest::whereKey($c->id)->update(['phase_at' => LiveContest::nowMs() - 21000]);
        $this->assertSame('reveal', $this->actingAs($student)->getJson(route('live.poll', $c))->json('phase'));
        LiveContest::whereKey($c->id)->update(['phase_at' => LiveContest::nowMs() - LiveContest::REVEAL_MS - 10]);
        $this->assertSame(1, $this->actingAs($student)->getJson(route('live.poll', $c))->json('current'));
    }

    public function test_validation_and_import_from_bank(): void
    {
        $this->seedRoles();
        [$teacher, $classroom] = $this->makeClass($this->makeSchool());
        $this->actingAs($teacher)->post(route('teacher.live.store'), [
            'title' => 'x', 'classroom_id' => $classroom->id, 'mode' => 'auto', 'seconds' => 20, 'questions' => $this->questions(),
        ])->assertSessionHasErrors('starts_at');
        $this->actingAs($teacher)->post(route('teacher.live.store'), [
            'title' => 'x', 'classroom_id' => $classroom->id, 'mode' => 'manual', 'seconds' => 20,
            'questions' => [['prompt' => 'بی‌گزینه', 'choices' => ['', 'الف'], 'answer' => 0]],
        ])->assertSessionHasErrors('questions');

        $this->makeQuestions($teacher, 'کسر', 4);
        $got = $this->actingAs($teacher)->getJson(route('teacher.live.import', ['source' => 'bank', 'n' => 3]))->assertOk()->json('questions');
        $this->assertCount(3, $got);
        $this->assertSame(4, count($got[0]['choices']));
        $this->assertStringStartsWith('درست', $got[0]['choices'][$got[0]['answer']]);
        $this->actingAs($teacher)->get(route('teacher.live'))->assertOk();
    }

    public function test_manual_contest_with_time_starts_itself_and_runs_to_the_end_without_the_board(): void
    {
        [$teacher, , $student, $c] = $this->make(['starts_at' => now()->addMinutes(5)->format('Y-m-d H:i')]);
        $this->actingAs($student)->getJson(route('live.poll', $c))->assertJsonPath('phase', 'lobby');

        // سرِ ساعت، بدونِ اینکه معلم تخته را باز کند
        $this->travel(5)->minutes();
        $this->actingAs($student)->getJson(route('live.poll', $c))->assertJsonPath('phase', 'question')->assertJsonPath('current', 0);
        $this->travel(4)->seconds();
        $this->actingAs($student)->postJson(route('live.answer', $c), ['q' => 0, 'choice' => 1])->assertOk();
        // تنها شرکت‌کننده جواب داد → جواب نشان داده می‌شود، بعد خودش می‌رود سؤالِ بعد
        $this->actingAs($student)->getJson(route('live.poll', $c))->assertJsonPath('phase', 'reveal');
        $this->travel(8)->seconds();
        $this->actingAs($student)->getJson(route('live.poll', $c))->assertJsonPath('current', 1);
        $this->travel(4)->seconds();
        $this->actingAs($student)->postJson(route('live.answer', $c), ['q' => 1, 'choice' => 0])->assertOk();
        $this->actingAs($student)->getJson(route('live.poll', $c));
        $this->travel(8)->seconds();
        $end = $this->actingAs($student)->getJson(route('live.poll', $c))->assertJsonPath('phase', 'end')->json();

        $this->assertSame(1, $end['me']['rank']);
        $this->assertSame(2, $end['summary']['correct']);
        $this->assertSame(100, $end['summary']['accuracy']);
        $this->assertSame(23, $end['summary']['xp']);
        $this->assertSame(23, (int) XpEntry::where('student_id', $student->id)->where('source_type', 'live_contest')->sum('amount'));
    }

    public function test_abandoned_contest_is_finished_and_points_are_recorded(): void
    {
        [$teacher, , $student, $c] = $this->make();
        $this->actingAs($teacher)->postJson(route('teacher.live.go', $c), ['action' => 'start']);
        $this->travel(4)->seconds();
        $this->actingAs($student)->postJson(route('live.answer', $c), ['q' => 0, 'choice' => 1])->assertOk();
        $this->actingAs($teacher)->postJson(route('teacher.live.go', $c), ['action' => 'reveal']);

        // معلم تخته را بست؛ کسی هم دیگر وارد نشد
        $this->travel(LiveContest::STALE_MIN + 1)->minutes();
        $this->actingAs($student)->get(route('live'))->assertOk();
        $this->assertSame('end', $c->fresh()->phase);
        $this->assertTrue(XpEntry::where('student_id', $student->id)->where('source_type', 'live_contest')->exists());
    }

    public function test_last_question_is_golden_and_worth_double(): void
    {
        $qs = array_merge($this->questions(), [['prompt' => '۹ − ۴ = ?', 'choices' => ['۵', '۶'], 'answer' => 0]]);
        [$teacher, , $student, $c] = $this->make(['questions' => $qs]);
        $c->update(['phase' => 'question', 'current' => 2, 'phase_at' => LiveContest::nowMs()]);
        $this->actingAs($teacher)->getJson(route('teacher.live.state', $c))->assertJsonPath('golden', true);
        $this->actingAs($student)->postJson(route('live.answer', $c), ['q' => 2, 'choice' => 0])->assertOk();
        $this->assertGreaterThanOrEqual(1900, (int) LiveContestPlayer::where('student_id', $student->id)->value('score'));
    }

    public function test_teacher_music_library_and_contest_music(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        [$teacher, $classroom, $student, $c] = $this->make();
        $up = $this->actingAs($teacher)->postJson(route('teacher.live.tracks.store'), [
            'track' => \Illuminate\Http\UploadedFile::fake()->create('شاد.mp3', 300, 'audio/mpeg'),
        ])->assertOk()->json('track');
        $this->assertSame('شاد', $up['title']);
        $this->assertCount(1, $this->actingAs($teacher)->get(route('teacher.live'))->viewData('page')['props']['tracks']);

        // ساختِ مسابقه با آهنگ → تخته آهنگ را دارد و پخش با نوعِ صوتی است
        $this->actingAs($teacher)->post(route('teacher.live.store'), [
            'title' => 'با آهنگ', 'classroom_id' => $classroom->id, 'mode' => 'manual', 'seconds' => 20, 'questions' => $this->questions(), 'music_track_id' => $up['id'],
        ])->assertSessionHasNoErrors();
        $withMusic = LiveContest::where('title', 'با آهنگ')->firstOrFail();
        $this->assertSame($up['id'], $withMusic->music_track_id);
        $music = $this->actingAs($teacher)->get(route('teacher.live.host', $withMusic))->viewData('page')['props']['contest']['music'];
        $this->assertSame($up['url'], $music['url']);
        $this->assertSame('audio/mpeg', $this->actingAs($teacher)->get($music['url'])->assertOk()->headers->get('Content-Type'));
        $this->actingAs($student)->get($music['url'])->assertForbidden(); // کتابخانه‌ی معلم نه
        // ولی دانش‌آموزِ همین مسابقه آهنگ را روی گوشی‌اش می‌شنود
        $this->assertSame($up['id'], $this->actingAs($student)->get(route('live.play', $withMusic))->viewData('page')['props']['contest']['music']);
        $this->actingAs($student)->getJson(route('live.poll', $withMusic))->assertJsonPath('music', $up['id']);
        $this->assertSame('audio/mpeg', $this->actingAs($student)->get(route('live.music', $withMusic))->assertOk()->headers->get('Content-Type'));
        $outsider = $this->makeClass($this->makeSchool('دیگر'))[2];
        $this->actingAs($outsider)->get(route('live.music', $withMusic))->assertForbidden();

        // بدونِ آهنگ / آهنگِ دیگر
        $this->actingAs($teacher)->postJson(route('teacher.live.music', $withMusic), ['track_id' => null])->assertOk()->assertJsonPath('music', null);
        $this->actingAs($teacher)->postJson(route('teacher.live.music', $c), ['track_id' => $up['id']])->assertOk();
        $this->assertSame($up['id'], $c->fresh()->music_track_id);

        // آهنگِ معلمِ دیگر قابلِ انتخاب نیست
        $other = $this->makeClass($teacher->school, 'پنجم')[0];
        $this->actingAs($other)->postJson(route('teacher.live.music', $c), ['track_id' => $up['id']])->assertForbidden();

        // حذفِ آهنگ → از مسابقه‌ها هم برداشته می‌شود
        $this->actingAs($teacher)->delete(route('teacher.live.tracks.destroy', $up['id']))->assertRedirect();
        $this->assertNull($c->fresh()->music_track_id);
        $this->actingAs($teacher)->postJson(route('teacher.live.tracks.store'), [
            'track' => \Illuminate\Http\UploadedFile::fake()->create('virus.exe', 10),
        ])->assertStatus(422);
    }

    /** فایلی مثلِ آپلودِ واقعیِ مرورگر: نام و نوعی که خودِ کاربر/مرورگر گزارش داده. */
    public function test_duplicate_or_stale_board_commands_never_skip_a_question(): void
    {
        [$teacher, $classroom, $student] = $this->make(['questions' => array_merge($this->questions(), [
            ['prompt' => '۳ × ۳ = ?', 'choices' => ['۹', '۶'], 'answer' => 0],
            ['prompt' => '۱۰ − ۴ = ?', 'choices' => ['۵', '۶'], 'answer' => 1],
        ])]);
        $c = \App\Models\LiveContest::firstOrFail();
        $go = fn (array $body) => $this->actingAs($teacher)->postJson(route('teacher.live.go', $c), $body)->json();
        $go(['action' => 'start']);
        $this->travel(4)->seconds();
        $go(['action' => 'reveal', 'q' => 0, 'from' => 'question']);
        $this->assertSame(['reveal', 0], [$c->fresh()->phase, $c->fresh()->current]);

        // «بعدی» دو بار (دوبار زدن / شبکه‌ی کُند) → فقط یک سؤال جلو
        $go(['action' => 'next', 'q' => 0, 'from' => 'reveal']);
        $go(['action' => 'next', 'q' => 0, 'from' => 'reveal']);
        $this->assertSame(['question', 1], [$c->fresh()->phase, $c->fresh()->current]);

        // «نمایشِ جوابِ» خودکاری که دیر رسید (برای سؤالِ قبلی) روی سؤالِ تازه اثر ندارد،
        // و در شمارشِ «۳، ۲، ۱» هم جواب نمایش داده نمی‌شود
        $go(['action' => 'reveal', 'q' => 0, 'from' => 'question']);
        $go(['action' => 'reveal']);
        $this->assertSame(['question', 1], [$c->fresh()->phase, $c->fresh()->current]);

        $this->travel(4)->seconds();
        $go(['action' => 'reveal', 'q' => 1, 'from' => 'question']);
        $go(['action' => 'next', 'q' => 1, 'from' => 'reveal']);
        $this->assertSame(['question', 2], [$c->fresh()->phase, $c->fresh()->current]);   // سؤالِ ۳ دیده می‌شود، ۴ نه
    }

    private function clientUpload(string $name, string $mime): \Illuminate\Http\UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($tmp, str_repeat("\xff\xfb\x90\x00", 5000));

        return new \Illuminate\Http\UploadedFile($tmp, $name, $mime, null, true);
    }

    public function test_song_without_extension_is_accepted_by_its_audio_type(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        [$teacher] = $this->make();
        // بعضی انتخابگرهای اندروید نامِ بی‌پسوند می‌فرستند (مثلِ msf:1234)
        $t = $this->actingAs($teacher)->postJson(route('teacher.live.tracks.store'), [
            'track' => $this->clientUpload('msf1234', 'audio/mpeg'), 'title' => 'آهنگِ شاد',
        ])->assertOk()->json('track');
        $path = \App\Models\LiveTrack::findOrFail($t['id'])->path;
        $this->assertStringEndsWith('.mp3', $path);
        $this->assertSame('audio/mpeg', $this->actingAs($teacher)->get($t['url'])->headers->get('Content-Type'));
        // نامِ بی‌پسوند با نوعِ غیرِصوتی رد می‌شود
        $this->actingAs($teacher)->postJson(route('teacher.live.tracks.store'), [
            'track' => $this->clientUpload('msf999', 'application/octet-stream'),
        ])->assertStatus(422);
    }

    public function test_contest_ends_after_chosen_minutes_and_history_survives_rerun(): void
    {
        $qs = array_merge($this->questions(), [['prompt' => '۹ − ۴ = ?', 'choices' => ['۵', '۶'], 'answer' => 0]]);
        [$teacher, , $student, $c] = $this->make(['questions' => $qs, 'end_type' => 'duration', 'duration_min' => 5]);
        $this->assertSame(5, $c->duration_min);
        $this->assertNull($c->endsAt()); // هنوز شروع نشده

        $this->assertGreaterThanOrEqual(298, $this->actingAs($teacher)->postJson(route('teacher.live.go', $c), ['action' => 'start'])->json('ends_in'));
        $this->travel(4)->seconds();
        $this->actingAs($student)->postJson(route('live.answer', $c), ['q' => 0, 'choice' => 1])->assertOk();
        $poll = $this->actingAs($student)->getJson(route('live.poll', $c))->json();
        $this->assertLessThanOrEqual(300, $poll['ends_in']);
        $this->assertStringContainsString('۵ دقیقه بعد از شروع', $poll['ends']);

        // ۵ دقیقه گذشت؛ هنوز سؤال مانده بود → پایان، رتبه نهایی، سابقه ثبت
        $this->travel(5)->minutes();
        $end = $this->actingAs($student)->getJson(route('live.poll', $c))->assertJsonPath('phase', 'end')->json();
        $this->assertTrue($end['timed_out']);
        $row = \App\Models\LiveContestResult::where('student_id', $student->id)->firstOrFail();
        $this->assertSame([1, 1, 1], [$row->round, $row->rank, $row->correct]);
        $this->assertGreaterThan(0, $row->xp);

        // اجرای دوباره: سابقه‌ی دورِ ۱ می‌ماند و دورِ ۲ جدا ثبت می‌شود
        $this->actingAs($teacher)->postJson(route('teacher.live.go', $c), ['action' => 'reset'])->assertJsonPath('phase', 'lobby');
        $this->assertSame(2, $c->fresh()->round);
        $this->assertNull($c->fresh()->endsAt());
        $this->actingAs($student)->get(route('live.play', $c))->assertOk();
        $this->actingAs($teacher)->postJson(route('teacher.live.go', $c), ['action' => 'start']);
        $this->actingAs($teacher)->postJson(route('teacher.live.go', $c), ['action' => 'end']);
        $this->assertSame(2, \App\Models\LiveContestResult::where('live_contest_id', $c->id)->count());

        $res = $this->actingAs($teacher)->getJson(route('teacher.live.results', $c))->json();
        $this->assertSame([2, 1], array_column($res['rounds'], 'round'));
        $hist = $this->actingAs($student)->get(route('live'))->viewData('page')['props']['history'];
        $this->assertSame(1, collect($hist)->where('round', 1)->first()['rank']);
    }

    public function test_contest_with_exact_end_time(): void
    {
        [$teacher, , $student, $c] = $this->make(['end_type' => 'at', 'ends_at' => now()->addMinutes(10)->format('Y-m-d H:i')]);
        $this->assertNotNull($c->ends_at);
        $this->actingAs($student)->get(route('live.play', $c))->assertOk();
        $this->actingAs($teacher)->postJson(route('teacher.live.go', $c), ['action' => 'start']);
        $this->travel(11)->minutes();
        $this->actingAs($student)->getJson(route('live.poll', $c))->assertJsonPath('phase', 'end');
        // زمانِ پایانِ گذشته پذیرفته نمی‌شود
        $this->actingAs($teacher)->post(route('teacher.live.store'), [
            'title' => 'x', 'mode' => 'manual', 'seconds' => 20, 'questions' => $this->questions(), 'end_type' => 'at', 'ends_at' => now()->subHour()->format('Y-m-d H:i'),
        ])->assertSessionHasErrors('ends_at');
    }

    public function test_teacher_can_edit_contest_any_time_and_asked_questions_stay_locked(): void
    {
        [$teacher, $classroom, $student, $c] = $this->make();
        $base = ['title' => 'مسابقه‌ی تازه', 'classroom_id' => $classroom->id, 'mode' => 'manual', 'seconds' => 30];

        // در انتظار: همه چیز عوض می‌شود و با «اعلان» بچه‌ها خبردار می‌شوند
        $this->actingAs($teacher)->get(route('teacher.live'))->assertOk()
            ->assertInertia(fn ($p) => $p->where('contests.0.edit.title', 'مسابقه‌ی ریاضی')->where('contests.0.edit.locked', 0));
        $qs = $this->questions();
        $qs[0]['prompt'] = '۳ + ۳ = ?';
        $this->actingAs($teacher)->put(route('teacher.live.update', $c), $base + [
            'questions' => $qs, 'end_type' => 'duration', 'duration_min' => 7, 'notify' => true,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $c->refresh();
        $this->assertSame('مسابقه‌ی تازه', $c->title);
        $this->assertSame(30, (int) $c->seconds);
        $this->assertSame(7, (int) $c->duration_min);
        $this->assertSame('۳ + ۳ = ?', $c->questions[0]['prompt']);
        $this->assertDatabaseHas('announcements', ['title' => '✏️ تغییر در مسابقه‌ی زنده — مسابقه‌ی تازه']);

        // معلمِ دیگر اجازه ندارد
        $other = $this->makeClass($this->makeSchool('other'))[0];
        $this->actingAs($other)->put(route('teacher.live.update', $c), $base + ['questions' => $qs])->assertForbidden();

        // در حینِ اجرا: سؤالِ پرسیده‌شده قفل است، بقیه عوض می‌شود
        $this->actingAs($teacher)->postJson(route('teacher.live.go', $c), ['action' => 'start'])->assertJsonPath('phase', 'question');
        $this->actingAs($teacher)->get(route('teacher.live'))->assertInertia(fn ($p) => $p->where('contests.0.edit.locked', 1));
        $changed = [
            ['prompt' => 'دست‌کاری‌شده', 'choices' => ['الف', 'ب'], 'answer' => 0],
            ['prompt' => 'سؤالِ دومِ تازه', 'choices' => ['x', 'y', 'z'], 'answer' => 2],
            ['prompt' => 'سؤالِ سوم', 'choices' => ['۱', '۲'], 'answer' => 1],
        ];
        $this->actingAs($teacher)->put(route('teacher.live.update', $c), $base + ['questions' => $changed])->assertSessionHasNoErrors();
        $c->refresh();
        $this->assertSame('question', $c->phase);
        $this->assertCount(3, $c->questions);
        $this->assertSame('۳ + ۳ = ?', $c->questions[0]['prompt']);
        $this->assertSame('سؤالِ دومِ تازه', $c->questions[1]['prompt']);
        $this->assertSame(2, $c->questions[1]['answer']);

        // نمی‌شود سؤال‌ها را کمتر از پرسیده‌شده‌ها کرد
        $this->actingAs($teacher)->put(route('teacher.live.update', $c), $base + ['questions' => []])->assertSessionHasErrors();

        // پس از پایان هم ویرایش برای «اجرای دوباره» ممکن است
        $c->update(['phase' => 'end']);
        $this->actingAs($teacher)->put(route('teacher.live.update', $c), array_merge($base, ['title' => 'دورِ دوم', 'questions' => $this->questions()]))->assertSessionHasNoErrors();
        $this->assertSame('دورِ دوم', $c->fresh()->title);
    }
}
