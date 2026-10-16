<?php

namespace Tests\Feature\Assistant;

use App\Models\Announcement;
use App\Models\EduGame;
use App\Models\EduGameQuestion;
use App\Models\XpEntry;
use App\Support\MahooAbilities;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** ماهو کار انجام می‌دهد: فقط با تأییدِ کاربر و فقط اگر ادمینِ کل و مدیرِ مدرسه اجازه داده باشند. */
class MahooActionsTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    private function ask($user, string $msg): array
    {
        return $this->actingAs($user)->postJson(route('assistant.chat'), ['message' => $msg])->assertOk()->json();
    }

    public function test_teacher_awards_points_and_messages_class_only_after_confirm(): void
    {
        $this->seedRoles();
        $school = $this->makeSchool();
        [$teacher, $classroom, $sara] = $this->makeClass($school);
        $sara->update(['name' => 'سارا محمدی']);
        $other = $this->makeClass($school)[2];
        $other->update(['name' => 'سارا رضایی']);   // دانش‌آموزِ کلاسِ دیگر — نباید پیدا شود

        $r = $this->ask($teacher, '۵ امتیاز به سارا بده بابت نقاشی قشنگش');
        $this->assertSame('confirm', $r['action']['type']);
        $this->assertSame('award', $r['action']['kind']);
        $this->assertStringContainsString('سارا محمدی', implode(' ', $r['action']['lines']));
        $this->assertSame(0, XpEntry::count());                       // هنوز هیچ کاری نشده

        $this->actingAs($sara)->postJson(route('assistant.act'), ['token' => $r['action']['token']])->assertJsonPath('ok', false);   // توکنِ کسِ دیگر
        $this->actingAs($teacher)->postJson(route('assistant.act'), ['token' => $r['action']['token']])->assertJsonPath('ok', true);
        $this->assertSame(5, (int) XpEntry::where('student_id', $sara->id)->sum('amount'));

        // پیام به کلاس
        $r = $this->ask($teacher, 'به کلاس بگو: فردا کتاب علوم بیارید');
        $this->assertSame('class_notice', $r['action']['kind']);
        $this->actingAs($teacher)->postJson(route('assistant.act'), ['token' => $r['action']['token']])->assertJsonPath('ok', true);
        $ann = Announcement::latest('id')->firstOrFail();
        $this->assertSame('personal', $ann->audience);
        $this->assertSame([$sara->id], $ann->recipients()->pluck('users.id')->all());

        // مدیرِ مدرسه امتیازدادن با ماهو را برای معلم‌ها می‌بندد
        $admin = $this->makeUser($school, Roles::SCHOOL_ADMIN);
        $set = MahooAbilities::school($school->id);
        $set['abilities']['award']['teacher'] = false;
        $this->actingAs($admin)->put(route('school.mahoo.save'), ['abilities' => $set['abilities'], 'entrance' => true, 'sound' => true, 'voice' => true,
            'access' => ['students' => true, 'teachers' => true, 'ai' => true]])->assertRedirect();
        $r = $this->ask($teacher, 'به سارا ۵ امتیاز بده');
        $this->assertArrayNotHasKey('action', $r);
        $this->assertStringContainsString('بسته', $r['reply']);
        $this->assertNotContains('award', array_column($this->actingAs($teacher)->getJson(route('assistant.hello'))->json('abilities'), 'key'));
    }

    public function test_global_switch_student_tools_and_settings_access(): void
    {
        $this->seedRoles();
        $school = $this->makeSchool();
        [$teacher, , $student] = $this->makeClass($school);
        $super = $this->makeUser(null, Roles::SUPER_ADMIN);
        $admin = $this->makeUser($school, Roles::SCHOOL_ADMIN);

        $this->assertSame('timer', $this->ask($student, 'تایمر ۲۰ دقیقه')['action']['type']);
        $this->assertSame(20, $this->ask($student, 'تایمر ۲۰ دقیقه')['action']['minutes']);
        $this->assertSame('riddle', $this->ask($student, 'یه چیستان بگو')['action']['type']);
        $this->assertSame('navigate', $this->ask($student, 'برو به کارنامه')['action']['type']);
        // دانش‌آموز نمی‌تواند امتیاز بدهد
        $this->assertArrayNotHasKey('action', $this->ask($student, 'به علی ۵ امتیاز بده'));

        // ادمینِ کل تایمر را برای کلِ سامانه خاموش می‌کند
        $this->actingAs($super)->get(route('admin.mahoo'))->assertOk()->assertInertia(fn ($p) => $p->component('Mahoo/Settings')->where('scope', 'global'));
        $g = MahooAbilities::global();
        $g['abilities']['timer'] = false;
        $this->actingAs($super)->put(route('admin.mahoo.save'), ['abilities' => $g['abilities'], 'entrance' => false, 'sound' => true, 'voice' => false])->assertRedirect();
        $this->assertArrayNotHasKey('action', $this->ask($student, 'تایمر ۲۰ دقیقه'));
        $this->actingAs($student)->get(route('dashboard'));
        $this->assertFalse(MahooAbilities::look($student)['entrance']);

        // مدیرِ مدرسه صفحه‌ی خودش را دارد؛ معلم و دانش‌آموز ندارند؛ مدیر نمی‌تواند تنظیمِ سراسری را عوض کند
        $this->actingAs($admin)->get(route('school.mahoo'))->assertOk()->assertInertia(fn ($p) => $p->where('scope', 'school')->where('global.abilities.timer', false));
        $this->actingAs($teacher)->get(route('school.mahoo'))->assertForbidden();
        $this->actingAs($admin)->put(route('admin.mahoo.save'), ['abilities' => []])->assertForbidden();

        // صدای خوش‌آمد وقتی سرویسِ صدا نیست: بی‌خطا و بی‌نشانی
        $this->actingAs($student)->postJson(route('assistant.voice'))->assertOk()->assertJsonPath('url', null);
    }

    public function test_school_notice_and_remedial_game_from_chat(): void
    {
        $this->seedRoles();
        $school = $this->makeSchool();
        [$teacher, $classroom, $ali] = $this->makeClass($school);
        $admin = $this->makeUser($school, Roles::SCHOOL_ADMIN);

        $r = $this->ask($admin, 'اطلاعیه بده برای معلم ها: جلسه ساعت ۱۰ در دفتر');
        $this->assertSame('school_notice', $r['action']['kind']);
        $this->actingAs($admin)->postJson(route('assistant.act'), ['token' => $r['action']['token']])->assertJsonPath('ok', true);
        $this->assertSame('teachers', Announcement::latest('id')->first()->audience);

        // آزمونِ جبرانی
        $this->assertStringContainsString('لازم نیست', $this->ask($teacher, 'یک آزمون جبرانی بساز')['reply']);
        $bank = $this->makeQuestions($teacher, 'اعداد', 3);
        $game = EduGame::create(['school_id' => $school->id, 'teacher_id' => $teacher->id, 'template_key' => 'snakes', 'title' => 'اعداد', 'subject' => 'ریاضی', 'grade' => 'چهارم', 'status' => 'published']);
        foreach ($bank as $k => $b) {
            EduGameQuestion::create(['edu_game_id' => $game->id, 'type' => 'mc', 'prompt' => $b->prompt, 'choices' => $b->choices, 'points' => 10, 'sort' => $k]);
        }
        $this->actingAs($ali)->post(route('gameworld.finish', $game), ['answers' => [0, 1, 0]]);
        $r = $this->ask($teacher, 'از سوال های سخت یک آزمون جبرانی بساز');
        $this->assertSame('remedial', $r['action']['kind']);
        $done = $this->actingAs($teacher)->postJson(route('assistant.act'), ['token' => $r['action']['token']])->assertJsonPath('ok', true)->json();
        $new = EduGame::latest('id')->first();
        $this->assertNotSame($game->id, $new->id);
        $this->assertSame('draft', $new->status);
        $this->assertSame(1, $new->questions()->count());
        $this->assertStringContainsString('/studio/' . $new->id . '/edit', $done['href']);
    }

    public function test_ai_reply_action_line_becomes_a_checked_proposal(): void
    {
        $this->seedRoles();
        [$teacher, , $sara] = $this->makeClass($this->makeSchool());
        $sara->update(['name' => 'سارا محمدی']);
        $svc = app(\App\Services\MahooActions::class);

        [$text, $action] = $svc->fromAi($teacher, "حتماً! تأیید کنید 🌟\n<<ACTION {\"type\":\"award\",\"student\":\"سارا\",\"amount\":3}>>");
        $this->assertSame('حتماً! تأیید کنید 🌟', $text);
        $this->assertSame('award', $action['kind']);
        // کاری که مجاز نیست (دانش‌آموز → امتیاز) نادیده گرفته می‌شود و فقط متن می‌ماند
        [$text2, $action2] = $svc->fromAi($sara, "باشه <<ACTION {\"type\":\"award\",\"student\":\"سارا\",\"amount\":3}>>");
        $this->assertSame('باشه', $text2);
        $this->assertNull($action2);
        $this->assertStringContainsString('award', \App\Services\MahooActions::aiGuide($teacher));
        $this->assertStringNotContainsString('award', \App\Services\MahooActions::aiGuide($sara));
    }
}
