<?php

namespace Tests\Feature\School;

use App\Models\AttendanceRecord;
use App\Models\XpEntry;
use App\Services\EarlyWarningService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** «🚨 هشدارِ زودهنگام»: افتِ مشارکت و غیبتِ زیاد دیده و به مدیر خبر داده می‌شود. */
class EarlyWarningTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    public function test_participation_drop_and_absence_are_flagged(): void
    {
        $this->seedRoles();
        $school = $this->makeSchool();
        [$teacher, $classroom, $first] = $this->makeClass($school);
        $students = collect([$first]);
        for ($i = 0; $i < 4; $i++) $students->push($this->addStudent($school, $classroom));
        [, $calm, $calmKid] = $this->makeClass($school);
        $admin = $this->makeUser($school, Roles::SCHOOL_ADMIN);

        // هفته‌های ۲ تا ۴ پیش همه فعال؛ این هفته هیچ‌کس
        foreach ($students as $s) {
            foreach ([11, 17, 24] as $ago) {
                $e = XpEntry::create(['student_id' => $s->id, 'amount' => 5, 'reason' => 'تست']);
                $e->forceFill(['created_at' => now()->subDays($ago)])->save();
            }
            AttendanceRecord::create(['school_id' => $school->id, 'classroom_id' => $classroom->id, 'student_id' => $s->id,
                'date' => now()->subDays(1)->toDateString(), 'status' => 'absent']);
            AttendanceRecord::create(['school_id' => $school->id, 'classroom_id' => $classroom->id, 'student_id' => $s->id,
                'date' => now()->subDays(2)->toDateString(), 'status' => 'present']);
        }
        XpEntry::create(['student_id' => $calmKid->id, 'amount' => 5, 'reason' => 'تست']);

        $data = app(EarlyWarningService::class)->compute($school->id);
        $kinds = collect($data['alerts'])->where('classroom_id', $classroom->id)->pluck('level', 'kind');
        $this->assertSame('crit', $kinds['participation']);
        $this->assertSame('crit', $kinds['attendance']);
        $this->assertSame('warn', $kinds['inactive']);
        $this->assertTrue(collect($data['alerts'])->where('classroom_id', $calm->id)->isEmpty());
        $this->assertSame('crit', $data['classes'][0]['level']);
        $this->assertSame(0, $data['classes'][0]['active']);

        $this->actingAs($admin)->get(route('school.early.warning'))->assertOk();
        $props = $this->actingAs($admin)->get(route('school.overview'))->assertOk()->viewData('page')['props'];
        $this->assertGreaterThan(0, $props['warning']['counts']['crit']);

        app(EarlyWarningService::class)->notifyDaily($school->id);
        app(EarlyWarningService::class)->notifyDaily($school->id);
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('announcement_recipients')->where('user_id', $admin->id)->count());

        $this->actingAs($teacher)->get(route('school.early.warning'))->assertForbidden();
    }
}
