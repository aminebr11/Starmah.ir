<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\ClassContent;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * انتشارِ محتوای کلاس و اعلانِ آن به دانش‌آموزان.
 *
 * چرا جدا از کنترلر: محتوای «زمان‌دار» باید سرِ ساعتِ خودش اعلان بدهد،
 * و آن لحظه هیچ درخواستی از سمتِ معلم در کار نیست. releaseDue() در
 * ساختِ فیدِ اعلان‌ها صدا زده می‌شود؛ پس بدونِ نیاز به cron، نخستین
 * بازدیدِ هر کاربر از سایت محتوای سررسیده را منتشر و اعلان می‌کند.
 */
class ContentRelease
{
    private const LABELS = [
        'material' => '📄 جزوه/فایلِ جدید',
        'podcast'  => '🎧 پادکستِ جدید',
        'video'    => '🎬 ویدیوی درسیِ جدید',
        'gallery'  => '🖼️ تصویرِ جدید',
        'homework' => '📝 تکلیفِ جدید',
    ];

    /** مقصدِ کلیک روی اعلان — تکلیف به تبِ تکالیف می‌رود. */
    public static function linkFor(ClassContent $content): string
    {
        return $content->type === 'homework'
            ? '/class-content?tab=homework'
            : '/class-content?tab=' . $content->type;
    }

    /** اعلانِ «محتوای جدید». هرگز جریانِ اصلی را نمی‌شکند. */
    /**
     * اعلانِ آلبوم: یک اعلان برای کلِ آلبوم یا عکس‌های تازه‌اش.
     * کلیک روی اعلان مستقیم همان آلبوم را باز می‌کند.
     */
    public static function notifyAlbum(\App\Models\GalleryAlbum $album, int $count, bool $added = false): void
    {
        $first = ClassContent::withoutGlobalScopes()->where('album_id', $album->id)->orderBy('id')->first();
        if (! $first) return;
        $title = $added
            ? '🖼️ ' . Jalali::fa((string) $count) . ' عکسِ تازه در آلبومِ «' . $album->title . '»'
            : '📸 آلبومِ تازه: ' . $album->title . ' (' . Jalali::fa((string) $count) . ' عکس)';
        try {
            self::doNotify($first, max(1, $count), [
                'title' => $title,
                'body' => "معلمت " . ($added ? 'عکس‌های تازه‌ای به آلبومِ' : 'آلبومِ تازه‌ای با') . " «{$album->title}» گذاشت. روی همین اعلان بزن تا ببینی 📷",
                'link' => '/class-content?tab=gallery&album=' . $album->id,
                'classroom_id' => $album->classroom_id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('album notify failed: ' . $e->getMessage());
        }
    }

    public static function notify(ClassContent $content, int $count = 1): void
    {
        try {
            self::doNotify($content, max(1, $count));
        } catch (\Throwable $e) {
            Log::warning('content notify failed: ' . $e->getMessage());
        }
    }

    private static function doNotify(ClassContent $content, int $count = 1, array $over = []): void
    {
        if (array_key_exists('classroom_id', $over)) {
            $content = (clone $content)->forceFill(['classroom_id' => $over['classroom_id']]);
        }
        $teacher = $content->teacher ?: User::find($content->teacher_id);

        if ($content->classroom_id) {
            $ids = Classroom::find($content->classroom_id)?->students()->pluck('users.id')->all() ?? [];
        } else {
            $ids = Classroom::where('teacher_id', $content->teacher_id)
                ->with('students:id')->get()
                ->flatMap(fn ($c) => $c->students->pluck('id'))->unique()->values()->all();
        }
        if (! $ids) {
            return;
        }

        // دسته‌ای از عکس‌ها یک اعلان دارد، نه یک اعلان برای هر عکس
        $label = ($content->type === 'gallery' && $count > 1)
            ? '🖼️ ' . Jalali::fa((string) $count) . ' عکسِ جدید'
            : (self::LABELS[$content->type] ?? '📚 محتوای جدید');
        $payload = [
            'school_id' => $content->school_id ?? optional($teacher)->school_id,
            'sender_id' => $content->teacher_id,
            'title' => $over['title'] ?? ($label . ' — ' . $content->title),
            'audience' => 'personal',
            'body' => $over['body'] ?? "معلمت محتوای جدیدی برایت گذاشت: «{$content->title}». روی همین اعلان بزن تا ببینی"
                . ($content->type === 'podcast' ? ' و با گوش‌دادن امتیاز بگیری ⚡' : '.'),
        ];
        if (\App\Support\DbSchema::hasColumn('announcements', 'link')) {
            $payload['link'] = $over['link'] ?? self::linkFor($content);
        }

        $ann = Announcement::create($payload);
        $ann->recipients()->sync($ids);

        // پیامک — تکلیف رویدادِ جداگانه دارد چون معمولاً مهم‌ترینِ آن‌هاست
        $event = $content->type === 'homework' ? 'homework' : 'content';
        foreach (User::whereIn('id', $ids)->get() as $student) {
            SmsGateway::event($event, $student,
                isset($over['title'])
                    ? "{$over['title']} — برای {$student->name} در سامانه‌ی ستاره ماه."
                    : "{$label} — «{$content->title}» برای {$student->name} در سامانه‌ی ستاره ماه ثبت شد.", $teacher);
        }
    }

    /**
     * محتوای زمان‌داری که وقتش رسیده را اعلان می‌کند (یک‌بار).
     * سبک و کم‌هزینه: یک کوئریِ محدود، و در نبودِ ستون‌ها بی‌صدا رد می‌شود.
     */
    public static function releaseDue(): void
    {
        if (! \App\Support\DbSchema::hasColumn('class_contents', 'publish_at')) {
            return;
        }
        try {
            ClassContent::whereNotNull('publish_at')
                ->where('publish_at', '<=', now())
                ->whereNull('notified_at')
                ->where('is_visible', true)
                ->limit(20)->get()
                ->each(function (ClassContent $c) {
                    if ($c->fresh()?->notified_at) {
                        return; // همراهِ دسته‌ی گالریِ خودش اعلان شده
                    }
                    $count = 1;
                    if ($c->type === 'gallery' && $c->album_id && ($album = \App\Models\GalleryAlbum::withoutGlobalScopes()->find($c->album_id))) {
                        // آلبوم: یک اعلان برای همه‌ی عکس‌های سررسیده‌اش
                        $due = ClassContent::withoutGlobalScopes()->where('album_id', $album->id)->whereNull('notified_at');
                        $count = max(1, (clone $due)->count());
                        $due->update(['notified_at' => now()]);
                        if ($album->is_visible) {
                            self::notifyAlbum($album, $count, (bool) $album->notified_at);
                        }
                        $album->forceFill(['notified_at' => $album->notified_at ?? now()])->save();
                        $c->forceFill(['notified_at' => now()])->save();
                        return;
                    }
                    if ($c->type === 'gallery') {
                        // عکس‌های هم‌دسته (همان معلم، عنوان، کلاس و زمانِ انتشار) یک اعلان می‌گیرند
                        $siblings = ClassContent::where('type', 'gallery')
                            ->where('teacher_id', $c->teacher_id)->where('title', $c->title)
                            ->where('publish_at', $c->publish_at)->whereNull('notified_at')
                            ->when($c->classroom_id, fn ($q) => $q->where('classroom_id', $c->classroom_id), fn ($q) => $q->whereNull('classroom_id'));
                        $count = max(1, (clone $siblings)->count());
                        $siblings->update(['notified_at' => now()]);
                    }
                    self::notify($c, $count);
                    $c->forceFill(['notified_at' => now()])->save();
                });
        } catch (\Throwable $e) {
            Log::warning('content release failed: ' . $e->getMessage());
        }
    }
}
