<?php

namespace Tests\Feature\Gallery;

use App\Models\ClassContent;
use App\Models\GalleryAlbum;
use App\Support\GalleryAlbums;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/** فیلمِ کلاس در گالری: تکه‌تکه فرستاده می‌شود، تکه‌ی تکراری دو بار چسبانده نمی‌شود، فایلِ غیرِفیلم رد می‌شود. */
class GalleryVideoTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    private function chunk(string $bytes): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('part', $bytes);
    }

    public function test_video_is_uploaded_in_chunks_into_a_new_album(): void
    {
        $this->seedRoles();
        Storage::fake('public');
        Storage::fake('local');
        [$teacher, $classroom] = $this->makeClass($this->makeSchool());
        $video = "\x00\x00\x00\x18ftypmp42" . str_repeat('A', 3000);
        $parts = str_split($video, 1200);
        $base = ['upload' => 'abcdefgh1234', 'chunks' => count($parts), 'name' => 'jashn.mp4', 'title' => 'جشنِ کلاس', 'classroom_id' => $classroom->id, 'final' => 1, 'total' => 1, 'is_new' => 1];

        foreach ($parts as $i => $p) {
            $r = $this->actingAs($teacher)->post(route('teacher.gallery.video'), $base + ['seq' => $i, 'chunk' => $this->chunk($p)], ['Accept' => 'application/json'])->assertOk();
            if ($i === 0) {
                // همان تکه دوباره (قطعیِ اینترنت) → دو بار چسبانده نمی‌شود
                $this->actingAs($teacher)->post(route('teacher.gallery.video'), $base + ['seq' => 0, 'chunk' => $this->chunk($p)], ['Accept' => 'application/json'])->assertOk();
            }
        }
        $album = GalleryAlbum::firstOrFail();
        $this->assertSame($album->id, $r->json('album_id'));
        $item = ClassContent::where('album_id', $album->id)->firstOrFail();
        $this->assertStringEndsWith('.mp4', $item->file_path);
        $this->assertSame($video, Storage::disk('public')->get($item->file_path));
        $this->assertTrue(GalleryAlbums::isVideo($item->file_path));
        // پشتیبانِ /storage: فیلم با Range (۲۰۶) تحویل می‌شود تا آیفون پخش کند
        $this->get('/storage/' . $item->file_path, ['Range' => 'bytes=0-99'])->assertStatus(206)->assertHeader('Content-Type', 'video/mp4');

        $shown = GalleryAlbums::present(collect([$item]))[0];
        $this->assertSame(1, $shown['videos']);
        $this->assertTrue($shown['photos'][0]['video']);
        $this->assertNull($shown['cover']);
        $this->assertNotNull($shown['cover_video']);
    }

    public function test_non_video_file_is_rejected(): void
    {
        $this->seedRoles();
        Storage::fake('public');
        Storage::fake('local');
        [$teacher] = $this->makeClass($this->makeSchool());
        $this->actingAs($teacher)->post(route('teacher.gallery.video'), [
            'upload' => 'zzzzzzzz9999', 'seq' => 0, 'chunks' => 1, 'name' => 'evil.mp4', 'title' => 'x', 'chunk' => $this->chunk('<?php echo 1; ?>'),
        ], ['Accept' => 'application/json'])->assertStatus(422);
        $this->actingAs($teacher)->post(route('teacher.gallery.video'), [
            'upload' => 'zzzzzzzz9998', 'seq' => 0, 'chunks' => 1, 'name' => 'a.exe', 'title' => 'x', 'chunk' => $this->chunk('MZ'),
        ], ['Accept' => 'application/json'])->assertStatus(422);
        $this->assertSame(0, ClassContent::count());
    }
}
