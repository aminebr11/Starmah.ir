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
    public static function notify(ClassContent $content, int $count = 1): void
    {
        try {
            self::doNotify($content, max(1, $count));
        } catch (\Throwable $e) {
            Log::warning('content notify failed: ' . $e->getMessage());
        }
    }

    private static function doNotify(ClassContent $content, int $count = 1): void
    {
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
            'title' => $label . ' — ' . $content->title,
            'audience' => 'personal',
            'body' => "معلمت محتوای جدیدی برایت گذاشت: «{$content->title}». روی همین اعلان بزن تا ببینی"
                . ($content->type === 'podcast' ? ' و با گوش‌دادن امتیاز بگیری ⚡' : '.'),
        ];
        if (\Illuminate\Support\Facades\Schema::hasColumn('announcements', 'link')) {
            $payload['link'] = self::linkFor($content);
        }

        $ann = Announcement::create($payload);
        $ann->recipients()->sync($ids);

        // پیامک — تکلیف رویدادِ جداگانه دارد چون معمولاً مهم‌ترینِ آن‌هاست
        $event = $content->type === 'homework' ? 'homework' : 'content';
        foreach (User::whereIn('id', $ids)->get() as $student) {
            SmsGateway::event($event, $student,
                "{$label} — «{$content->title}» برای {$student->name} در سامانه‌ی ستاره ماه ثبت شد.", $teacher);
        }
    }

    /**
     * محتوای زمان‌داری که وقتش رسیده را اعلان می‌کند (یک‌بار).
     * سبک و کم‌هزینه: یک کوئریِ محدود، و در نبودِ ستون‌ها بی‌صدا رد می‌شود.
     */
    public static function releaseDue(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('class_contents', 'publish_at')) {
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
