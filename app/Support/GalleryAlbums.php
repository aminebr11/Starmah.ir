<?php

namespace App\Support;

use App\Models\ClassContent;
use App\Models\GalleryAlbum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * منطقِ مشترکِ آلبوم‌های گالری (معلم و دانش‌آموز).
 *
 * - عکس‌های قدیمیِ بدونِ آلبوم به‌طورِ خودکار دسته می‌شوند: هم‌عنوان، هم‌کلاس
 *   و آپلودشده در یک روز = یک آلبوم.
 * - نمایش/مخفی و زمانِ انتشارِ آلبوم روی عکس‌هایش هم نوشته می‌شود تا
 *   کوئری‌های موجودِ «محتوای زنده» بی‌تغییر درست کار کنند.
 */
class GalleryAlbums
{
    public static function ready(): bool
    {
        static $ok = null;

        return $ok ??= Schema::hasTable('gallery_albums') && Schema::hasColumn('class_contents', 'album_id');
    }

    /** دسته‌بندیِ عکس‌های بی‌آلبومِ یک معلم. */
    public static function adopt(int $teacherId): void
    {
        if (! self::ready()) return;
        $orphans = ClassContent::withoutGlobalScopes()->where('teacher_id', $teacherId)->where('type', 'gallery')
            ->whereNull('album_id')->orderBy('created_at')->orderBy('id')->get();
        if ($orphans->isEmpty()) return;

        $groups = $orphans->groupBy(fn ($c) => implode('|', [trim((string) $c->title), $c->classroom_id ?: 0, $c->created_at?->toDateString()]));
        foreach ($groups as $items) {
            $first = $items->first();
            $album = GalleryAlbum::withoutGlobalScopes()->create([
                'school_id' => $first->school_id, 'teacher_id' => $teacherId, 'classroom_id' => $first->classroom_id,
                'title' => $first->title ?: 'آلبومِ کلاس', 'description' => $first->description,
                'cover_id' => $first->id, 'publish_at' => $first->publish_at,
                'is_visible' => $items->contains(fn ($c) => $c->is_visible !== false),
                'notified_at' => $first->notified_at,
            ]);
            // تاریخِ ساختِ آلبوم = تاریخِ نخستین عکس
            $album->forceFill(['created_at' => $first->created_at, 'updated_at' => $items->max('created_at')])->saveQuietly();
            ClassContent::withoutGlobalScopes()->whereIn('id', $items->pluck('id'))->update(['album_id' => $album->id]);
        }
    }

    /** اگر کاور حذف یا خالی شد، نخستین عکسِ باقی‌مانده کاور می‌شود؛ آلبومِ خالی حذف می‌شود. */
    public static function repair(GalleryAlbum $album): ?GalleryAlbum
    {
        $ids = ClassContent::withoutGlobalScopes()->where('album_id', $album->id)->orderBy('created_at')->orderBy('id')->pluck('id');
        if ($ids->isEmpty()) {
            $album->delete();

            return null;
        }
        if (! $album->cover_id || ! $ids->contains($album->cover_id)) {
            $album->forceFill(['cover_id' => $ids->first()])->save();
        }

        return $album;
    }

    /** نمایش/زمانِ انتشارِ آلبوم را روی همه‌ی عکس‌هایش بنویس. */
    public static function syncPhotos(GalleryAlbum $album): void
    {
        ClassContent::withoutGlobalScopes()->where('album_id', $album->id)->update([
            'publish_at' => $album->publish_at,
            'classroom_id' => $album->classroom_id,
        ]);
    }

    public static function url(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    /**
     * داده‌ی آلبوم‌ها برای صفحه.
     *
     * @param  Collection<int, ClassContent>  $photos  عکس‌هایی که بیننده اجازه‌ی دیدنشان را دارد
     * @param  callable|null  $extra  داده‌ی اضافه برای هر عکس (مثلاً آمارِ بازدید برای معلم)
     */
    public static function present(Collection $photos, ?callable $extra = null): array
    {
        if (! self::ready()) return [];
        $albums = GalleryAlbum::withoutGlobalScopes()->whereIn('id', $photos->pluck('album_id')->filter()->unique())->get()->keyBy('id');
        $out = [];
        foreach ($photos->groupBy('album_id') as $albumId => $items) {
            $a = $albums->get($albumId);
            if (! $a) continue;
            $items = $items->sortBy(fn ($p) => [$p->created_at?->timestamp ?? 0, $p->id])->values();
            $cover = $items->firstWhere('id', $a->cover_id) ?? $items->first();
            $last = $items->max('created_at');
            $out[] = [
                'id' => $a->id,
                'title' => $a->title,
                'description' => $a->description,
                'classroom_id' => $a->classroom_id,
                'theme' => $a->theme ?: GalleryAlbum::THEMES[$a->id % count(GalleryAlbum::THEMES)],
                'cover_id' => $cover?->id,
                'cover' => self::url($cover?->file_path) ?? $cover?->external_url,
                'count' => $items->count(),
                'day' => $a->created_at?->toDateString(),
                'date' => $a->created_at ? Jalali::format($a->created_at) : null,
                'updated' => $last ? Jalali::format($last) : null,
                'ts' => $a->created_at?->timestamp ?? 0,
                'is_visible' => $a->is_visible !== false,
                'live' => $a->isLive(),
                'publish_at_raw' => $a->publish_at?->format('Y-m-d H:i'),
                'publish_at' => $a->publish_at ? Jalali::format($a->publish_at) . ' ساعت ' . Jalali::fa($a->publish_at->format('H:i')) : null,
                'photos' => $items->map(fn ($p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'description' => $p->description,
                    'url' => self::url($p->file_path) ?? $p->external_url,
                    'is_visible' => $p->is_visible !== false,
                    'date' => Jalali::format($p->created_at),
                    'time' => $p->created_at ? Jalali::fa($p->created_at->format('H:i')) : null,
                    'ts' => $p->created_at?->timestamp ?? 0,
                ] + ($extra ? $extra($p) : []))->values()->all(),
            ];
        }
        usort($out, fn ($x, $y) => $y['ts'] <=> $x['ts'] ?: $y['id'] <=> $x['id']);

        return $out;
    }
}
