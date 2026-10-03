<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ClassContent;
use App\Models\GalleryAlbum;
use App\Support\ContentRelease;
use App\Support\GalleryAlbums;
use App\Support\Jalali;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * گالریِ آلبومی: هر آلبوم یک «پوشه» با کاور است و عکس‌ها داخلش به ترتیبِ
 * زمانِ آپلود. معلم می‌تواند آلبوم را ویرایش، مخفی یا حذف کند و داخلش عکس
 * اضافه/حذف/تغییرِ عنوان دهد یا کاور را عوض کند.
 */
class GalleryController extends Controller
{
    use \App\Http\Controllers\Concerns\StoresUploads;

    private const EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /**
     * آپلودِ یک تکه از عکس‌ها (JSON). مرورگر عکس‌های زیاد را چند تکه می‌فرستد:
     * تکه‌ی اول آلبوم را می‌سازد (یا album_id آلبومِ موجود را می‌دهد) و شناسه‌اش
     * برمی‌گردد؛ تکه‌ی آخر (final) اعلانِ یک‌باره را می‌فرستد.
     */
    public function upload(Request $request): JsonResponse
    {
        $teacher = $request->user();
        $data = $request->validate([
            'album_id'     => 'nullable|integer',
            'title'        => 'required_without:album_id|nullable|string|max:150',
            'description'  => 'nullable|string|max:2000',
            'classroom_id' => 'nullable|integer|exists:classrooms,id',
            'publish_at'   => 'nullable|date',
            'files'        => 'required|array|min:1|max:20',
            'files.*'      => 'file|max:30720',
            'cover_local'  => 'nullable|integer|min:0|max:19',
            'final'        => 'nullable|boolean',
            'total'        => 'nullable|integer|min:1|max:500',
            'is_new'       => 'nullable|boolean',
        ], [
            'title.required_without' => 'عنوانِ آلبوم را بنویسید.',
            'files.required' => 'دستِ‌کم یک عکس انتخاب کنید.',
            'files.max' => 'در هر بار حداکثر ۲۰ عکس فرستاده می‌شود.',
            'files.*.max' => 'حجمِ یکی از عکس‌ها بیش از ۳۰ مگابایت است.',
            'files.*.file' => 'یکی از فایل‌ها درست بارگذاری نشد.',
        ]);

        foreach ($request->file('files') as $f) {
            if (! $this->extensionSafe($f, self::EXT)) {
                return response()->json(['message' => 'فقط عکس (JPG، PNG، WEBP، GIF) پذیرفته می‌شود: ' . $f->getClientOriginalName()], 422);
            }
        }

        if (! empty($data['album_id'])) {
            $album = GalleryAlbum::where('teacher_id', $teacher->id)->findOrFail($data['album_id']);
        } else {
            $album = GalleryAlbum::create([
                'teacher_id' => $teacher->id,
                'school_id' => $teacher->school_id,
                'classroom_id' => $data['classroom_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'publish_at' => $data['publish_at'] ?? null,
                'is_visible' => true,
            ]);
        }

        $ids = [];
        foreach ($request->file('files') as $i => $f) {
            $path = $this->storeUpload($f, 'class-content/gallery');
            if ($path === false) {
                return response()->json(['message' => 'ذخیره‌ی «' . $f->getClientOriginalName() . '» روی سرور ممکن نشد.', 'album_id' => $album->id], 500);
            }
            // عنوانِ هر عکس: نامِ فایل بدونِ پسوند (قابلِ ویرایش)، نه تکرارِ عنوانِ آلبوم
            $name = trim(preg_replace('/[_\-]+/', ' ', pathinfo($f->getClientOriginalName(), PATHINFO_FILENAME)));
            $photo = ClassContent::create([
                'school_id' => $album->school_id ?? $teacher->school_id,
                'teacher_id' => $teacher->id,
                'classroom_id' => $album->classroom_id,
                'album_id' => $album->id,
                'type' => 'gallery',
                'title' => mb_substr($this->niceName($name) ?: $album->title, 0, 150),
                'file_path' => $path,
                'publish_at' => $album->publish_at,
                'is_visible' => true,
                // آلبومِ زنده: اعلان را خودِ این متد می‌دهد، نه releaseDue
                'notified_at' => $album->isLive() ? now() : null,
            ]);
            $ids[] = $photo->id;
            if (isset($data['cover_local']) && (int) $data['cover_local'] === $i) {
                $album->forceFill(['cover_id' => $photo->id])->save();
            }
        }
        GalleryAlbums::repair($album);

        if ($request->boolean('final') && $album->isLive()) {
            $total = (int) ($data['total'] ?? count($ids));
            ContentRelease::notifyAlbum($album, $total, ! $request->boolean('is_new'));
            $album->forceFill(['notified_at' => now()])->save();
        }

        $msg = $album->isLive()
            ? '🖼️ ' . Jalali::fa((string) ($data['total'] ?? count($ids))) . ' عکس در آلبومِ «' . $album->title . '» قرار گرفت ✅'
            : '⏰ آلبومِ «' . $album->title . '» در ' . Jalali::format($album->publish_at) . ' ساعت ' . Jalali::fa($album->publish_at->format('H:i')) . ' منتشر می‌شود.';
        if ($request->boolean('final')) {
            session()->flash('flash', $msg);
        }

        return response()->json(['ok' => true, 'album_id' => $album->id, 'ids' => $ids, 'message' => $msg]);
    }

    /** «IMG 2024…» و نام‌های بی‌معنای دوربین عنوانِ خوبی نیستند. */
    private function niceName(string $n): string
    {
        return preg_match('/^(img|dsc|pxl|photo|image|whatsapp|screenshot|\d)/i', $n) ? '' : $n;
    }

    public function update(Request $request, GalleryAlbum $album): RedirectResponse
    {
        abort_unless($album->teacher_id === $request->user()->id, 403);
        $data = $request->validate([
            'title'        => 'required|string|max:150',
            'description'  => 'nullable|string|max:2000',
            'classroom_id' => 'nullable|integer|exists:classrooms,id',
            'publish_at'   => 'nullable|date',
            'cover_id'     => 'nullable|integer',
            'theme'        => 'nullable|in:' . implode(',', GalleryAlbum::THEMES),
        ], ['title.required' => 'عنوانِ آلبوم را بنویسید.']);

        if (! empty($data['cover_id']) && ! ClassContent::where('album_id', $album->id)->whereKey($data['cover_id'])->exists()) {
            unset($data['cover_id']);
        }
        $wasLive = $album->isLive();
        $album->fill([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'classroom_id' => $data['classroom_id'] ?? null,
            'publish_at' => $data['publish_at'] ?? null,
            'theme' => $data['theme'] ?? $album->theme,
        ] + (isset($data['cover_id']) ? ['cover_id' => $data['cover_id']] : []))->save();
        GalleryAlbums::syncPhotos($album);

        // تازه منتشر شد و هنوز اعلانی نرفته بود
        if (! $wasLive && $album->isLive() && ! $album->notified_at) {
            ContentRelease::notifyAlbum($album, $album->photos()->count(), false);
            $album->forceFill(['notified_at' => now()])->save();
            ClassContent::where('album_id', $album->id)->whereNull('notified_at')->update(['notified_at' => now()]);
        }

        return back()->with('flash', 'آلبوم ویرایش شد ✅');
    }

    /** فقط عوض‌کردنِ کاور (دکمه‌ی ⭐ روی هر عکس). */
    public function cover(Request $request, GalleryAlbum $album, ClassContent $photo): RedirectResponse
    {
        abort_unless($album->teacher_id === $request->user()->id && $photo->album_id === $album->id, 403);
        $album->forceFill(['cover_id' => $photo->id])->save();

        return back()->with('flash', '⭐ کاورِ آلبوم عوض شد');
    }

    public function visibility(Request $request, GalleryAlbum $album): RedirectResponse
    {
        abort_unless($album->teacher_id === $request->user()->id, 403);
        $album->is_visible = ! $album->is_visible;
        $album->save();
        if ($album->isLive() && ! $album->notified_at) {
            ContentRelease::notifyAlbum($album, $album->photos()->count(), false);
            $album->forceFill(['notified_at' => now()])->save();
        }

        return back()->with('flash', $album->is_visible ? '👁️ آلبوم برای دانش‌آموزان نمایش داده می‌شود' : '🙈 آلبوم از دیدِ دانش‌آموزان مخفی شد');
    }

    public function destroy(Request $request, GalleryAlbum $album): RedirectResponse
    {
        abort_unless($album->teacher_id === $request->user()->id, 403);
        $photos = ClassContent::where('album_id', $album->id)->get();
        foreach ($photos as $p) {
            if ($p->file_path) Storage::disk('public')->delete($p->file_path);
            \App\Models\ContentView::where('class_content_id', $p->id)->delete();
            $p->delete();
        }
        $title = $album->title;
        $album->delete();

        return back()->with('flash', "🗑️ آلبومِ «{$title}» و " . Jalali::fa((string) $photos->count()) . ' عکسش حذف شد');
    }

    /** ویرایشِ عنوان/توضیحِ یک عکس، یا انتقالش به آلبومی دیگر. */
    public function updatePhoto(Request $request, ClassContent $photo): RedirectResponse
    {
        abort_unless($photo->teacher_id === $request->user()->id && $photo->type === 'gallery', 403);
        $data = $request->validate([
            'title'       => 'required|string|max:150',
            'description' => 'nullable|string|max:2000',
            'album_id'    => 'nullable|integer',
        ], ['title.required' => 'عنوانِ عکس را بنویسید.']);

        $old = $photo->album_id;
        $photo->fill(['title' => $data['title'], 'description' => $data['description'] ?? null]);
        if (! empty($data['album_id']) && (int) $data['album_id'] !== (int) $old) {
            $to = GalleryAlbum::where('teacher_id', $request->user()->id)->findOrFail($data['album_id']);
            $photo->fill(['album_id' => $to->id, 'publish_at' => $to->publish_at, 'classroom_id' => $to->classroom_id]);
        }
        $photo->save();
        if ($old && $old !== $photo->album_id && ($a = GalleryAlbum::find($old))) {
            GalleryAlbums::repair($a);
        }
        if ($photo->album) GalleryAlbums::repair($photo->album);

        return back()->with('flash', 'عکس ویرایش شد ✅');
    }

    /** حذفِ گروهیِ عکس‌های انتخاب‌شده در یک آلبوم. */
    public function destroyPhotos(Request $request, GalleryAlbum $album): RedirectResponse
    {
        abort_unless($album->teacher_id === $request->user()->id, 403);
        $ids = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer'])['ids'];
        $photos = ClassContent::where('album_id', $album->id)->whereIn('id', $ids)->get();
        foreach ($photos as $p) {
            if ($p->file_path) Storage::disk('public')->delete($p->file_path);
            \App\Models\ContentView::where('class_content_id', $p->id)->delete();
            $p->delete();
        }
        $left = GalleryAlbums::repair($album);

        return back()->with('flash', '🗑️ ' . Jalali::fa((string) $photos->count()) . ' عکس حذف شد' . ($left ? '' : ' و آلبومِ خالی هم برداشته شد'));
    }
}
