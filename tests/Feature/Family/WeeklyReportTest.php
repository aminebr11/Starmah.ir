<?php

namespace Tests\Feature\Family;

use App\Models\ClassroomPref;
use App\Models\ParentNote;
use App\Models\User;
use App\Models\WeeklyReport;
use App\Models\XpEntry;
use App\Services\WeeklyReportService;
use App\Support\Roles;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** «📬 گزارشِ هفتگیِ والدین»: ساخت، یادداشت، ارسال و زمان‌بندیِ دستی/تأیید/خودکار. */
class WeeklyReportTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_build_note_and_send_to_parents(): void
    {
        $this->seedRoles();
        [$teacher, $classroom, $student] = $this->makeClass($this->makeSchool());
        $parent = $this->makeUser($teacher->school, Roles::PARENT);
        $student->parents()->attach($parent->id);
        XpEntry::create(['student_id' => $student->id, 'amount' => 40, 'reason' => 'تست']);

        $this->actingAs($teacher)->get(route('teacher.weekly'))->assertOk();
        $this->actingAs($teacher)->post(route('teacher.weekly.build'), ['classroom_id' => $classroom->id])->assertSessionHasNoErrors();
        $r = WeeklyReport::where('student_id', $student->id)->firstOrFail();
        $this->assertSame('draft', $r->status);
        $this->assertSame(40, $r->data['xp']);
        $this->assertNotEmpty($r->data['highlights']);
        $this->assertNotEmpty($r->activity['steps']);

        $this->actingAs($teacher)->patch(route('teacher.weekly.update', $r), ['note' => 'در کارِ گروهی عالی بود'])->assertRedirect();
        $this->actingAs($teacher)->post(route('teacher.weekly.send'), ['ids' => [$r->id]])->assertRedirect();
        $r->refresh();
        $this->assertSame('sent', $r->status);
        $note = ParentNote::where('student_id', $student->id)->firstOrFail();
        $this->assertStringContainsString('در کارِ گروهی عالی بود', $note->body);
        $this->assertStringContainsString('۱۰ دقیقه با فرزندم', $note->body);
        $this->assertDatabaseHas('announcement_recipients', ['user_id' => $parent->id]);

        // فرستاده‌شده دوباره ساخته/فرستاده نمی‌شود
        $this->actingAs($teacher)->post(route('teacher.weekly.build'), ['classroom_id' => $classroom->id]);
        $this->actingAs($teacher)->post(route('teacher.weekly.send'), ['ids' => [$r->id]]);
        $this->assertSame(1, ParentNote::count());

        $props = $this->actingAs($parent)->get(route('parent.home'))->viewData('page')['props'];
        $this->assertCount(1, $props['weekly']);

        // معلمِ دیگر دسترسی ندارد
        $other = $this->makeClass($teacher->school)[0];
        $this->actingAs($other)->patch(route('teacher.weekly.update', $r), ['note' => 'x'])->assertForbidden();
    }

    public function test_schedule_modes(): void
    {
        $this->seedRoles();
        [$teacher, $classroom, $student] = $this->makeClass($this->makeSchool());
        $svc = app(WeeklyReportService::class);

        // چهارشنبه: هنوز موعد (پنجشنبه ۱۶) نرسیده
        Carbon::setTestNow(Carbon::parse('2026-10-07 10:00')); // Wednesday
        $this->assertSame(4, WeeklyReportService::dayIndex());
        $svc->runDue(null, true);
        $this->assertSame(0, WeeklyReport::count());

        // پنجشنبه ۱۷: حالتِ پیش‌فرض «با تأیید» → پیش‌نویس + خبر به معلم، بدونِ ارسال
        Carbon::setTestNow(Carbon::parse('2026-10-08 17:00'));
        $svc->runDue(null, true);
        $this->assertSame('draft', WeeklyReport::firstOrFail()->status);
        $this->assertDatabaseHas('announcement_recipients', ['user_id' => $teacher->id]);
        $this->assertSame(0, ParentNote::count());

        // خودکار → فرستاده می‌شود
        ClassroomPref::put($classroom->id, WeeklyReportService::KEY, ['mode' => 'auto'] + WeeklyReportService::DEFAULTS);
        $this->assertSame(1, $svc->runDue(null, true));
        $this->assertSame('sent', WeeklyReport::firstOrFail()->status);

        // دستی → هیچ کارِ خودکاری
        WeeklyReport::query()->delete();
        ClassroomPref::put($classroom->id, WeeklyReportService::KEY, ['mode' => 'manual'] + WeeklyReportService::DEFAULTS);
        $svc->runDue(null, true);
        $this->assertSame(0, WeeklyReport::count());

        $this->actingAs($teacher)->post(route('teacher.weekly.settings'), ['classroom_id' => $classroom->id, 'mode' => 'auto', 'day' => 6, 'hour' => 9, 'app' => true, 'sms' => false])
            ->assertSessionHasNoErrors();
        $this->assertSame(6, WeeklyReportService::settings($classroom->id)['day']);
    }
}
