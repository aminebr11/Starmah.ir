<?php

namespace App\Support;

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * پاک‌کردنِ کاملِ اعلان‌های یک موضوع، وقتی خودِ موضوع حذف می‌شود
 * (اعلان، گیرنده‌ها، «خوانده‌شده»ها، پنهان‌شده‌ها) تا ردی در سیستم نماند.
 *
 * هر اعلانِ تازه source_type/source_id دارد؛ برای اعلان‌های قدیمی (پیش از این ستون‌ها)
 * پیوندِ اختصاصیِ همان موضوع هم بررسی می‌شود.
 */
class NoticeCleanup
{
    /** موضوع‌هایی که با حذفشان اعلان‌ها هم پاک می‌شوند → پیوندهای اختصاصیِ قدیمی. */
    public static function links(Model $m): array
    {
        $id = $m->getKey();

        return match (get_class($m)) {
            \App\Models\AudioTask::class => ["/listen/{$id}", "/teacher/audio/{$id}"],
            \App\Models\LiveContest::class => ["/live/{$id}"],
            \App\Models\Worksheet::class => ["/worksheets/{$id}", "/teacher/worksheets/{$id}"],
            \App\Models\EduGame::class => [ActivityNotifier::gameLink($id)],
            \App\Models\SmartExam::class => [ActivityNotifier::examLink($id)],
            \App\Models\GalleryAlbum::class => ['/class-content?tab=gallery&album=' . $id],
            default => [],
        };
    }

    public const MODELS = [
        \App\Models\AudioTask::class, \App\Models\LiveContest::class, \App\Models\Worksheet::class, \App\Models\EduGame::class,
        \App\Models\SmartExam::class, \App\Models\GalleryAlbum::class, \App\Models\ClassContent::class, \App\Models\Mission::class,
        \App\Models\Assignment::class, \App\Models\GradeColumn::class, \App\Models\PointBatch::class,
    ];

    /** از روی پیوند، موضوعِ اعلان را حدس می‌زند (برای فراخوانی‌هایی که خودشان source نمی‌دهند). */
    public static function sourceFromLink(?string $link): ?array
    {
        $path = (string) $link;
        $map = [
            '#^/listen/(\d+)$#' => \App\Models\AudioTask::class,
            '#^/teacher/audio/(\d+)$#' => \App\Models\AudioTask::class,
            '#^/live/(\d+)$#' => \App\Models\LiveContest::class,
            '#^/(?:teacher/)?worksheets/(\d+)$#' => \App\Models\Worksheet::class,
            '#^/game-world\?game=(\d+)$#' => \App\Models\EduGame::class,
            '#^/student/smart-exams\?exam=(\d+)$#' => \App\Models\SmartExam::class,
            '#^/class-content\?tab=gallery&album=(\d+)$#' => \App\Models\GalleryAlbum::class,
        ];
        foreach ($map as $re => $class) {
            if (preg_match($re, $path, $m)) {
                return [$class, (int) $m[1]];
            }
        }

        return null;
    }

    public static function purge(Model $m): int
    {
        if (! DbSchema::hasTable('announcements')) {
            return 0;
        }
        $links = self::links($m);
        $q = DB::table('announcements')->where(function ($w) use ($m, $links) {
            if (DbSchema::hasColumn('announcements', 'source_type')) {
                $w->where(fn ($x) => $x->where('source_type', get_class($m))->where('source_id', $m->getKey()));
            }
            if ($links) {
                $w->orWhereIn('link', $links);
            }
            if (! DbSchema::hasColumn('announcements', 'source_type') && ! $links) {
                $w->whereRaw('1 = 0');
            }
        });
        $ids = $q->pluck('id')->all();

        return self::deleteAnnouncements($ids);
    }

    /** @param  array<int>  $ids */
    public static function deleteAnnouncements(array $ids): int
    {
        if (! $ids) {
            return 0;
        }
        foreach (array_chunk($ids, 500) as $chunk) {
            $keys = array_map(fn ($id) => 'a' . $id, $chunk);
            DB::table('announcement_recipients')->whereIn('announcement_id', $chunk)->delete();
            if (DbSchema::hasTable('announcement_dismissals')) {
                DB::table('announcement_dismissals')->whereIn('announcement_id', $chunk)->delete();
            }
            if (DbSchema::hasTable('notification_reads')) {
                DB::table('notification_reads')->whereIn('key', $keys)->delete();
            }
            if (DbSchema::hasTable('notification_hides')) {
                DB::table('notification_hides')->whereIn('key', $keys)->delete();
            }
            Announcement::withoutGlobalScopes()->whereIn('id', $chunk)->delete();
        }
        Notifications::forget();

        return count($ids);
    }
}
