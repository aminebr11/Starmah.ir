<?php

namespace Tests\Feature\XpRules;

use App\Models\Mission;
use App\Models\School;
use App\Models\Worksheet;
use App\Models\WorksheetSubmission;
use App\Models\XpEntry;
use App\Support\Roles;
use App\Support\XpRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** «⚡ مدیریتِ امتیازها»: صفحه‌ی مدیرِ مدرسه و اثرِ قاعده‌ها روی امتیازهای واقعی. */
class XpRulesTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // صفحه‌ی تازه هنوز در manifestِ Vite نیست (ساخت با همکار است)
        $this->withoutVite();
    }

    private function setUpSchool(): array
    {
        $this->seedRoles();
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, Roles::SCHOOL_ADMIN);
        [$teacher, $classroom, $student] = $this->makeClass($school);

        return [$school, $admin, $teacher, $classroom, $student];
    }

    private function save($admin, array $rules)
    {
        return $this->actingAs($admin)->put(route('school.xp-rules.update'), ['rules' => $rules]);
    }

    private function submitWorksheet($teacher, $classroom, $student): void
    {
        Storage::fake('public');
        $ws = Worksheet::create(['school_id' => $teacher->school_id, 'teacher_id' => $teacher->id, 'classroom_id' => $classroom->id,
            'title' => 'کاربرگِ جمع', 'mode' => 'manual', 'is_published' => true, 'published_at' => now()]);
        $this->actingAs($student)->post(route('my.worksheet.submit', $ws), ['file' => UploadedFile::fake()->image('page.jpg', 400, 600)])
            ->assertSessionHasNoErrors();
    }

    /** مأموریت را با پاسخِ همه‌ی سؤال‌ها در بارِ اول تمام می‌کند. */
    private function playMission($student, Mission $mission): array
    {
        $props = $this->actingAs($student)->get(route('missions.play', $mission->id))->assertOk()->viewData('page')['props'];
        foreach ($props['questions'] as $q) {
            $right = collect($q['choices'])->pluck('value')->first(fn ($v) => str_starts_with($v, 'درست'));
            $this->postJson(route('missions.check'), ['token' => $props['token'], 'i' => $q['i'], 'value' => $right])->assertJson(['ok' => true]);
        }

        return $this->postJson(route('missions.submit'), ['token' => $props['token']])->assertOk()->json();
    }

    public function test_only_the_school_admin_sees_the_page_with_every_source(): void
    {
        [$school, $admin, $teacher, , $student] = $this->setUpSchool();

        $props = $this->actingAs($admin)->get(route('school.xp-rules'))->assertOk()->viewData('page')['props'];
        $keys = collect($props['groups'])->flatMap(fn ($g) => collect($g['rules'])->pluck('key'))->all();
        $this->assertEqualsCanonicalizing(array_keys(XpRules::RULES), $keys);
        $this->assertSame($school->name, $props['schoolName']);

        $this->actingAs($teacher)->get(route('school.xp-rules'))->assertForbidden();
        $this->actingAs($student)->get(route('school.xp-rules'))->assertForbidden();
        $this->actingAs($teacher)->put(route('school.xp-rules.update'), ['rules' => ['mission' => ['on' => false]]])->assertForbidden();
    }

    public function test_saving_keeps_only_overrides_and_never_touches_another_school(): void
    {
        [$school, $admin] = $this->setUpSchool();
        $other = $this->makeSchool('other');
        $otherAdmin = $this->makeUser($other, Roles::SCHOOL_ADMIN);
        $other->forceFill(['xp_rules' => ['edu_game' => ['pct' => 200]]])->save();

        $this->save($admin, [
            'mission' => ['on' => true, 'pct' => 150],
            'worksheet_submit' => ['on' => true, 'amount' => 30],
            'audio_submit' => ['on' => false, 'amount' => 5],
            'teacher_manual' => ['on' => true, 'cap' => 20],
            'content' => ['on' => true, 'pct' => 100],   // همان پیش‌فرض → ذخیره نمی‌شود
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame([
            'mission' => ['pct' => 150],
            'worksheet_submit' => ['amount' => 30],
            'audio_submit' => ['on' => false],
            'teacher_manual' => ['cap' => 20],
        ], $school->fresh()->xp_rules);
        $this->assertSame(['edu_game' => ['pct' => 200]], $other->fresh()->xp_rules);

        // صفحه‌ی هر مدیر فقط تنظیمِ مدرسه‌ی خودش را نشان می‌دهد
        $rule = fn ($props, $key) => collect($props['groups'])->flatMap(fn ($g) => $g['rules'])->firstWhere('key', $key);
        $mine = $this->actingAs($admin)->get(route('school.xp-rules'))->viewData('page')['props'];
        $theirs = $this->actingAs($otherAdmin)->get(route('school.xp-rules'))->viewData('page')['props'];
        $this->assertSame(150, $rule($mine, 'mission')['value']['pct']);
        $this->assertSame(100, $rule($mine, 'edu_game')['value']['pct']);
        $this->assertSame(100, $rule($theirs, 'mission')['value']['pct']);
        $this->assertSame(200, $rule($theirs, 'edu_game')['value']['pct']);

        // اعتبارسنجی: بیرون از بازه و کلیدِ ناشناخته
        $this->save($admin, ['mission' => ['on' => true, 'pct' => 500]])->assertSessionHasErrors('rules.mission.pct');
        $this->save($admin, ['hack' => ['on' => true]])->assertSessionHasErrors('rules');
        $this->assertSame(150, XpRules::rulesFor($school->id)['mission']['pct']);
    }

    public function test_disabled_source_creates_no_xp_entry(): void
    {
        [, $admin, $teacher, $classroom, $student] = $this->setUpSchool();
        $this->save($admin, ['worksheet_submit' => ['on' => false]])->assertSessionHasNoErrors();

        // صفحه‌ی دانش‌آموز هم دیگر وعده‌ی امتیاز نمی‌دهد
        $this->submitWorksheet($teacher, $classroom, $student);
        $this->assertSame(0, XpEntry::where('student_id', $student->id)->where('source_type', WorksheetSubmission::class)->count());
        $this->assertTrue((bool) WorksheetSubmission::firstOrFail()->submit_xp);
        $ws = Worksheet::firstOrFail();
        $this->assertSame(0, $this->actingAs($student)->get(route('my.worksheet', $ws))->viewData('page')['props']['submitXp']);
    }

    public function test_fixed_amount_override_and_defaults_untouched_elsewhere(): void
    {
        [$school, $admin, $teacher, $classroom, $student] = $this->setUpSchool();
        $this->save($admin, ['worksheet_submit' => ['on' => true, 'amount' => 40]])->assertSessionHasNoErrors();
        $this->submitWorksheet($teacher, $classroom, $student);
        $this->assertSame(40, (int) XpEntry::where('student_id', $student->id)->where('source_type', WorksheetSubmission::class)->sum('amount'));

        // مدرسه‌ی بی‌تنظیم: همان مقدارِ پیش‌فرضِ سیستم (۱۵)
        [$t2, $c2, $s2] = $this->makeClass($this->makeSchool('plain'));
        $this->submitWorksheet($t2, $c2, $s2);
        $this->assertSame(15, (int) XpEntry::where('student_id', $s2->id)->where('source_type', WorksheetSubmission::class)->sum('amount'));
    }

    public function test_percentage_multiplier_is_applied_to_a_mission(): void
    {
        [$school, $admin, $teacher, $classroom, $student] = $this->setUpSchool();
        $mission = $this->makeMission($teacher, $classroom, $this->makeQuestions($teacher, 'ضرب', 2), ['xp_reward' => 20]);
        $this->save($admin, ['mission' => ['on' => true, 'pct' => 50]])->assertSessionHasNoErrors();

        // تخته‌ی مأموریت عددِ واقعی را نشان می‌دهد
        $cards = $this->actingAs($student)->get(route('missions'))->viewData('page')['props']['missions'];
        $this->assertSame(10, $cards[0]['xp_reward']);

        $res = $this->playMission($student, $mission);
        $this->assertSame(10, $res['xp']);
        $this->assertSame(10, (int) XpEntry::where('student_id', $student->id)->where('source_type', Mission::class)->sum('amount'));
    }

    public function test_disabled_mission_gives_nothing_but_still_completes(): void
    {
        [, $admin, $teacher, $classroom, $student] = $this->setUpSchool();
        $mission = $this->makeMission($teacher, $classroom, $this->makeQuestions($teacher, 'تقسیم', 2));
        $this->save($admin, ['mission' => ['on' => false]])->assertSessionHasNoErrors();

        $res = $this->playMission($student, $mission);
        $this->assertSame(0, $res['xp']);
        $this->assertSame(0, XpEntry::where('student_id', $student->id)->where('source_type', Mission::class)->count());
        $this->assertDatabaseHas('mission_completions', ['mission_id' => $mission->id, 'student_id' => $student->id, 'xp_awarded' => 0]);
    }

    public function test_reset_returns_to_defaults(): void
    {
        [$school, $admin, $teacher, $classroom, $student] = $this->setUpSchool();
        $this->save($admin, ['worksheet_submit' => ['on' => false], 'mission' => ['on' => true, 'pct' => 0]])->assertSessionHasNoErrors();
        $this->assertNotNull($school->fresh()->xp_rules);

        $this->actingAs($admin)->post(route('school.xp-rules.reset'))->assertRedirect();
        $this->assertNull($school->fresh()->xp_rules);

        $this->submitWorksheet($teacher, $classroom, $student);
        $this->assertSame(15, (int) XpEntry::where('student_id', $student->id)->where('source_type', WorksheetSubmission::class)->sum('amount'));
    }

    public function test_penalties_keep_their_sign(): void
    {
        [$school, $admin, $teacher, $classroom, $student] = $this->setUpSchool();
        $this->save($admin, [
            'attendance_absent' => ['on' => true, 'amount' => 6],
            'attendance_late' => ['on' => false],
            'discipline_negative' => ['on' => true, 'pct' => 200],
            'teacher_manual' => ['on' => true, 'cap' => 10],
        ])->assertSessionHasNoErrors();

        // غیبت: ۶ امتیاز کم می‌شود (نه اضافه)
        $late = $this->addStudent($school, $classroom);
        $this->actingAs($teacher)->post(route('teacher.attendance.store'), [
            'classroom_id' => $classroom->id, 'date' => now()->toDateString(),
            'records' => [['student_id' => $student->id, 'status' => 'absent'], ['student_id' => $late->id, 'status' => 'late']],
        ])->assertSessionHasNoErrors();
        $this->assertSame(-6, (int) XpEntry::where('student_id', $student->id)->sum('amount'));
        $this->assertSame(0, XpEntry::where('student_id', $late->id)->count(), 'تأخیرِ خاموش هیچ ردیفی نمی‌سازد');

        // امتیازِ دستیِ منفی: سقف روی اندازه اثر دارد، علامت می‌ماند
        $this->actingAs($teacher)->post(route('teacher.points.adjust'), ['student_id' => $late->id, 'amount' => -50, 'reason' => 'بی‌نظمی'])
            ->assertSessionHasNoErrors();
        $this->assertSame(-10, (int) XpEntry::where('student_id', $late->id)->sum('amount'));

        // ضریب هرگز علامت را برعکس نمی‌کند
        $this->assertSame(-10, XpRules::apply($student, 'discipline_negative', -5));
        $this->assertSame(-6, XpRules::apply($student, 'attendance_absent', -3));
        $this->assertSame(10, XpRules::apply($student, 'teacher_manual', 30));
        $this->assertSame(0, XpRules::apply($student, 'attendance_late', -1));
        // دانش‌آموزِ بدونِ مدرسه یا کلیدِ ناشناخته: بدونِ تغییر
        $this->assertSame(-3, XpRules::apply(null, 'attendance_absent', -3));
        $this->assertSame(7, XpRules::apply($student, 'unknown_key', 7));
    }

    public function test_point_batch_is_refused_when_disabled(): void
    {
        [, $admin, $teacher, , $student] = $this->setUpSchool();
        $this->save($admin, ['point_batch' => ['on' => false]])->assertSessionHasNoErrors();

        $this->actingAs($teacher)->post(route('teacher.points.give'), ['student_ids' => [$student->id], 'amount' => 5, 'reason' => 'همکاری'])
            ->assertSessionHasErrors('amount');
        $this->assertSame(0, XpEntry::where('student_id', $student->id)->count());
    }

    public function test_stats_show_last_30_days_for_this_school_only(): void
    {
        [$school, $admin, $teacher, $classroom, $student] = $this->setUpSchool();
        $this->submitWorksheet($teacher, $classroom, $student);
        [$t2, $c2, $s2] = $this->makeClass($this->makeSchool('far'));
        $this->submitWorksheet($t2, $c2, $s2);

        $props = $this->actingAs($admin)->get(route('school.xp-rules'))->viewData('page')['props'];
        $rule = collect($props['groups'])->flatMap(fn ($g) => $g['rules'])->firstWhere('key', 'worksheet_submit');
        $this->assertSame(['count' => 1, 'total' => 15], $rule['stats']);
    }
}
