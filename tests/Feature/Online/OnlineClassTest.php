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
}
