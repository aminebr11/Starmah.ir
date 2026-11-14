<?php

namespace Tests\Feature\Wellbeing;

use App\Models\ClassroomPref;
use App\Models\ScreenTime;
use App\Services\WellbeingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** «🌿 سلامتِ دیجیتال»: شمارشِ زمان، یادآوریِ استراحت، قفل و بازکردن با رمزِ والدین. */
class ScreenTimeTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function beatFor($student, int $minutes): array
    {
        $last = [];
        for ($i = 0; $i < $minutes; $i++) {
            Carbon::setTestNow(now()->addSeconds(60));
            $last = $this->actingAs($student)->postJson(route('presence'))->json('wellbeing') ?? $last;
        }

        return $last;
    }

    public function test_counting_break_and_daily_lock_with_parent_unlock(): void
    {
        $this->seedRoles();
        Carbon::setTestNow(Carbon::parse('2026-10-10 09:00'));
        [$teacher, $classroom, $student] = $this->makeClass($this->makeSchool());
        $student->forceFill(['settings' => ['guardian' => ['pin' => '4321']]])->save();

        $this->actingAs($teacher)->post(route('teacher.wellbeing.settings'), ['classroom_id' => $classroom->id, 'daily' => 20, 'session' => 0, 'break_every' => 10, 'break_minutes' => 2])
            ->assertSessionHasNoErrors();
        $this->assertSame(20, WellbeingService::classSettings($classroom->id)['daily']);

        $this->actingAs($student)->postJson(route('presence'))->assertOk(); // اولین ضربان: شروع
        $breaks = 0;
        for ($i = 0; $i < 12; $i++) {
            Carbon::setTestNow(now()->addSeconds(60));
            $s = $this->actingAs($student)->postJson(route('presence'))->json('wellbeing');
            $breaks += $s['break_due'] ? 1 : 0;
        }
        $this->assertSame(1, $breaks);
        $this->assertSame(720, ScreenTime::firstOrFail()->seconds);
        $this->actingAs($student)->get(route('listen'))->assertOk();

        $s = $this->beatFor($student, 9);
        $this->assertTrue($s['locked']);
        $this->assertSame('daily', $s['reason']);
        $this->assertSame(1200, ScreenTime::firstOrFail()->seconds);
        // ضربانِ بعد از قفل زمانی اضافه نمی‌کند
        $this->beatFor($student, 2);
        $this->assertSame(1200, ScreenTime::firstOrFail()->seconds);

        $this->actingAs($student)->get(route('dashboard'))->assertRedirect(route('time-up'));
        $this->actingAs($student)->get(route('my.content'))->assertRedirect(route('time-up'));
        $this->actingAs($student)->get(route('time-up'))->assertOk();
        $this->actingAs($student)->get(route('family'))->assertOk();

        // رمزِ غلط
        $this->actingAs($student)->post(route('time-up.unlock'), ['pin' => '0000'])->assertSessionHasErrors('pin');
        $this->actingAs($student)->get(route('dashboard'))->assertRedirect(route('time-up'));
        // رمزِ درست + سقفِ کمترِ والدین (۴۵ بیشتر از سقفِ معلم است → به ۲۰ محدود می‌شود)
        $this->actingAs($student)->post(route('time-up.unlock'), ['pin' => '4321', 'daily' => 45, 'session' => 10])->assertRedirect(route('dashboard'));
        $this->assertSame(0, ScreenTime::firstOrFail()->seconds);
        $this->assertSame(['daily' => 20, 'session' => 10], $student->fresh()->settings['wellbeing']);
        $this->actingAs($student)->get(route('listen'))->assertOk();

        // سقفِ «هر بار حضور»ِ والدین (۱۰ دقیقه) → قفل؛ بعد از ۲۰ دقیقه دوری، دورِ تازه
        $s = $this->beatFor($student->fresh(), 11);
        $this->assertSame('session', $s['reason']);
        Carbon::setTestNow(now()->addMinutes(21));
        $this->actingAs($student->fresh())->get(route('listen'))->assertOk();

        // معلم هم می‌تواند صفر کند؛ صفحه‌ی معلم
        $this->actingAs($teacher)->get(route('teacher.wellbeing'))->assertOk();
        $this->actingAs($teacher)->post(route('teacher.wellbeing.reset', $student))->assertRedirect();
        $this->assertSame(2, ScreenTime::firstOrFail()->resets);
    }

    public function test_no_limits_means_no_lock_and_other_teachers_cannot_reset(): void
    {
        $this->seedRoles();
        [$teacher, $classroom, $student] = $this->makeClass($this->makeSchool());
        $this->beatFor($student, 3);
        $this->actingAs($student)->get(route('listen'))->assertOk();
        $other = $this->makeClass($teacher->school)[0];
        $this->actingAs($other)->post(route('teacher.wellbeing.reset', $student))->assertForbidden();
        $this->assertNull(app(WellbeingService::class)->locked($student));
    }

    /** کلاسِ آنلاین درس است: زمانش شمرده نمی‌شود، وسطش «استراحت» نمی‌آید و با قفلِ زمان هم باز می‌ماند. */
    public function test_online_class_is_not_counted_and_stays_open_when_locked(): void
    {
        $this->seedRoles();
        Carbon::setTestNow(Carbon::parse('2026-10-10 09:00'));
        [$teacher, $classroom, $student] = $this->makeClass($this->makeSchool());
        $this->actingAs($teacher)->post(route('teacher.wellbeing.settings'), ['classroom_id' => $classroom->id, 'daily' => 5, 'session' => 0, 'break_every' => 2, 'break_minutes' => 2])
            ->assertSessionHasNoErrors();

        // ۱۰ دقیقه در کلاسِ آنلاین (نشانه‌ی برنامه یا نشانیِ صفحه): نه استراحت، نه شمارش
        for ($i = 0; $i < 10; $i++) {
            Carbon::setTestNow(now()->addSeconds(60));
            $r = $i % 2
                ? $this->actingAs($student)->postJson(route('presence'), ['in_class' => 1])
                : $this->actingAs($student)->withHeaders(['referer' => url('/online/7')])->postJson(route('presence'));
            $r->assertNoContent();
        }
        $this->assertSame(0, (int) ScreenTime::query()->sum('seconds'));
        // فهرستِ کلاس‌ها (/online) صفحه‌ی معمولی است و شمرده می‌شود
        $this->actingAs($student)->withHeaders(['referer' => url('/online')])->postJson(route('presence'))->assertOk();

        // بیرون از کلاس سقف پر می‌شود → قفل؛ ولی کلاسِ آنلاین و آزمایشِ اتصال باز می‌مانند
        $s = $this->beatFor($student, 6);
        $this->assertTrue($s['locked']);
        $this->actingAs($student)->get(route('dashboard'))->assertRedirect(route('time-up'));
        $this->assertNotSame(route('time-up'), $this->actingAs($student)->get(route('online.list'))->headers->get('Location'));
        $this->assertNotSame(route('time-up'), $this->actingAs($student)->get('/online/999')->headers->get('Location'));
        $this->assertNotSame(423, $this->actingAs($student)->postJson('/online/999/sync')->status());
        // در کلاس، ضربان هم پیامِ قفل برنمی‌گرداند
        $this->actingAs($student)->postJson(route('presence'), ['in_class' => 1])->assertNoContent();
    }

    public function test_class_polling_does_not_trigger_bot_push(): void
    {
        $skip = (new \ReflectionClass(\App\Http\Middleware\PushBotNotices::class))->getConstant('SKIP');
        foreach (['online.sync', 'online.signal', 'online.board.op', 'online.chat', 'live.state', 'presence'] as $name) {
            $this->assertSame(1, preg_match($skip, $name), $name);
        }
        foreach (['teacher.online.store', 'teacher.exams.store', 'teacher.homework.store'] as $name) {
            $this->assertSame(0, preg_match($skip, $name), $name);
        }
    }
}
