<?php

namespace Tests\Feature\Worksheets;

use App\Models\Worksheet;
use App\Models\WorksheetSubmission;
use App\Models\XpEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** کاربرگِ پرشده: فایل از مسیرِ سایت (با کنترلِ دسترسی) و تصحیحِ معلم با امتیاز. */
class WorksheetGradingTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    private function setUpSheet(): array
    {
        $this->seedRoles();
        Storage::fake('public');
        [$teacher, $classroom, $student] = $this->makeClass($this->makeSchool());
        $ws = Worksheet::create(['school_id' => $teacher->school_id, 'teacher_id' => $teacher->id, 'classroom_id' => $classroom->id,
            'title' => 'کاربرگِ عددنویسی', 'mode' => 'manual', 'is_published' => true, 'published_at' => now()]);
        $this->actingAs($student)->post(route('my.worksheet.submit', $ws), ['file' => UploadedFile::fake()->image('page.jpg', 600, 800)])
            ->assertSessionHasNoErrors();
        $sub = WorksheetSubmission::firstOrFail();

        return [$teacher, $classroom, $student, $ws, $sub];
    }

    public function test_file_is_served_through_the_app_only_to_allowed_users(): void
    {
        [$teacher, $classroom, $student, $ws, $sub] = $this->setUpSheet();
        $other = $this->addStudent($teacher->school, $classroom);

        $this->actingAs($student)->get(route('worksheet.file', [$sub->id, 'file']))->assertOk();
        $this->actingAs($teacher)->get(route('worksheet.file', [$sub->id, 'file']))->assertOk()
            ->assertHeader('Content-Disposition');
        $this->actingAs($other)->get(route('worksheet.file', [$sub->id, 'file']))->assertForbidden();

        // صفحه‌ی معلم لینکِ داخلی می‌دهد، نه /storage
        $props = $this->actingAs($teacher)->get(route('teacher.worksheets.show', $ws))->viewData('page')['props'];
        $this->assertStringContainsString('/worksheet-files/' . $sub->id, $props['submissions'][0]['url']);
        $this->assertFalse($props['submissions'][0]['graded']);
    }

    public function test_teacher_grades_with_marks_and_points_once(): void
    {
        [$teacher, , $student, $ws, $sub] = $this->setUpSheet();
        $before = $student->totalXp();

        $this->actingAs($teacher)->post(route('worksheet.grade', $sub), [
            'grade' => 'خیلی خوب', 'xp' => 15, 'feedback' => 'آفرین! فقط ارزشِ مکانیِ ۷ را دوباره ببین',
            'marked' => UploadedFile::fake()->image('marked.jpg', 600, 800),
        ])->assertOk()->assertJsonPath('submission.graded', true);

        $sub->refresh();
        $this->assertSame(['خیلی خوب', 15], [$sub->grade, $sub->grade_xp]);
        $this->assertNotNull($sub->marked_path);
        Storage::disk('public')->assertExists($sub->marked_path);
        $this->assertSame($before + 15, $student->fresh()->totalXp());
        $this->assertDatabaseHas('announcement_recipients', ['user_id' => $student->id]);

        // تصحیحِ دوباره امتیاز را دو بار نمی‌دهد، فقط عوض می‌کند
        $this->actingAs($teacher)->post(route('worksheet.grade', $sub), ['grade' => 'عالی', 'xp' => 20])->assertOk();
        $this->assertSame($before + 20, $student->fresh()->totalXp());
        $this->assertSame(1, XpEntry::where('source_type', WorksheetSubmission::GRADE_SOURCE)->count());

        // دانش‌آموز نتیجه و برگه‌ی علامت‌خورده را می‌بیند
        $mine = $this->actingAs($student)->get(route('my.worksheet', $ws))->viewData('page')['props']['submitted'];
        $this->assertSame(['عالی', 20, true], [$mine['grade'], $mine['xp'], $mine['graded']]);
        $this->assertStringContainsString('/marked', $mine['marked_url']);
        $this->actingAs($student)->get(route('worksheet.file', [$sub->id, 'marked']))->assertOk();
    }

    public function test_only_the_teacher_can_grade_and_points_are_capped(): void
    {
        [$teacher, , $student, , $sub] = $this->setUpSheet();
        $this->actingAs($student)->postJson(route('worksheet.grade', $sub), ['xp' => 50])->assertForbidden();
        $this->actingAs($teacher)->postJson(route('worksheet.grade', $sub), ['xp' => 500])->assertStatus(422);
        $this->assertSame(0, XpEntry::where('source_type', WorksheetSubmission::GRADE_SOURCE)->count());
    }
}
