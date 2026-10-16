<?php

namespace Tests\Feature\Online;

use App\Models\OnlineParticipant;
use App\Models\OnlineRoom;
use App\Models\Setting;
use App\Models\XpEntry;
use App\Support\OnlineAccess;
use App\Support\Roles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsSchool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** «🎥 کلاسِ آنلاین»: دسترسیِ سه‌لایه، ورود، همگام‌سازی، اتصال، گفت‌وگو، نظرسنجی، امتیاز و گزارش. */
class OnlineClassTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        OnlineAccess::forget();
    }

    /** مدرسه‌ی فعال + معلمِ مجاز + یک کلاسِ زنده. */
    private function live(): array
    {
        $this->seedRoles();
        $school = $this->makeSchool();
        [$teacher, $classroom, $student] = $this->makeClass($school);
        $admin = $this->makeUser($school, Roles::SCHOOL_ADMIN);
        $super = $this->makeUser(null, Roles::SUPER_ADMIN);
        $this->actingAs($super)->put(route('admin.online.school', $school), ['enabled' => true, 'max' => 40])->assertRedirect();
        OnlineAccess::forget();
        $this->actingAs($admin)->post(route('school.online.grant'), ['user' => $teacher->id, 'field' => 'can_host', 'value' => true])->assertRedirect();

        $this->actingAs($teacher)->post(route('teacher.online.store'), ['title' => 'ریاضی آنلاین', 'classroom_id' => $classroom->id, 'duration_min' => 45])
            ->assertSessionHasNoErrors()->assertRedirect();
        $room = OnlineRoom::firstOrFail();
        $this->actingAs($teacher)->postJson(route('online.join', $room))->assertOk()->assertJsonPath('me.role', 'host');

        return [$school, $teacher, $classroom, $student, $admin, $super, $room->fresh()];
    }

    public function test_three_layer_access(): void
    {
        $this->seedRoles();
        $school = $this->makeSchool();
        [$teacher, $classroom] = $this->makeClass($school);
        $admin = $this->makeUser($school, Roles::SCHOOL_ADMIN);
        $super = $this->makeUser(null, Roles::SUPER_ADMIN);

        // مدرسه خاموش: معلم نمی‌سازد، منو نیست
        $this->actingAs($teacher)->post(route('teacher.online.store'), ['title' => 'x'])->assertForbidden();
        $this->assertFalse(OnlineAccess::menuFor($teacher));

        // ادمینِ کل روشن می‌کند؛ هنوز اجازه‌ی معلم لازم است
        $this->actingAs($super)->get(route('admin.online'))->assertOk();
        $this->actingAs($super)->put(route('admin.online.school', $school), ['enabled' => true, 'max' => 30]);
        OnlineAccess::forget();
        $this->actingAs($teacher)->post(route('teacher.online.store'), ['title' => 'x'])->assertForbidden();
        $this->actingAs($teacher)->get(route('teacher.online'))->assertOk()->assertInertia(fn ($p) => $p->where('allowed', false)->where('schoolOn', true));

        // مدیرِ مدرسه اجازه می‌دهد؛ معلمِ مدرسه‌ی دیگر را نمی‌تواند
        $this->actingAs($admin)->get(route('school.online'))->assertOk();
        $other = $this->makeClass($this->makeSchool('other'))[0];
        $this->actingAs($admin)->post(route('school.online.grant'), ['user' => $other->id, 'field' => 'can_host', 'value' => true])->assertNotFound();
        $this->actingAs($admin)->post(route('school.online.grant'), ['all' => true, 'field' => 'can_host', 'value' => true])->assertRedirect();
        $this->actingAs($teacher)->post(route('teacher.online.store'), ['title' => 'ریاضی', 'classroom_id' => $classroom->id])->assertRedirect();
        $this->assertTrue(OnlineAccess::menuFor($teacher));

        // نقش‌ها و کنترلِ دسترسیِ صفحه‌ها
        $this->actingAs($teacher)->get(route('school.online'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.online'))->assertForbidden();
        $this->assertSame(30, OnlineAccess::maxParticipants($school->id));
    }

    public function test_join_sync_signal_and_waiting_room(): void
    {
        $this->seedRoles();
        $school = $this->makeSchool();
        [$teacher, $classroom, $student] = $this->makeClass($school);
        $super = $this->makeUser(null, Roles::SUPER_ADMIN);
        $this->actingAs($super)->put(route('admin.online.school', $school), ['enabled' => true, 'max' => 40]);
        DB::table('online_hosts')->insert(['school_id' => $school->id, 'user_id' => $teacher->id, 'can_host' => true, 'supervisor' => false]);
        OnlineAccess::forget();
        $this->actingAs($teacher)->post(route('teacher.online.store'), ['title' => 'علوم', 'classroom_id' => $classroom->id]);
        $room = OnlineRoom::firstOrFail();
        $outsider = $this->makeClass($this->makeSchool('other'))[2];

        // پیش از شروع: اتاقِ انتظار
        $this->actingAs($student)->get(route('online.room', $room))->assertOk()->assertInertia(fn ($p) => $p->component('Online/Room')->where('me.role', 'student'));
        $this->actingAs($student)->postJson(route('online.join', $room))->assertOk()->assertJsonPath('waiting', true);
        $this->actingAs($outsider)->get(route('online.room', $room))->assertForbidden();

        // معلم وارد می‌شود → کلاس شروع و اعلان
        $this->actingAs($teacher)->postJson(route('online.join', $room))->assertOk();
        $this->assertSame('live', $room->fresh()->status);
        $this->assertDatabaseHas('announcement_recipients', ['user_id' => $student->id]);

        $join = $this->actingAs($student)->postJson(route('online.join', $room))->assertOk()->json();
        $this->assertSame('student', $join['me']['role']);
        $this->assertFalse($join['me']['can_speak']);
        $this->assertCount(2, $join['state']['people']);

        // دانش‌آموز فقط به معلم پیامِ اتصال می‌فرستد
        $this->actingAs($student)->postJson(route('online.signal', $room), ['to' => $teacher->id, 'kind' => 'hello'])->assertOk();
        $this->actingAs($student)->postJson(route('online.signal', $room), ['to' => $student->id + 999, 'kind' => 'offer', 'payload' => '{}'])->assertForbidden();
        $sync = $this->actingAs($teacher)->postJson(route('online.sync', $room), ['v' => 0])->assertOk()->json();
        $this->assertSame([['from' => $student->id, 'kind' => 'hello', 'payload' => null]], $sync['signals']);
        $this->assertArrayHasKey('state', $sync);
        // پیام‌ها پس از تحویل پاک می‌شوند؛ بدونِ تغییر، وضعیتِ کامل نمی‌آید
        $again = $this->actingAs($teacher)->postJson(route('online.sync', $room), ['v' => $sync['v']])->json();
        $this->assertSame([], $again['signals']);
        $this->assertArrayNotHasKey('state', $again);
        $this->actingAs($teacher)->postJson(route('online.signal', $room), ['to' => $student->id, 'kind' => 'offer', 'payload' => '{"sdp":"x","n":1}'])->assertOk();
        $this->actingAs($student)->postJson(route('online.sync', $room), ['v' => 0, 'ms' => 'ready'])->assertJsonPath('signals.0.kind', 'offer');
        $this->assertSame('ready', OnlineParticipant::where('user_id', $student->id)->value('media_state'));
    }

    public function test_chat_hand_permissions_poll_star_and_end_with_xp(): void
    {
        [$school, $teacher, $classroom, $student, $admin, , $room] = $this->live();
        $this->actingAs($student)->postJson(route('online.join', $room))->assertOk();

        // گفت‌وگو و بستنِ آن
        $this->actingAs($student)->postJson(route('online.chat', $room), ['body' => 'سلام'])->assertOk();
        $this->actingAs($teacher)->postJson(route('online.control', $room), ['action' => 'chat', 'value' => false])->assertOk();
        $this->actingAs($student)->postJson(route('online.chat', $room), ['body' => 'باز'])->assertStatus(423);
        $this->actingAs($student)->postJson(route('online.control', $room), ['action' => 'chat', 'value' => true])->assertForbidden();

        // دست بالا → اجازه‌ی صحبت (دست پایین می‌آید)
        $this->actingAs($student)->postJson(route('online.hand', $room), ['up' => true])->assertOk();
        $this->actingAs($teacher)->postJson(route('online.control', $room), ['action' => 'perm', 'user' => $student->id, 'perm' => 'can_speak', 'value' => true])->assertOk();
        $me = $this->actingAs($student)->postJson(route('online.sync', $room), ['v' => 0])->json('me');
        $this->assertTrue($me['can_speak']);
        $this->assertFalse($me['hand']);
        $this->actingAs($teacher)->postJson(route('online.control', $room), ['action' => 'mute_all'])->assertOk();
        $this->assertFalse((bool) OnlineParticipant::where('user_id', $student->id)->value('can_speak'));

        // نظرسنجی با پاسخِ درست و امتیاز
        $this->actingAs($teacher)->postJson(route('online.poll', $room), ['question' => '۲+۲؟', 'options' => ['۳', '۴'], 'correct' => 1, 'xp' => 7])->assertOk();
        $poll = \App\Models\OnlinePoll::firstOrFail();
        $state = $this->actingAs($student)->postJson(route('online.sync', $room), ['v' => 0])->json('state.poll');
        $this->assertNull($state['correct']);         // پیش از پایان، پاسخ لو نمی‌رود
        $this->assertNull($state['counts']);
        $this->actingAs($student)->postJson(route('online.poll.answer', [$room, $poll]), ['choice' => 1])->assertOk();
        $this->actingAs($student)->postJson(route('online.poll.answer', [$room, $poll]), ['choice' => 0])->assertOk()->assertJsonPath('mine', 1);
        $this->actingAs($teacher)->postJson(route('online.poll.close', [$room, $poll]))->assertOk();
        $this->assertSame(7, (int) XpEntry::where('student_id', $student->id)->where('source_type', \App\Models\OnlinePoll::class)->sum('amount'));
        $this->actingAs($student)->postJson(route('online.poll.answer', [$room, $poll]), ['choice' => 1])->assertStatus(423);

        // ستاره
        $this->actingAs($teacher)->postJson(route('online.control', $room), ['action' => 'star', 'user' => $student->id])->assertOk()->assertJsonPath('xp', 5);

        // تایمر و صحنه
        $this->actingAs($teacher)->postJson(route('online.control', $room), ['action' => 'timer', 'value' => 120])->assertOk();
        $this->assertNotNull($room->fresh()->opt('timer_end'));

        // حضور: ۱۰ دقیقه بعد کلاس تمام می‌شود
        $this->travel(4)->minutes();
        $this->actingAs($student)->postJson(route('online.sync', $room), ['v' => 0]);
        OnlineParticipant::where('user_id', $student->id)->update(['seconds' => 700]);
        $this->travel(6)->minutes();
        $this->actingAs($admin)->post(route('online.end', $room))->assertRedirect();
        $room->refresh();
        $this->assertSame('ended', $room->status);
        $this->assertTrue($room->xp_done);
        $this->assertSame(10, (int) XpEntry::where('student_id', $student->id)->where('reason', 'like', '🎥 حضور%')->sum('amount'));

        // گزارشِ حضور: معلم و مدیر بله، دانش‌آموز نه
        $rep = $this->actingAs($teacher)->getJson(route('online.report', $room))->assertOk()->json();
        $this->assertSame(1, $rep['present']);
        $this->assertSame(1, $rep['rows'][0]['stars']);
        $this->actingAs($admin)->getJson(route('online.report', $room))->assertOk();
        $this->actingAs($student)->getJson(route('online.report', $room))->assertForbidden();
        $this->actingAs($student)->postJson(route('online.sync', $room))->assertJsonPath('status', 'ended');
    }

    public function test_kick_lock_and_capacity(): void
    {
        [$school, $teacher, $classroom, $student] = $this->live();
        $room = OnlineRoom::firstOrFail();
        $late = $this->addStudent($school, $classroom);
        $this->actingAs($student)->postJson(route('online.join', $room))->assertOk();

        $this->actingAs($teacher)->postJson(route('online.control', $room), ['action' => 'lock', 'value' => true])->assertOk();
        $this->actingAs($late)->postJson(route('online.join', $room))->assertStatus(423);
        $this->actingAs($teacher)->postJson(route('online.control', $room), ['action' => 'lock', 'value' => false]);

        DB::table('online_schools')->where('school_id', $school->id)->update(['max_participants' => 1]);
        OnlineAccess::forget();
        $this->actingAs($late)->postJson(route('online.join', $room))->assertStatus(423)->assertJsonPath('error', 'ظرفیتِ کلاس پر است. کمی بعد دوباره امتحان کن.');

        $this->actingAs($teacher)->postJson(route('online.control', $room), ['action' => 'kick', 'user' => $student->id])->assertOk();
        $this->actingAs($student)->postJson(route('online.sync', $room))->assertJsonPath('kicked', true);
        $this->actingAs($student)->postJson(route('online.join', $room))->assertStatus(423);
    }

    public function test_board_assets_recording_and_livekit_token(): void
    {
        Storage::fake('local');
        [$school, $teacher, $classroom, $student] = $this->live();
        $room = OnlineRoom::firstOrFail();
        $this->actingAs($student)->postJson(route('online.join', $room))->assertOk();

        $ops = json_encode([['id' => 'a', 'k' => 'pen', 'p' => [1, 2, 3, 4]]]);
        $this->actingAs($teacher)->postJson(route('online.board.save', $room), ['page' => 0, 'bg' => 'lines', 'ops' => $ops])->assertOk()->assertJsonPath('version', 1);
        $this->actingAs($student)->postJson(route('online.board.save', $room), ['page' => 0, 'ops' => '[]'])->assertForbidden();
        $this->actingAs($student)->getJson(route('online.board', $room) . '?page=0')->assertOk()->assertJsonPath('bg', 'lines')->assertJsonPath('ops.0.k', 'pen');

        $up = $this->actingAs($teacher)->post(route('online.asset.upload', $room), ['file' => UploadedFile::fake()->image('slide.png', 800, 450)])->assertOk()->json('url');
        $this->actingAs($student)->get($up)->assertOk();

        $this->actingAs($teacher)->post(route('online.record', $room), ['seq' => 0, 'chunk' => UploadedFile::fake()->createWithContent('a.webm', 'AAAA')])->assertOk()->assertJsonPath('size', 4);
        $this->actingAs($teacher)->post(route('online.record', $room), ['seq' => 1, 'chunk' => UploadedFile::fake()->createWithContent('b.webm', 'BB')])->assertOk()->assertJsonPath('size', 6);
        $this->actingAs($student)->post(route('online.record', $room), ['seq' => 2, 'chunk' => UploadedFile::fake()->createWithContent('c.webm', 'C')])->assertForbidden();
        $this->actingAs($student)->get(route('online.recording', $room))->assertOk();

        // بدونِ تنظیم: توکن نیست
        $this->actingAs($student)->getJson(route('online.token', $room))->assertNotFound();
        Setting::put('online_media', 'livekit');
        Setting::put('online_livekit_url', 'wss://live.example.ir');
        Setting::put('online_livekit_key', 'APIkey');
        Setting::put('online_livekit_secret', \Illuminate\Support\Facades\Crypt::encryptString('s3cret'));
        $t = $this->actingAs($student)->getJson(route('online.token', $room))->assertOk()->json();
        [$h, $b, $sig] = explode('.', $t['token']);
        $this->assertSame(rtrim(strtr(base64_encode(hash_hmac('sha256', "$h.$b", 's3cret', true)), '+/', '-_'), '='), $sig);
        $claims = json_decode(base64_decode(strtr($b, '-_', '+/')), true);
        $this->assertSame((string) $student->id, $claims['sub']);
        $this->assertSame('starmah-' . $school->id . '-' . $room->id, $claims['video']['room']);
        $this->assertFalse($claims['video']['roomAdmin']);
        $this->assertSame('livekit', OnlineAccess::media());
    }

    public function test_student_list_and_live_banner(): void
    {
        [$school, $teacher, $classroom, $student] = $this->live();
        $this->actingAs($student)->get(route('online.list'))->assertOk()
            ->assertInertia(fn ($p) => $p->where('on', true)->where('rooms.0.status', 'live'));
        $this->actingAs($student)->get(route('online.list'))->assertInertia(fn ($p) => $p->where('onlineClass', true)
            ->where('onlineNow.title', 'ریاضی آنلاین'));
    }

    public function test_student_board_fallback_undo_conflict_and_kid_board_view(): void
    {
        [$school, $teacher, $classroom, $student] = $this->live();
        $room = OnlineRoom::firstOrFail();
        $this->actingAs($student)->postJson(route('online.join', $room))->assertOk();

        $op = ['id' => 'st-1', 'k' => 'pen', 'p' => [10, 10, 20, 20], 'c' => '#000', 'w' => 4];
        // بدونِ اجازه نمی‌تواند بنویسد
        $this->actingAs($student)->postJson(route('online.board.op', $room), ['page' => 0, 'kind' => 'op', 'op' => $op])->assertForbidden();

        $this->actingAs($teacher)->postJson(route('online.control', $room), ['action' => 'perm', 'user' => $student->id, 'perm' => 'can_draw', 'value' => true])->assertOk();
        $v1 = $this->actingAs($student)->postJson(route('online.board.op', $room), ['page' => 0, 'kind' => 'op', 'op' => $op])->assertOk()->json('version');
        // دوباره‌فرستادنِ همان خط چیزی را دو بار ثبت نمی‌کند
        $this->actingAs($student)->postJson(route('online.board.op', $room), ['page' => 0, 'kind' => 'op', 'op' => $op])->assertOk();
        $ops = $this->actingAs($teacher)->getJson(route('online.board', $room) . '?page=0')->assertOk()->json('ops');
        $this->assertCount(1, $ops);
        $this->assertSame($student->id, $ops[0]['by']);

        // معلم با نسخه‌ی قدیمی ذخیره می‌کند: باید ۴۰۹ بگیرد و کار بچه پاک نشود
        $this->actingAs($teacher)->postJson(route('online.board.save', $room), ['page' => 0, 'ops' => '[]', 'base' => 0])
            ->assertStatus(409)->assertJsonPath('conflict', true);
        $this->assertCount(1, $this->actingAs($teacher)->getJson(route('online.board', $room) . '?page=0')->json('ops'));
        // با نسخه‌ی درست ذخیره می‌شود و نسخه بالا می‌رود
        $this->actingAs($teacher)->postJson(route('online.board.save', $room), ['page' => 0, 'ops' => json_encode($ops), 'base' => $v1])->assertOk();

        // دانش‌آموز فقط خط خودش را پاک می‌کند
        $this->actingAs($student)->postJson(route('online.board.op', $room), ['page' => 0, 'kind' => 'undo', 'id' => 'st-1'])->assertOk();
        $this->assertCount(0, $this->actingAs($teacher)->getJson(route('online.board', $room) . '?page=0')->json('ops'));

        // نمای بچه‌ها: فقط تخته
        $this->actingAs($teacher)->postJson(route('online.control', $room), ['action' => 'kidview', 'value' => 'board'])->assertOk();
        $this->actingAs($student)->postJson(route('online.sync', $room), ['v' => 0])->assertJsonPath('state.settings.kid_view', 'board');
        $this->actingAs($teacher)->postJson(route('online.control', $room), ['action' => 'kidview', 'value' => 'normal'])->assertOk();
        $this->actingAs($student)->postJson(route('online.sync', $room), ['v' => 0])->assertJsonPath('state.settings.kid_view', 'normal');
    }

    public function test_layouts_and_live_media_flags(): void
    {
        [$school, $teacher, $classroom, $student] = $this->live();
        $room = OnlineRoom::firstOrFail();
        $this->actingAs($student)->postJson(route('online.join', $room))->assertOk();

        $cases = ['board_cam' => ['board', 'normal'], 'board' => ['board', 'board'], 'cam' => ['cam', 'normal'], 'screen' => ['screen', 'normal']];
        foreach ($cases as $layout => [$stage, $kid]) {
            $this->actingAs($teacher)->postJson(route('online.control', $room), ['action' => 'layout', 'value' => $layout])->assertOk();
            $this->actingAs($student)->postJson(route('online.sync', $room), ['v' => 0])
                ->assertJsonPath('state.settings.stage', $stage)->assertJsonPath('state.settings.kid_view', $kid);
        }
        $this->actingAs($teacher)->postJson(route('online.control', $room), ['action' => 'layout', 'value' => 'nope'])->assertStatus(422);
        $this->actingAs($student)->postJson(route('online.control', $room), ['action' => 'layout', 'value' => 'board'])->assertForbidden();

        // معلم دوربین را روشن و بعد خاموش می‌کند: وضعیت به بچه‌ها می‌رسد (تصویرِ مانده پنهان می‌شود)
        $this->actingAs($teacher)->postJson(route('online.sync', $room), ['v' => 0, 'ms' => 'ready+mic+cam'])->assertOk();
        $row = collect($this->actingAs($student)->postJson(route('online.sync', $room), ['v' => 0])->json('state.people'))->firstWhere('id', $teacher->id);
        $this->assertSame('ready+mic+cam', $row['ms']);
        $this->actingAs($teacher)->postJson(route('online.sync', $room), ['v' => 0, 'ms' => 'ready+mic'])->assertOk();
        $row = collect($this->actingAs($student)->postJson(route('online.sync', $room), ['v' => 0])->json('state.people'))->firstWhere('id', $teacher->id);
        $this->assertStringNotContainsString('+cam', $row['ms']);
    }

    public function test_powerpoint_conversion_through_converter_service(): void
    {
        [$school, $teacher, $classroom, $student, $admin, $super, $room] = $this->live();
        $pptx = UploadedFile::fake()->createWithContent('درس.pptx', 'PK-fake-pptx');

        // راه‌اندازی نشده: پیامِ روشن با راهنمای PDF
        $this->actingAs($teacher)->post(route('online.convert', $room), ['file' => $pptx], ['Accept' => 'application/json'])
            ->assertStatus(503)->assertJsonFragment(['message' => 'تبدیلِ پاورپوینت هنوز راه‌اندازی نشده است. در پاورپوینت از «File ← Save As ← PDF» فایل را PDF کنید و همان را بفرستید.']);

        $this->actingAs($super)->put(route('admin.online.settings'), ['media' => 'builtin', 'budget' => 3000, 'default_max' => 40, 'recording' => true, 'ice' => '',
            'convert_url' => 'http://10.0.0.5:7890/', 'convert_token' => 'secret-token'])->assertRedirect();
        $this->assertSame('http://10.0.0.5:7890', OnlineAccess::converter()['url']);
        $this->assertSame('secret-token', OnlineAccess::converter()['token']);
        $this->assertNotSame('secret-token', Setting::get('online_convert_token'));   // رمز ذخیره‌شده رمزنگاری شده است

        $health = 200;
        Http::fake([
            '10.0.0.5:7890/health' => function () use (&$health) { return Http::response(['ok' => $health === 200], $health); },
            '10.0.0.5:7890/convert*' => Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf']),
        ]);
        $this->actingAs($super)->postJson(route('admin.online.test-convert'))->assertOk()->assertJsonPath('ok', true);

        $res = $this->actingAs($teacher)->post(route('online.convert', $room), ['file' => $pptx], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
        Http::assertSent(fn ($r) => str_contains($r->url(), '/convert?ext=pptx') && $r->header('X-Token')[0] === 'secret-token');

        // فقط معلمِ کلاس؛ و فقط نوع‌های مجاز
        $this->actingAs($student)->postJson(route('online.join', $room))->assertOk();
        $this->actingAs($student)->post(route('online.convert', $room), ['file' => $pptx], ['Accept' => 'application/json'])->assertForbidden();
        $exe = UploadedFile::fake()->createWithContent('x.exe', 'MZ');
        $this->actingAs($teacher)->post(route('online.convert', $room), ['file' => $exe], ['Accept' => 'application/json'])->assertStatus(422);

        // رمزِ اشتباه
        $health = 401;
        $this->actingAs($super)->postJson(route('admin.online.test-convert'))->assertJsonPath('ok', false);
    }

    public function test_connection_check_page_reports_livekit_problems(): void
    {
        [$school, $teacher, $classroom, $student, $admin, $super] = $this->live();
        $this->actingAs($student)->get(route('online.check'))->assertOk()
            ->assertInertia(fn ($p) => $p->component('Online/Check')->where('mode', 'builtin'));
        $this->actingAs($student)->postJson(route('online.check.server'))->assertOk()->assertJsonPath('ok', false)->assertJsonPath('step', 'config');

        Setting::put('online_media', 'livekit');
        Setting::put('online_livekit_url', 'wss://live.example.ir');
        Setting::put('online_livekit_key', 'APIkey');
        Setting::put('online_livekit_secret', \Illuminate\Support\Facades\Crypt::encryptString('s3cret'));
        OnlineAccess::forget();

        $valid = true;
        Http::fake([
            'live.example.ir/rtc/validate*' => function () use (&$valid) { return $valid ? Http::response('success') : Http::response('invalid token: eyJabc.def.ghi', 401); },
            'live.example.ir/*' => Http::response('OK'),
        ]);
        $r = $this->actingAs($student)->postJson(route('online.check.server'))->assertOk()->assertJsonPath('ok', true)->assertJsonPath('http', 'https://live.example.ir')->json();
        $this->assertStringContainsString('.', $r['token']);
        $this->actingAs($student)->get(route('online.check'))->assertInertia(fn ($p) => $p->where('mode', 'livekit')->where('url', 'wss://live.example.ir'));

        $valid = false;
        $bad = $this->actingAs($student)->postJson(route('online.check.server'))->assertJsonPath('ok', false)->assertJsonPath('step', 'keys')->json('message');
        $this->assertStringNotContainsString('eyJabc', $bad);   // توکن در پیام نمی‌آید
    }

    public function test_each_recording_is_kept_listed_and_shared_with_students(): void
    {
        Storage::fake('local');
        [$school, $teacher, $classroom, $student, $admin, $super, $room] = $this->live();
        $chunk = fn () => UploadedFile::fake()->createWithContent('part.webm', str_repeat('x', 2048));

        // ضبطِ اول: دو تکه در یک فایل
        $rec1 = $this->actingAs($teacher)->post(route('online.record', $room), ['seq' => 0, 'chunk' => $chunk()], ['Accept' => 'application/json'])->assertOk()->json('rec');
        $this->actingAs($teacher)->post(route('online.record', $room), ['seq' => 1, 'rec' => $rec1, 'chunk' => $chunk()], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('size', 4096);
        $this->actingAs($teacher)->postJson(route('online.record.finish', $room), ['duration_ms' => 61000, 'rec' => $rec1])->assertOk();
        // ضبطِ دوم در همان کلاس: فایلِ جدا (اولی پاک نمی‌شود)
        $rec2 = $this->actingAs($teacher)->post(route('online.record', $room), ['seq' => 0, 'chunk' => $chunk()], ['Accept' => 'application/json'])->assertOk()->json('rec');
        $this->assertNotSame($rec1, $rec2);
        $recs = \App\Models\OnlineRecording::orderBy('id')->get();
        $this->assertCount(2, $recs);
        $this->assertSame(4096, (int) $recs[0]->size);
        $this->assertSame(61000, (int) $recs[0]->duration_ms);
        Storage::disk('local')->assertExists($recs[0]->path);
        Storage::disk('local')->assertExists($recs[1]->path);

        // دانش‌آموز نمی‌تواند ضبط کند
        $this->actingAs($student)->postJson(route('online.join', $room))->assertOk();
        $this->actingAs($student)->post(route('online.record', $room), ['seq' => 0, 'chunk' => $chunk()], ['Accept' => 'application/json'])->assertForbidden();

        // فهرستِ معلم و بچه‌ها
        $this->actingAs($teacher)->get(route('teacher.online'))->assertInertia(fn ($p) => $p->has('recordings', 2)->where('recordings.0.minutes', null)->where('recordings.1.minutes', 1));
        $this->actingAs($student)->get(route('online.list'))->assertInertia(fn ($p) => $p->has('recordings', 2));
        $this->actingAs($student)->get(route('online.recording', [$room, 'rec' => $rec1]))->assertOk();

        // معلم «برای بچه‌ها» را خاموش می‌کند
        $this->actingAs($teacher)->post(route('online.recording.share', $room), ['shared' => false])->assertRedirect();
        $this->actingAs($student)->get(route('online.list'))->assertInertia(fn ($p) => $p->has('recordings', 0));
        $this->actingAs($student)->get(route('online.recording', [$room, 'rec' => $rec1]))->assertNotFound();

        // حذفِ فقط یکی
        $path1 = $recs[0]->path;
        $this->actingAs($teacher)->post(route('online.recording.share', $room), ['delete' => true, 'rec' => $rec1])->assertRedirect();
        Storage::disk('local')->assertMissing($path1);
        $this->assertSame([$rec2], \App\Models\OnlineRecording::pluck('id')->all());
        $this->assertSame($recs[1]->path, $room->fresh()->recording_path);
    }

    public function test_textbook_upload_open_notes_progress_and_access(): void
    {
        Storage::fake('local');
        [$school, $teacher, $classroom, $student, $admin, $super, $room] = $this->live();
        $this->actingAs($student)->postJson(route('online.join', $room))->assertOk();
        $pdf = "%PDF-1.4\n" . str_repeat('x', 5000);

        // بارگذاریِ دوتکه‌ای
        $this->actingAs($teacher)->post(route('online.books.upload', $room), ['upload' => 'abc12345', 'seq' => 0, 'total' => 2, 'title' => 'ریاضیِ سوم',
            'chunk' => UploadedFile::fake()->createWithContent('a.bin', substr($pdf, 0, 3000))], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('done', false);
        $book = $this->actingAs($teacher)->post(route('online.books.upload', $room), ['upload' => 'abc12345', 'seq' => 1, 'total' => 2, 'title' => 'ریاضیِ سوم',
            'chunk' => UploadedFile::fake()->createWithContent('b.bin', substr($pdf, 3000))], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('done', true)->json('book');
        $model = \App\Models\OnlineBook::findOrFail($book['id']);
        $this->assertSame(strlen($pdf), (int) $model->size);
        Storage::disk('local')->assertExists($model->path);
        // فایلِ غیرِ PDF رد می‌شود؛ دانش‌آموز نمی‌تواند کتاب بگذارد
        $this->actingAs($teacher)->post(route('online.books.upload', $room), ['upload' => 'zzz12345', 'seq' => 0, 'total' => 1, 'title' => 'x',
            'chunk' => UploadedFile::fake()->createWithContent('x.bin', 'hello')], ['Accept' => 'application/json'])->assertStatus(422);
        $this->actingAs($student)->getJson(route('online.books', $room))->assertForbidden();
        $this->actingAs($student)->get(route('online.book.file', [$room, $model]))->assertForbidden();

        // باز کردن (۱۰ صفحه) → صفحه‌ی ۱ = صفحه‌ی ۱۰۰۰ تخته
        $this->actingAs($teacher)->postJson(route('online.book.open', [$room, $model]), ['pages' => 10])->assertOk()->assertJsonPath('board', 1000);
        $this->actingAs($student)->postJson(route('online.sync', $room), ['v' => 0])->assertJsonPath('state.settings.page', 1000)
            ->assertJsonPath('state.settings.book.pages', 10);
        // تصویرِ صفحه که مرورگرِ معلم ساخته
        $this->actingAs($teacher)->post(route('online.book.image', [$room, $model]), ['page' => 1, 'file' => UploadedFile::fake()->image('p1.jpg', 50, 70)], ['Accept' => 'application/json'])->assertOk();
        $bg = $this->actingAs($student)->getJson(route('online.board', $room) . '?page=1000')->assertOk()->json('bg');
        $this->assertStringContainsString('/book-img/' . $model->id . '/1', $bg);
        $this->actingAs($student)->get(parse_url($bg, PHP_URL_PATH))->assertOk();

        // نوشته روی صفحه‌ی ۳ کتاب و رفتن به آن
        $this->actingAs($teacher)->postJson(route('online.control', $room), ['action' => 'page', 'value' => 1002])->assertOk();
        $this->actingAs($teacher)->postJson(route('online.board.save', $room), ['page' => 1002, 'ops' => json_encode([['id' => 'n1', 'k' => 'pen', 'p' => [1, 2, 3, 4]]])])->assertOk();
        $this->assertSame(3, $model->fresh()->pageFor($classroom->id));
        // صفحه‌ی بیرون از کتاب
        $this->actingAs($teacher)->postJson(route('online.control', $room), ['action' => 'page', 'value' => 1010])->assertStatus(422);

        // بستن → تخته؛ تصویرِ کتاب دیگر برای بچه‌ها در دسترس نیست
        $this->actingAs($teacher)->postJson(route('online.control', $room), ['action' => 'book_close'])->assertOk();
        $this->actingAs($student)->postJson(route('online.sync', $room), ['v' => 0])->assertJsonPath('state.settings.page', 0)->assertJsonPath('state.settings.book', null);
        $this->actingAs($student)->get(parse_url($bg, PHP_URL_PATH))->assertNotFound();

        // دوباره باز کردن: از صفحه‌ی ۳ با همان نوشته
        $this->actingAs($teacher)->postJson(route('online.book.open', [$room, $model]))->assertOk()->assertJsonPath('page', 3);
        $this->assertSame('n1', $this->actingAs($student)->getJson(route('online.board', $room) . '?page=1002')->json('ops.0.id'));
        $this->actingAs($teacher)->getJson(route('online.books', $room))->assertJsonPath('books.0.page', 3)->assertJsonPath('books.0.notes', 1);

        // حذف
        $this->actingAs($teacher)->deleteJson(route('online.book.destroy', [$room, $model]))->assertOk();
        $this->assertNull(\App\Models\OnlineBook::find($model->id));
        Storage::disk('local')->assertMissing($model->path);
        $this->actingAs($student)->postJson(route('online.sync', $room), ['v' => 0])->assertJsonPath('state.settings.book', null);
    }

    public function test_poll_results_delete_reactions_playing_and_long_timer(): void
    {
        [$school, $teacher, $classroom, $student, $admin, $super, $room] = $this->live();
        $this->actingAs($student)->postJson(route('online.join', $room))->assertOk();

        // سؤال: دانش‌آموز نادرست می‌زند؛ معلم پس از پایان فهرستِ کامل را می‌بیند
        $this->actingAs($teacher)->postJson(route('online.poll', $room), ['question' => '۷×۸؟', 'options' => ['۵۶', '۵۴'], 'correct' => 0, 'xp' => 5])->assertOk();
        $pollId = \App\Models\OnlinePoll::latest('id')->value('id');
        $this->actingAs($student)->postJson(route('online.poll.answer', [$room, $pollId]), ['choice' => 1])->assertOk();
        $this->actingAs($teacher)->postJson(route('online.sync', $room), ['v' => 0])->assertJsonPath('state.poll.who', null);   // هنوز باز است
        $this->actingAs($teacher)->postJson(route('online.poll.close', [$room, $pollId]))->assertOk();
        $who = $this->actingAs($teacher)->postJson(route('online.sync', $room), ['v' => 0])->json('state.poll.who');
        $this->assertSame([['id' => $student->id, 'name' => $student->name, 'choice' => 1, 'ok' => false]], $who);
        // دانش‌آموز فهرستِ بقیه را نمی‌گیرد
        $this->actingAs($student)->postJson(route('online.sync', $room), ['v' => 0])->assertJsonPath('state.poll.who', null);
        // پاک کردن: دیگر نشان داده نمی‌شود (و سؤالِ قبلی هم برنمی‌گردد)
        $this->actingAs($student)->deleteJson(route('online.poll.destroy', [$room, $pollId]))->assertForbidden();
        $this->actingAs($teacher)->deleteJson(route('online.poll.destroy', [$room, $pollId]))->assertOk();
        $this->actingAs($student)->postJson(route('online.sync', $room), ['v' => 0])->assertJsonPath('state.poll', null);

        // شکلک‌ها، «در حالِ پخش» و تایمرِ طولانی
        $this->actingAs($teacher)->postJson(route('online.control', $room), ['action' => 'reactions', 'value' => false])->assertOk();
        $this->actingAs($teacher)->postJson(route('online.control', $room), ['action' => 'playing', 'value' => ['name' => 'سرود ملی', 'kind' => 'audio']])->assertOk();
        $this->actingAs($teacher)->postJson(route('online.control', $room), ['action' => 'timer', 'value' => 2 * 3600])->assertOk();
        $st = $this->actingAs($student)->postJson(route('online.sync', $room), ['v' => 0])->json('state.settings');
        $this->assertFalse($st['reactions']);
        $this->assertSame(['name' => 'سرود ملی', 'kind' => 'audio'], $st['playing']);
        $this->assertGreaterThan(now()->addMinutes(110)->getTimestampMs(), $st['timer_end']);
        $this->actingAs($teacher)->postJson(route('online.control', $room), ['action' => 'playing', 'value' => null])->assertOk();
        $this->actingAs($student)->postJson(route('online.sync', $room), ['v' => 0])->assertJsonPath('state.settings.playing', null);
        $this->actingAs($student)->postJson(route('online.control', $room), ['action' => 'reactions', 'value' => true])->assertForbidden();
    }

    public function test_supervisor_only_sees_and_controls_assigned_classes(): void
    {
        [$school, $teacher, $classA, $student, $admin, $super, $roomA] = $this->live();
        // کلاسِ دوم در همان مدرسه، با کلاسِ آنلاینِ زنده‌ی خودش
        [$t2, $classB] = $this->makeClass($school);
        $this->actingAs($admin)->post(route('school.online.grant'), ['user' => $t2->id, 'field' => 'can_host', 'value' => true]);
        $this->actingAs($t2)->post(route('teacher.online.store'), ['title' => 'علوم', 'classroom_id' => $classB->id]);
        $roomB = OnlineRoom::where('classroom_id', $classB->id)->firstOrFail();
        $this->actingAs($t2)->postJson(route('online.join', $roomB))->assertOk();
        $foreign = $this->makeClass($this->makeSchool('other-school'))[1];

        // مدیر «فقط ناظر» می‌سازد: کلاس لازم ندارد، ولی دستِ‌کم یک کلاسِ نظارت لازم است
        $this->actingAs($admin)->post(route('school.teachers.store'), ['kind' => 'supervisor', 'name' => 'ناظر', 'phone' => '09125550000'])
            ->assertSessionHasErrors('supervise');
        $this->actingAs($admin)->post(route('school.teachers.store'), ['kind' => 'supervisor', 'name' => 'ناظر', 'phone' => '09125550000',
            'supervise' => [$classA->id, $foreign->id]])->assertSessionHasNoErrors()->assertRedirect();
        $nazem = \App\Models\User::where('phone', '09125550000')->firstOrFail();
        $nazem->forceFill(['must_change_password' => false])->save();   // اولین ورود: تغییرِ رمز (این‌جا لازم نیست)
        $this->assertTrue($nazem->hasRole(Roles::TEACHER));
        $this->assertSame(0, \App\Models\Classroom::where('teacher_id', $nazem->id)->count());
        $this->assertSame([$classA->id], \App\Support\ClassSupervision::classroomIds($nazem));   // کلاسِ مدرسه‌ی دیگر پذیرفته نشد

        // صفحه‌ی نظارت: فقط کلاسِ A و کلاسِ آنلاینِ A
        $this->actingAs($nazem)->get(route('teacher.supervise'))->assertOk()->assertInertia(fn ($p) => $p->component('Online/Supervise')
            ->has('classes', 1)->where('classes.0.id', $classA->id)->where('classes.0.students', 1)
            ->has('rooms', 1)->where('rooms.0.id', $roomA->id)->where('onlineSup', true));
        $this->actingAs($nazem)->get(route('online.supervise'))->assertRedirect(route('teacher.supervise'));
        $this->actingAs($nazem)->get(route('teacher.online'))->assertInertia(fn ($p) => $p->where('supervisor', true)->where('allowed', false));
        $this->actingAs($nazem)->get(route('teacher.dashboard'))->assertInertia(fn ($p) => $p->where('supervises', 1));

        // کلاسِ A: ورود به‌عنوانِ ناظر و ابزارهای مدیر؛ کلاسِ B: راهی ندارد
        $this->actingAs($nazem)->postJson(route('online.join', $roomA))->assertOk()->assertJsonPath('me.role', 'observer');
        $this->actingAs($nazem)->postJson(route('online.control', $roomA), ['action' => 'mute_all'])->assertOk();
        $this->actingAs($nazem)->getJson(route('online.report', $roomA))->assertOk();
        $this->actingAs($nazem)->getJson(route('online.books', $roomA))->assertForbidden();
        $this->assertNull(OnlineAccess::roleIn($nazem, $roomB));
        $this->actingAs($nazem)->get(route('online.room', $roomB))->assertForbidden();
        $this->actingAs($nazem)->getJson(route('online.report', $roomB))->assertForbidden();
        $this->actingAs($nazem)->post(route('online.end', $roomB))->assertForbidden();

        // حضور و غیاب: کلاسِ A را دارد، B را نه
        $this->actingAs($nazem)->get(route('teacher.attendance', ['classroom_id' => $classA->id]))->assertOk()
            ->assertInertia(fn ($p) => $p->has('classrooms', 1)->where('classroom.id', $classA->id));
        $this->actingAs($nazem)->get(route('teacher.attendance.report', ['classroom_id' => $classB->id]))->assertOk()
            ->assertInertia(fn ($p) => $p->has('classrooms', 1));

        // ناظر معلم یا ناظر نمی‌سازد
        $this->actingAs($nazem)->post(route('school.teachers.store'), ['name' => 'x', 'phone' => '09120000999', 'class_name' => 'y', 'grade' => 'چهارم'])->assertForbidden();
        $this->actingAs($nazem)->put(route('school.teachers.supervise', $t2), ['classes' => [$classA->id]])->assertForbidden();

        // مدیر کلاسِ B را هم می‌سپارد، بعد نظارت را برمی‌دارد
        $this->actingAs($admin)->put(route('school.teachers.supervise', $nazem), ['classes' => [$classA->id, $classB->id]])->assertRedirect();
        \App\Support\ClassSupervision::forget();
        $this->assertSame('observer', OnlineAccess::roleIn($nazem, $roomB));
        $this->actingAs($admin)->get(route('school.teachers'))->assertInertia(fn ($p) => $p->where('teachers', fn ($t) => collect($t)->firstWhere('id', $nazem->id)['supervises'] === [$classA->id, $classB->id] || collect($t)->firstWhere('id', $nazem->id)['supervises'] === [$classB->id, $classA->id]));
        $this->actingAs($nazem)->post(route('online.end', $roomA))->assertRedirect();
        $this->assertSame('ended', $roomA->fresh()->status);

        $this->actingAs($admin)->put(route('school.teachers.supervise', $nazem), ['classes' => []])->assertRedirect();
        \App\Support\ClassSupervision::forget();
        $this->actingAs($nazem)->get(route('teacher.supervise'))->assertForbidden();
        $this->assertNull(OnlineAccess::roleIn($nazem, $roomB));
        // مدیرِ مدرسه‌ی دیگر نمی‌تواند ناظرِ این مدرسه را تغییر دهد
        $otherAdmin = $this->makeUser(\App\Models\School::where('slug', 'other-school')->first(), Roles::SCHOOL_ADMIN);
        $this->actingAs($otherAdmin)->put(route('school.teachers.supervise', $nazem), ['classes' => [$foreign->id]])->assertNotFound();
    }
}
