<?php

namespace Tests\Feature\Worksheets;

use App\Models\Worksheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** کاربرگِ چندصفحه‌ای: معلم چند فایل می‌گذارد، دانش‌آموزِ کلاس هر صفحه را جدا باز می‌کند. */
class WorksheetPagesTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    public function test_teacher_uploads_several_pages_and_student_sees_each(): void
    {
        $this->seedRoles();
        Storage::fake('public');
        [$teacher, $classroom, $student] = $this->makeClass($this->makeSchool());

        $this->actingAs($teacher)->post(route('teacher.worksheets.store'), [
            'title' => 'کاربرگِ دوصفحه‌ای', 'mode' => 'upload', 'theme' => 'stars', 'classroom_id' => $classroom->id, 'publish' => 1,
            'files' => [UploadedFile::fake()->image('p1.jpg', 400, 560), UploadedFile::fake()->create('p2.pdf', 30, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $w = Worksheet::firstOrFail();
        $this->assertCount(2, $w->pagePaths());
        $this->assertSame($w->pagePaths()[0], $w->file_path);
        $pages = $w->pagesData();
        $this->assertTrue($pages[0]['image']);
        $this->assertTrue($pages[1]['pdf']);

        $this->actingAs($student)->get(route('worksheet.sheet', [$w->id, 'file', 1]))->assertOk();
        $this->actingAs($student)->get(route('worksheet.sheet', [$w->id, 'file']))->assertOk();      // پیوندِ قدیمی = صفحه‌ی ۱
        $this->actingAs($student)->get(route('worksheet.sheet', [$w->id, 'file', 5]))->assertNotFound();
        $this->actingAs($student)->get(route('my.worksheet', $w))->assertOk()
            ->assertInertia(fn ($p) => $p->has('worksheet.pages', 2));

        // حذفِ کاربرگ همه‌ی صفحه‌ها را پاک می‌کند
        $paths = $w->pagePaths();
        $this->actingAs($teacher)->delete(route('teacher.worksheets.destroy', $w));
        foreach ($paths as $p) {
            Storage::disk('public')->assertMissing($p);
        }
    }

    public function test_single_file_worksheet_still_works(): void
    {
        $this->seedRoles();
        Storage::fake('public');
        [$teacher, $classroom, $student] = $this->makeClass($this->makeSchool());
        $this->actingAs($teacher)->post(route('teacher.worksheets.store'), [
            'title' => 'یک‌صفحه', 'mode' => 'upload', 'theme' => 'stars', 'classroom_id' => $classroom->id, 'publish' => 1,
            'file' => UploadedFile::fake()->create('a.pdf', 30, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $w = Worksheet::firstOrFail();
        $this->assertNull($w->files);
        $this->assertCount(1, $w->pagesData());
    }
}
