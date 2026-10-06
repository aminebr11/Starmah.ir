<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\EduGame;
use App\Models\SmartExam;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * اعلانِ بازی و آزمونِ هوشمند به دانش‌آموزان — یک‌جا و بدونِ تکرار.
 *
 * قاعده: هر دانش‌آموزی که بازی/آزمون را «می‌تواند ببیند» و هنوز اعلانش را
 * نگرفته، یک اعلان می‌گیرد. پس:
 *  - انتشارِ تازه           ← همه‌ی مخاطبان اعلان می‌گیرند
 *  - تغییرِ گروه/مخاطب       ← فقط دانش‌آموزانِ تازه‌اضافه‌شده
 *  - انتشارِ زمان‌دار        ← سرِ همان ساعت (releaseDue)، نه هنگامِ ساخت
 *  - نسخه‌ی تازه/تغییرِ سؤال  ← دیگران یک اعلانِ «به‌روز شد» هم می‌گیرند
 *
 * «اعلان گرفته» از روی خودِ اعلان‌ها تشخیص داده می‌شود (پیوندِ اختصاصیِ هر
 * بازی/آزمون)، پس جدول یا ستونِ تازه‌ای لازم نیست.
 */
class ActivityNotifier
{
    public static function gameLink(int $id): string
    {
        return '/game-world?game=' . $id;
    }

    public static function examLink(int $id): string
    {
        return '/student/smart-exams?exam=' . $id;
    }

    /* ─────────── مخاطبان ─────────── */

    /** دانش‌آموزانِ کلاس‌های این معلم (همان منطقِ صفحه‌ی دانش‌آموز). */
    private static function teacherStudents(int $teacherId): Collection
    {
        $classIds = Classroom::where('teacher_id', $teacherId)->pluck('id');

        return User::whereHas('classrooms', fn ($q) => $q->whereIn('classrooms.id', $classIds))->get(['users.id', 'users.theme_id']);
    }

    public static function gameAudience(EduGame $game): array
    {
        $game->loadMissing('targets');
        $students = self::teacherStudents($game->teacher_id);
        if ($game->targets->isNotEmpty()) {
            $themes = $game->targets->pluck('theme_id')->filter();
            $ids = $game->targets->pluck('student_id')->filter();
            $students = $students->filter(fn ($s) => $ids->contains($s->id) || ($s->theme_id && $themes->contains($s->theme_id)));
        }

        return $students->pluck('id')->unique()->values()->all();
    }

    public static function examAudience(SmartExam $exam): array
    {
        $exam->loadMissing('targets');
        $classIds = Classroom::where('teacher_id', $exam->teacher_id)->pluck('id');
        if ($exam->targets->isEmpty()) {
            return User::whereHas('classrooms', fn ($q) => $q->whereIn('classrooms.id', $classIds))->pluck('users.id')->unique()->values()->all();
        }
        $ids = collect();
        $tClass = $exam->targets->pluck('classroom_id')->filter();
        $tTheme = $exam->targets->pluck('theme_id')->filter();
        if ($tClass->isNotEmpty()) {
            $ids = $ids->merge(User::whereHas('classrooms', fn ($q) => $q->whereIn('classrooms.id', $tClass))->pluck('users.id'));
        }
        if ($tTheme->isNotEmpty()) {
            $ids = $ids->merge(User::whereIn('theme_id', $tTheme)->whereHas('classrooms', fn ($q) => $q->whereIn('classrooms.id', $classIds))->pluck('users.id'));
        }

        return $ids->merge($exam->targets->pluck('student_id')->filter())->unique()->values()->all();
    }

    /* ─────────── «زنده» بودن ─────────── */

    public static function gameLive(EduGame $g): bool
    {
        return $g->status === 'published'
            && (! $g->publish_at || $g->publish_at->lessThanOrEqualTo(now()))
            && (! $g->close_at || $g->close_at->greaterThan(now()));
    }

    public static function examLive(SmartExam $e): bool
    {
        return $e->status === 'published'
            && (! $e->opens_at || $e->opens_at->lessThanOrEqualTo(now()))
            && (! $e->closes_at || $e->closes_at->greaterThan(now()));
    }

    /* ─────────── «قبلاً اعلان گرفته» ─────────── */

    /**
     * @param  array<string>  $links   پیوندهای اختصاصیِ این مورد (و نسخه‌های قبلی‌اش)
     * @param  array<string>  $legacyTitles  عنوانِ اعلان‌های قدیمی (پیش از پیوندِ اختصاصی)
     */
    private static function notified(array $links, array $legacy = []): array
    {
        return DB::table('announcement_recipients as r')
            ->join('announcements as a', 'a.id', '=', 'r.announcement_id')
            ->where(function ($q) use ($links, $legacy) {
                $q->whereIn('a.link', $links);
                foreach ($legacy as [$link, $title]) {
                    $q->orWhere(fn ($x) => $x->where('a.link', $link)->where('a.title', $title));
                }
            })
            ->distinct()->pluck('r.user_id')->map(fn ($v) => (int) $v)->all();
    }

    private static function send(int $schoolId, int $senderId, string $title, string $body, string $link, array $ids): int
    {
        if (! $ids) return 0;
        $ann = Announcement::create([
            'school_id' => $schoolId, 'sender_id' => $senderId,
            'title' => $title, 'body' => $body, 'audience' => 'personal', 'link' => $link,
        ]);
        $ann->recipients()->sync(array_values($ids));

        return count($ids);
    }

    /* ─────────── بازی ─────────── */

    /**
     * @param  array<int>  $previousIds  شناسه‌ی نسخه‌های قبلیِ همین بازی (پس از ویرایشِ اساسی)
     * @param  bool  $contentChanged  سؤال‌ها عوض شده‌اند → به دیدگانِ قبلی «به‌روز شد» بگو
     */
    public static function game(EduGame $game, array $previousIds = [], bool $contentChanged = false): int
    {
        try {
            if (! self::gameLive($game)) return 0; // زمان‌دار/پیش‌نویس: بعداً در releaseDue
            $game->loadMissing('template');
            $audience = self::gameAudience($game);
            if (! $audience) return 0;

            $links = array_map([self::class, 'gameLink'], array_merge([$game->id], $previousIds));
            $legacy = [['/game-world', '🎮 بازی جدید: ' . $game->title]];
            $done = self::notified($links, $legacy);

            $fresh = array_values(array_diff($audience, $done));
            $tName = optional($game->template)->name ?? 'بازی';
            $n = self::send($game->school_id, $game->teacher_id, '🎮 بازی جدید: ' . $game->title,
                "یک {$tName} جدید برایت منتشر شد! روی همین اعلان بزن و امتیاز بگیر ⚡", self::gameLink($game->id), $fresh);

            if ($contentChanged) {
                $again = array_values(array_intersect($audience, $done));
                $n += self::send($game->school_id, $game->teacher_id, '🔄 بازی به‌روز شد: ' . $game->title,
                    "معلمت بازیِ «{$game->title}» را تازه کرد — سؤال‌های جدید منتظرت هستند! 🎮", self::gameLink($game->id), $again);
            }

            return $n;
        } catch (\Throwable $e) {
            Log::warning('game notify failed: ' . $e->getMessage());

            return 0;
        }
    }

    /* ─────────── آزمون ─────────── */

    public static function exam(SmartExam $exam, bool $contentChanged = false): int
    {
        try {
            if (! self::examLive($exam)) return 0;
            $audience = self::examAudience($exam);
            if (! $audience) return 0;

            $legacy = [['/student/smart-exams', '🧠 آزمون هوشمند جدید — ' . $exam->title]];
            $done = self::notified([self::examLink($exam->id)], $legacy);
            $fresh = array_values(array_diff($audience, $done));

            $closes = $exam->closes_at ? "\n⏰ مهلت: " . Jalali::format($exam->closes_at, true) . ' ساعت ' . Jalali::fa($exam->closes_at->format('H:i')) : '';
            $n = self::send($exam->school_id, $exam->teacher_id, '🧠 آزمون هوشمند جدید — ' . $exam->title,
                "یک آزمون هوشمندِ جدید برای شما منتشر شد: «{$exam->title}».{$closes}\nبرای شرکت، روی همین اعلان بزنید.", self::examLink($exam->id), $fresh);

            if ($contentChanged) {
                // فقط کسانی که هنوز آزمون را تمام نکرده‌اند خبرِ تغییر را لازم دارند
                $finished = DB::table('smart_exam_attempts')->where('smart_exam_id', $exam->id)->where('status', 'completed')->pluck('student_id')->map(fn ($v) => (int) $v)->all();
                $again = array_values(array_diff(array_intersect($audience, $done), $finished));
                $n += self::send($exam->school_id, $exam->teacher_id, '🔄 آزمون به‌روز شد — ' . $exam->title,
                    "معلم آزمونِ «{$exam->title}» را ویرایش کرد. پیش از شرکت، دوباره نگاهش کن.", self::examLink($exam->id), $again);
            }

            return $n;
        } catch (\Throwable $e) {
            Log::warning('exam notify failed: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * بازی/آزمونِ زمان‌داری که وقتش رسیده را اعلان می‌کند. حداکثر دقیقه‌ای یک‌بار
     * و فقط روی موردهای ۳۰ روزِ اخیر اجرا می‌شود؛ تکراری‌بودن را خودِ game()/exam() مهار می‌کنند.
     */
    public static function releaseDue(): void
    {
        if (! Cache::add('activity-notifier:release', 1, 60)) return;
        try {
            EduGame::withoutGlobalScopes()->where('status', 'published')->whereNotNull('publish_at')
                ->whereBetween('publish_at', [now()->subDays(30), now()])
                ->orderByDesc('publish_at')->limit(30)->get()
                ->each(fn ($g) => self::game($g));
            SmartExam::withoutGlobalScopes()->where('status', 'published')->whereNotNull('opens_at')
                ->whereBetween('opens_at', [now()->subDays(30), now()])
                ->orderByDesc('opens_at')->limit(30)->get()
                ->each(fn ($e) => self::exam($e));
        } catch (\Throwable $e) {
            Log::warning('activity release failed: ' . $e->getMessage());
        }
    }

    /** اثرِ انگشتِ سؤال‌ها برای تشخیصِ «سؤال‌ها عوض شد». */
    public static function fingerprint($questions): string
    {
        return md5(json_encode(collect($questions)->map(fn ($q) => [
            trim((string) data_get($q, 'prompt')),
            collect(data_get($q, 'choices', []))->map(fn ($c) => [trim((string) data_get($c, 'value')), (bool) data_get($c, 'correct')])->all(),
            trim((string) data_get($q, 'answer')),
        ])->values()->all(), JSON_UNESCAPED_UNICODE));
    }
}
