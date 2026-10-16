<?php

namespace Tests\Feature\Notices;

use App\Models\Announcement;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** خلاصه‌ی اعلان‌های پیشخوان: فقط دیده‌نشده‌ها؛ «مطالعه شد» و «حذف» از پیشخوان هم برش می‌دارند — برای همه‌ی نقش‌ها. */
class DashboardNoticesTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    private function announce($school, $sender, string $title, string $audience = 'all'): Announcement
    {
        return Announcement::create(['school_id' => $school->id, 'sender_id' => $sender->id, 'title' => $title, 'body' => 'متن', 'audience' => $audience]);
    }

    public function test_student_summary_shows_only_unseen_and_respects_delete(): void
    {
        $this->seedRoles();
        $school = $this->makeSchool();
        [$teacher, , $student] = $this->makeClass($school);
        $admin = $this->makeUser($school, Roles::SCHOOL_ADMIN);
        $a1 = $this->announce($school, $admin, 'اردو');
        $a2 = $this->announce($school, $admin, 'جشن');

        $ids = fn ($u, $route) => collect($this->actingAs($u)->get(route($route))->viewData('page')['props']['dashNotices'])->pluck('id')->all();
        $this->assertEqualsCanonicalizing(['a'.$a1->id, 'a'.$a2->id], $ids($student, 'notices'));

        // دیده شد → از پیشخوان می‌رود، ولی در صفحه‌ی اعلان‌ها (به‌صورتِ خوانده‌شده) هست
        $this->actingAs($student)->post(route('notices.read', 'a'.$a1->id));
        $this->assertSame(['a'.$a2->id], $ids($student, 'notices'));
        $this->actingAs($student)->get(route('notices'))->assertInertia(fn ($p) => $p->has('notices', 2));

        // حذف → از همه‌جا می‌رود، و از Announcement::forUser هم
        $this->actingAs($student)->post(route('notices.remove'), ['keys' => ['a'.$a2->id]]);
        $this->assertSame([], $ids($student, 'notices'));
        $this->assertFalse(Announcement::forUser($student)->whereKey($a2->id)->exists());
        $this->actingAs($student)->get(route('notices'))->assertInertia(fn ($p) => $p->has('notices', 1));

        // «حذفِ همه» برای معلم: پیشخوانِ معلم (آلارم و پنل) خالی می‌شود
        $alarmIds = collect($this->actingAs($teacher)->get(route('teacher.dashboard'))->viewData('page')['props']['alarms'])->pluck('id');
        $this->assertTrue($alarmIds->contains('a'.$a1->id) && $alarmIds->contains('a'.$a2->id));
        $this->actingAs($teacher)->post(route('notices.remove'), ['all' => true]);
        $props = $this->actingAs($teacher)->get(route('teacher.dashboard'))->viewData('page')['props'];
        $this->assertSame([], collect($props['alarms'])->all());
        $this->assertSame([], collect($props['announcements'])->all());
        $this->assertSame(0, $props['unreadNotices']);
    }

    public function test_teacher_alarm_check_marks_seen_and_other_roles_get_summary(): void
    {
        $this->seedRoles();
        $school = $this->makeSchool();
        [$teacher] = $this->makeClass($school);
        $admin = $this->makeUser($school, Roles::SCHOOL_ADMIN);
        $a = $this->announce($school, $admin, 'جلسه‌ی معلمان', 'teachers');

        $alarms = collect($this->actingAs($teacher)->get(route('teacher.dashboard'))->viewData('page')['props']['alarms']);
        $this->assertTrue($alarms->contains('id', 'a'.$a->id));
        // «✓ دیدم» = مطالعه شد (در زنگوله و صفحه‌ی اعلان‌ها هم)
        $this->actingAs($teacher)->post(route('teacher.dismiss-alarm'), ['id' => 'a'.$a->id])->assertRedirect();
        $props = $this->actingAs($teacher)->get(route('teacher.dashboard'))->viewData('page')['props'];
        $this->assertFalse(collect($props['alarms'])->contains('id', 'a'.$a->id));
        $this->assertTrue(collect($props['notifications'])->firstWhere('id', 'a'.$a->id)['read']);

        // مدیرِ مدرسه هم خلاصه‌ی دیده‌نشده‌ها را دارد (پیامِ شخصی از معلم)
        $msg = $this->announce($school, $teacher, 'سؤال درباره‌ی برنامه', 'personal');
        $msg->recipients()->attach($admin->id);
        $this->assertSame(['a'.$msg->id], collect($this->actingAs($admin)->get(route('school.overview'))->viewData('page')['props']['dashNotices'])->pluck('id')->all());
        $this->actingAs($admin)->post(route('notices.read-all'));
        $this->assertSame([], $this->actingAs($admin)->get(route('school.overview'))->viewData('page')['props']['dashNotices']);
    }
}
