<?php

namespace Tests\Feature\Bot;

use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\BotLink;
use App\Models\BotLog;
use App\Models\Grade;
use App\Models\GradeColumn;
use App\Support\Bot\BotApi;
use App\Support\Bot\BotConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** باتِ معلم: اتصال، حضور و غیاب، نمره، اطلاعیه، گزارش و برگردان — همه با پیش‌نمایش و تأیید. */
class TeacherBotTest extends TestCase
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

    private function setupClass(): array
    {
        $this->seedRoles();
        BotConfig::put('enabled', '1');
        BotConfig::put('bale_token', 'TEST:token');
        BotConfig::put('bale_username', 'starmah_bot');
        $school = $this->makeSchool();
        [$teacher, $classroom, $s1] = $this->makeClass($school);
        $s1->update(['name' => 'علی کریمی']);
        $s2 = $this->addStudent($school, $classroom);
        $s2->update(['name' => 'سارا محمدی']);
        $s3 = $this->addStudent($school, $classroom);
        $s3->update(['name' => 'رضا احمدی']);

        return [$teacher, $classroom, $s1, $s2, $s3];
    }

    private function hook(array $update): void
    {
        $update['update_id'] = $this->upd++;
        $this->postJson('/bot/bale/' . BotConfig::secret(), $update)->assertOk();
    }

    private function text(string $t, string $chat = '555'): void
    {
        $this->hook(['message' => ['message_id' => $this->upd, 'chat' => ['id' => $chat, 'type' => 'private'], 'from' => ['id' => $chat, 'username' => 'teach'], 'text' => $t]]);
    }

    private function press(string $data, string $chat = '555'): void
    {
        $this->hook(['callback_query' => ['id' => 'cb' . $this->upd, 'data' => $data, 'from' => ['id' => $chat],
            'message' => ['message_id' => 77, 'chat' => ['id' => $chat, 'type' => 'private']]]]);
    }

    /** آخرین دکمه‌ای که داده‌اش با این پیشوند شروع می‌شود. */
    private function lastButton(string $prefix): ?string
    {
        foreach (array_reverse(BotApi::$fake) as $call) {
            foreach ($call['params']['reply_markup']['inline_keyboard'] ?? [] as $row) {
                foreach ($row as $b) {
                    if (str_starts_with($b['callback_data'] ?? '', $prefix)) {
                        return $b['callback_data'];
                    }
                }
            }
        }

        return null;
    }

    private function lastText(): string
    {
        return (string) (end(BotApi::$fake)['params']['text'] ?? '');
    }

    private function link($teacher): void
    {
        $code = $this->actingAs($teacher)->postJson(route('bot.code'), ['platform' => 'bale'])->assertOk()->json('code');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        auth()->logout();
        $this->text('/start ' . $code);
        $this->assertTrue(BotLink::where('chat_id', '555')->where('user_id', $teacher->id)->exists());
    }

    public function test_unknown_chat_and_wrong_secret(): void
    {
        $this->setupClass();
        $this->postJson('/bot/bale/wrong-secret', ['update_id' => 1])->assertNotFound();
        $this->text('سلام');
        $this->assertStringContainsString('وصل کن', $this->lastText());
    }

    public function test_attendance_from_a_sentence_with_preview_confirm_and_undo(): void
    {
        [$teacher, $classroom, $ali, $sara, $reza] = $this->setupClass();
        $this->link($teacher);

        $this->text('همه حاضرن جز سارا، رضا ده دقیقه تأخیر داشت');
        $this->assertStringContainsString('سارا محمدی', $this->lastText());
        $this->assertSame(0, AttendanceRecord::count());           // پیش از تأیید چیزی ثبت نمی‌شود

        $this->press($this->lastButton('ok:'));
        $st = AttendanceRecord::pluck('status', 'student_id');
        $this->assertSame('present', $st[$ali->id]);
        $this->assertSame('absent', $st[$sara->id]);
        $this->assertSame('late', $st[$reza->id]);
        $this->assertTrue(Announcement::where('title', 'like', 'ثبت غیبت%')->exists());   // همان اعلانِ سایت
        $this->assertSame(1, BotLog::count());

        $this->press($this->lastButton('un:'));
        $this->assertSame(0, AttendanceRecord::count());
        $this->assertNotNull(BotLog::first()->undone_at);
    }

    public function test_attendance_board_buttons(): void
    {
        [$teacher, $classroom, $ali, $sara] = $this->setupClass();
        $this->link($teacher);
        $this->press('m:att');
        $toggle = collect(BotApi::$fake)->flatMap(fn ($c) => collect($c['params']['reply_markup']['inline_keyboard'] ?? [])->flatten(1))
            ->first(fn ($b) => str_starts_with($b['callback_data'] ?? '', 'bt:') && str_ends_with($b['callback_data'], ':' . $sara->id));
        $this->assertNotNull($toggle);
        $this->press($toggle['callback_data']);                     // حاضر → غایب
        $this->press($this->lastButton('bs:'));                     // ثبت → پیش‌نمایش
        $this->press($this->lastButton('ok:'));
        $this->assertSame('absent', AttendanceRecord::where('student_id', $sara->id)->value('status'));
        $this->assertSame('present', AttendanceRecord::where('student_id', $ali->id)->value('status'));
    }

    public function test_grades_from_a_sentence(): void
    {
        [$teacher, $classroom, $ali, $sara, $reza] = $this->setupClass();
        $this->link($teacher);
        $this->text('ریاضی کسرها توصیفی: علی خیلی خوب، سارا خوب، بقیه قابل قبول');
        $this->assertStringContainsString('ریاضی', $this->lastText());
        $this->press($this->lastButton('ok:'));

        $col = GradeColumn::firstOrFail();
        $this->assertSame('ریاضی', $col->lesson);
        $this->assertSame($classroom->id, $col->classroom_id);
        $g = Grade::where('grade_column_id', $col->id)->pluck('text', 'student_id');
        $this->assertSame('خیلی خوب', $g[$ali->id]);
        $this->assertSame('خوب', $g[$sara->id]);
        $this->assertSame('قابل قبول', $g[$reza->id]);
        $this->assertTrue(\App\Models\XpEntry::where('source_type', Grade::class)->exists());   // امتیاز مثلِ سایت

        $this->press($this->lastButton('un:'));
        $this->assertSame(0, GradeColumn::count());
    }

    public function test_announcement_to_class_and_to_one_student(): void
    {
        [$teacher, $classroom, $ali, $sara] = $this->setupClass();
        $this->link($teacher);
        $this->text('اطلاعیه: فردا کتابِ علوم بیاورید');
        $this->press($this->lastButton('ok:'));
        $ann = Announcement::where('body', 'فردا کتابِ علوم بیاورید')->firstOrFail();
        $this->assertSame(3, $ann->recipients()->count());

        $this->text('به سارا بگو دفترش را بیاورد');
        $this->press($this->lastButton('ok:'));
        $one = Announcement::where('body', 'دفترش را بیاورد')->firstOrFail();
        $this->assertSame([$sara->id], $one->recipients()->pluck('users.id')->all());
    }

    public function test_cancel_and_reports(): void
    {
        [$teacher, , , $sara] = $this->setupClass();
        $this->link($teacher);
        $this->text('غایب: سارا');
        $this->press($this->lastButton('no:'));
        $this->assertSame(0, AttendanceRecord::count());

        $this->text('گزارش امروز');
        $this->assertStringContainsString('📋', $this->lastText());
        $this->press('r:attendance');
        $this->press('r:grades');
        $this->press('r:risk');
        $this->press('r:pending');
        $this->text('گزارش سارا');
        $this->assertStringContainsString('سارا محمدی', $this->lastText());
    }

    public function test_bot_respects_school_switch_and_other_teachers_cannot_unlink(): void
    {
        [$teacher] = $this->setupClass();
        $this->link($teacher);
        BotConfig::setSchool((int) $teacher->school_id, false);
        $this->text('گزارش امروز');
        $this->assertStringContainsString('بسته', $this->lastText());

        [$other] = $this->makeClass($this->makeSchool('other'));
        $link = BotLink::firstOrFail();
        $this->actingAs($other)->delete(route('bot.links.destroy', $link))->assertForbidden();
        $this->actingAs($teacher)->delete(route('bot.links.destroy', $link))->assertRedirect();
        $this->assertSame(0, BotLink::count());
    }

    public function test_teacher_with_two_classes_is_asked_which_class(): void
    {
        [$teacher, $classroom, $ali] = $this->setupClass();
        $second = \App\Models\Classroom::create(['school_id' => $teacher->school_id, 'name' => 'پنجم ب', 'teacher_id' => $teacher->id, 'grade' => 'پنجم', 'join_code' => 'ZZZ999']);
        $this->link($teacher);
        $this->text('اطلاعیه: جلسه‌ی اولیا پنجشنبه است');
        $this->assertStringContainsString('کدام کلاس', $this->lastText());
        $choice = collect(BotApi::$fake)->flatMap(fn ($c) => collect($c['params']['reply_markup']['inline_keyboard'] ?? [])->flatten(1))
            ->first(fn ($b) => str_starts_with($b['callback_data'] ?? '', 'c:') && str_ends_with($b['callback_data'], ':' . $classroom->id));
        $this->press($choice['callback_data']);
        $this->press($this->lastButton('ok:'));
        $this->assertSame(3, Announcement::where('body', 'جلسه‌ی اولیا پنجشنبه است')->firstOrFail()->recipients()->count());
    }

    public function test_unknown_text_without_ai_gets_help(): void
    {
        [$teacher] = $this->setupClass();
        \App\Models\Setting::put('ai_provider', 'off');
        $this->link($teacher);
        $this->text('یک چیزِ نامفهوم');
        $this->assertStringContainsString('نفهمیدم', $this->lastText());
    }

    public function test_morning_brief_and_new_notifications_are_pushed(): void
    {
        [$teacher, $classroom, $ali] = $this->setupClass();
        $this->link($teacher);
        \Illuminate\Support\Carbon::setTestNow(now()->startOfWeek(\Carbon\CarbonInterface::SATURDAY)->setTime(7, 30));
        BotLink::query()->update(['notified_at' => now()->subHour()]);
        $ann = Announcement::create(['school_id' => $teacher->school_id, 'sender_id' => $ali->id, 'audience' => 'personal', 'title' => 'کاربرگِ تازه رسید', 'body' => 'از علی']);
        $ann->recipients()->sync([$teacher->id]);
        BotApi::$fake = [];

        \App\Services\Bot\BotPush::run();
        $texts = collect(BotApi::$fake)->pluck('params.text')->implode("\n---\n");
        $this->assertStringContainsString('🌅', $texts);
        $this->assertStringContainsString('کاربرگِ تازه رسید', $texts);

        // دوباره فرستاده نمی‌شوند
        BotApi::$fake = [];
        \App\Services\Bot\BotPush::run();
        $this->assertSame([], BotApi::$fake);
        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_admin_and_connect_pages(): void
    {
        [$teacher] = $this->setupClass();
        $admin = $this->makeUser(null, \App\Support\Roles::SUPER_ADMIN);
        $this->actingAs($admin)->get(route('admin.bot'))->assertOk()->assertInertia(fn ($p) => $p->component('Admin/Bot')->where('settings.bale_token', '••••oken'));
        $this->actingAs($admin)->post(route('admin.bot.save'), ['enabled' => true, 'bale_token' => '••••oken', 'bale_username' => 'x_bot'])->assertRedirect();
        $this->assertSame('TEST:token', BotConfig::token('bale'));         // توکنِ پنهان دست نخورد
        $this->actingAs($admin)->post(route('admin.bot.webhook', 'bale'))->assertRedirect();
        $this->assertTrue(collect(BotApi::$fake)->contains(fn ($c) => $c['method'] === 'setWebhook' && str_contains($c['params']['url'], '/bot/bale/')));
        $this->actingAs($teacher)->get(route('bot.connect'))->assertOk()->assertInertia(fn ($p) => $p->component('Bot/Connect')->where('allowed', true));
    }

    public function test_free_text_goes_through_ai_but_still_needs_confirmation(): void
    {
        [$teacher, $classroom, $ali, $sara] = $this->setupClass();
        \App\Models\Setting::put('ai_provider', 'openai');
        \App\Models\Setting::put('openai_key', 'sk-test');
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['choices' => [['message' => ['content' =>
            '{"action":"attendance","absent":["سارا","یک نامِ ساختگی"]}']]], 'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1]])]);
        $this->link($teacher);
        $this->text('امروز سارا خانم تشریف نیاوردن');
        $this->assertSame(0, AttendanceRecord::count());
        $this->press($this->lastButton('ok:'));
        $this->assertSame('absent', AttendanceRecord::where('student_id', $sara->id)->value('status'));
        $this->assertSame(3, AttendanceRecord::count());          // نامِ ساختگی ثبت نشد
    }

    public function test_admin_diagnose_reports_each_step(): void
    {
        $this->setupClass();
        $admin = $this->makeUser(null, \App\Support\Roles::SUPER_ADMIN);
        BotConfig::put('site_url', 'http://localhost');
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['ok' => true])]);
        $steps = $this->actingAs($admin)->postJson(route('admin.bot.diagnose', 'bale'))->assertOk()->json('steps');
        $byTitle = collect($steps)->keyBy('title');
        $this->assertTrue($byTitle['بات روشن است']['ok']);
        $this->assertTrue($byTitle['سرور به بله وصل می‌شود و توکن درست است']['ok']);
        $this->assertFalse($byTitle['نشانیِ دریافتِ پیام معتبر است']['ok']);            // localhost پذیرفته نیست
        $this->assertStringContainsString('https', $byTitle['نشانیِ دریافتِ پیام معتبر است']['fix']);
    }
}
