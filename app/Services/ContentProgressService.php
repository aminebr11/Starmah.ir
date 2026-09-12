<?php

namespace App\Services;

use App\Models\ClassContent;
use App\Models\ContentView;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * پیشرفتِ دانش‌آموز در محتوای کلاس، با امتیازدهیِ قابلِ اعتماد.
 *
 * ── مشکلی که حل می‌کند ────────────────────────────────────────────────
 * نسخه‌ی پیشین امتیاز را از روی عددِ «ثانیه» می‌داد که مرورگر می‌فرستاد و
 * سرور هیچ بررسی‌ای رویش نداشت. یک درخواستِ دستی با seconds=9999 امتیازِ
 * کامل می‌گرفت، بی‌آنکه چیزی پخش شده باشد. جلو زدنِ نوارِ پخش هم همین اثر
 * را داشت.
 *
 * ── راهکار: نقشه‌ی پوشش + ساعتِ سرور ──────────────────────────────────
 * مدیا به خانه‌های ۱۰ثانیه‌ای تقسیم می‌شود. مرورگر فقط می‌گوید «الان روی
 * ثانیه‌ی N هستم». سرور:
 *
 *   ۱) خانه‌ی متناظر را در نقشه علامت می‌زند؛
 *   ۲) اگر بینِ گزارشِ قبلی و حالا جهشِ بزرگی رخ داده باشد (جلو زدن)،
 *      خانه‌های میانی علامت نمی‌خورند و در نقشه «سوراخ» می‌ماند؛
 *   ۳) با ساعتِ **خودش** بررسی می‌کند که از گزارشِ قبلی چقدر گذشته و
 *      اجازه نمی‌دهد در یک ثانیه ده‌ها خانه پر شود.
 *
 * پس تکمیل فقط وقتی ثبت می‌شود که دانش‌آموز واقعاً محتوا را — با گذرِ
 * زمانِ واقعی و بدونِ پرش — پخش کرده باشد.
 *
 * امتیاز **دقیقاً یک‌بار** و در لحظه‌ی تکمیل داده می‌شود؛ پخشِ دوباره
 * امتیازِ تازه‌ای ندارد.
 */
class ContentProgressService
{
    /** اندازه‌ی هر خانه‌ی نقشه‌ی پوشش (ثانیه). */
    public const BUCKET = 10;

    /** چند درصد از مدیا باید پخش شده باشد تا «تکمیل» به‌حساب آید. */
    public const COMPLETE_RATIO = 0.9;

    /**
     * بیشترین جهشِ مجاز بینِ دو گزارش تا هنوز «پخشِ پیوسته» شمرده شود.
     * گزارش‌ها هر ۵ ثانیه می‌آیند؛ ۲۵ ثانیه فضای کافی برای تأخیرِ شبکه
     * می‌دهد بی‌آنکه پرشِ واقعی را بپذیرد.
     */
    public const MAX_GAP = 25;

    /** ضریبِ تحملِ سرعت (۱.۵ برابر، برای پخشِ تندشده و تأخیرِ شبکه). */
    private const RATE_TOLERANCE = 1.5;

    /** انواعی که پخش‌شونده‌اند و امتیازشان به تکمیل بستگی دارد. */
    public const PLAYABLE = ['podcast', 'video'];

    /**
     * ثبتِ یک گزارشِ پیشرفت.
     *
     * @param  int   $position  ثانیه‌ی جاریِ پخش (از مرورگر)
     * @param  bool  $ended     آیا مدیا به پایان رسید
     * @return array{completed:bool, gained:int, xp:int, percent:int, position:int, verified_seconds:int}
     */
    public function report(User $student, ClassContent $content, int $position, bool $ended = false): array
    {
        $view = ContentView::firstOrNew([
            'class_content_id' => $content->id,
            'student_id'       => $student->id,
        ]);

        $wasCompleted = $view->completed_at !== null;
        $now = Carbon::now();

        // محتوای غیرِپخشی (جزوه، عکس): دیدنش یک‌بار امتیاز دارد
        if (! in_array($content->type, self::PLAYABLE, true)) {
            return $this->completeStatic($student, $content, $view, $now, $wasCompleted);
        }

        $duration = (int) ($content->duration_seconds ?? 0);
        $position = max(0, $position);
        if ($duration > 0) {
            $position = min($position, $duration);
        }

        $covered = $this->coveredSet($view);
        $prevPos = (int) ($view->max_position ?? 0);
        $lastPing = $view->last_ping_at ? Carbon::parse($view->last_ping_at) : null;

        // سقفِ **تجمعیِ** پوشش بر پایه‌ی ساعتِ سرور.
        //
        // نکته‌ی کلیدی: سقف نباید «به‌ازای هر درخواست» باشد، وگرنه فرستادنِ
        // ده‌ها درخواستِ پشت‌سرهم در یک لحظه همان اثرِ گوش‌دادنِ واقعی را
        // می‌دهد. پس کلِ خانه‌های پوشش‌داده‌شده را با زمانِ سپری‌شده از
        // نخستین گزارش می‌سنجیم: کسی نمی‌تواند بیش از زمانی که واقعاً
        // گذشته، مدیا شنیده باشد.
        $startedAt = $view->created_at ? Carbon::parse($view->created_at) : $now;
        $sinceStart = max(0, (int) abs($now->diffInSeconds($startedAt)));
        $maxBuckets = (int) floor(($sinceStart * self::RATE_TOLERANCE) / self::BUCKET) + 1;

        // در همین گزارش هم بیش از سهمِ زمانیِ فاصله‌ی دو گزارش جلو نمی‌رویم.
        $elapsed = $lastPing ? max(0, (int) abs($now->diffInSeconds($lastPing))) : self::BUCKET;
        $perPing = (int) floor(($elapsed * self::RATE_TOLERANCE) / self::BUCKET) + 1;

        $allowance = max(0, min($perPing, $maxBuckets - count($covered)));

        // خانه‌های بینِ نقطه‌ی قبلی و نقطه‌ی فعلی — فقط اگر جهش کوچک باشد
        $from = (int) floor(min($prevPos, $position) / self::BUCKET);
        $to   = (int) floor($position / self::BUCKET);
        $jumped = abs($position - $prevPos) > self::MAX_GAP;

        $added = 0;
        if (! $jumped) {
            for ($b = $from; $b <= $to && $added < $allowance; $b++) {
                if (! isset($covered[$b])) {
                    $covered[$b] = true;
                    $added++;
                }
            }
        } else {
            // پرش رخ داده: فقط خانه‌ی همین لحظه علامت می‌خورد،
            // پس در نقشه سوراخ می‌ماند و تکمیل حساب نمی‌شود.
            if (! isset($covered[$to]) && $allowance > 0) {
                $covered[$to] = true;
                $added = 1;
            }
        }

        $view->covered = array_map('intval', array_keys($covered));
        $view->verified_seconds = count($covered) * self::BUCKET;
        $view->max_position = max($prevPos, $position);
        $view->last_ping_at = $now;
        $view->viewed = true;
        // ستونِ قدیمی را هم‌گام نگه می‌داریم (سازگاری با گزارش‌های موجود)
        $view->seconds = $view->verified_seconds;

        $percent = $this->percent($covered, $duration);

        // تکمیل: پوششِ کافی. اگر مدت نامعلوم باشد، به پایانِ واقعیِ پخش تکیه می‌کنیم.
        $enough = $duration > 0
            ? $percent >= (int) round(self::COMPLETE_RATIO * 100)
            : ($ended && count($covered) >= 1);

        $gained = 0;
        if (! $wasCompleted && $enough) {
            $view->completed_at = $now;
            $gained = $this->awardOnce($student, $content, $view);
        }

        $view->save();

        return [
            'completed'        => $view->completed_at !== null,
            'gained'           => $gained,
            'xp'               => (int) ($view->xp_awarded ?? 0),
            'percent'          => $percent,
            'position'         => (int) $view->max_position,
            'verified_seconds' => (int) $view->verified_seconds,
        ];
    }

    /** درصدِ پوشش بر اساسِ نقشه و مدتِ مدیا. */
    private function percent(array $covered, int $duration): int
    {
        if ($duration <= 0) {
            return count($covered) > 0 ? 1 : 0;
        }

        $total = max(1, (int) ceil($duration / self::BUCKET));

        return min(100, (int) round(count($covered) / $total * 100));
    }

    /** محتوای غیرِپخشی: نخستین بازدید، یک‌بار امتیاز. */
    private function completeStatic(User $student, ClassContent $content, ContentView $view, Carbon $now, bool $wasCompleted): array
    {
        $view->viewed = true;
        $view->last_ping_at = $now;

        $gained = 0;
        if (! $wasCompleted) {
            $view->completed_at = $now;
            $gained = $this->awardOnce($student, $content, $view);
        }

        $view->save();

        return [
            'completed' => true,
            'gained'    => $gained,
            'xp'        => (int) ($view->xp_awarded ?? 0),
            'percent'   => 100,
            'position'  => (int) ($view->max_position ?? 0),
            'verified_seconds' => (int) ($view->verified_seconds ?? 0),
        ];
    }

    /**
     * پرداختِ امتیاز — فقط یک‌بار در عمرِ هر (دانش‌آموز، محتوا).
     * محافظ: اگر xp_awarded از قبل مقدار دارد، دوباره پرداخت نمی‌شود.
     */
    private function awardOnce(User $student, ClassContent $content, ContentView $view): int
    {
        if ((int) ($view->xp_awarded ?? 0) > 0) {
            return 0;
        }

        $xp = $this->xpFor($content);
        if ($xp <= 0) {
            return 0;
        }

        app(GamificationService::class)->award(
            $student,
            $xp,
            $this->reasonFor($content),
            null,
            'content',
            $content->id,
        );

        $view->xp_awarded = $xp;

        return $xp;
    }

    /**
     * امتیازِ هر محتوا.
     * اگر معلم عددی تعیین کرده باشد همان؛ وگرنه از روی مدت حساب می‌شود
     * تا محتوای بلندتر ارزشِ بیشتری داشته باشد — با سقفِ منطقی.
     */
    public function xpFor(ClassContent $content): int
    {
        if ($content->xp_reward !== null) {
            return (int) $content->xp_reward;
        }

        if (! in_array($content->type, self::PLAYABLE, true)) {
            return 1;   // جزوه یا عکسِ دیده‌شده
        }

        $minutes = (int) ceil(((int) ($content->duration_seconds ?? 0)) / 60);

        return max(5, min(25, $minutes * 2));
    }

    private function reasonFor(ClassContent $content): string
    {
        $icon = match ($content->type) {
            'video'   => '🎬',
            'podcast' => '🎧',
            'gallery' => '🖼️',
            default   => '📚',
        };

        return "{$icon} محتوای کلاس — {$content->title}";
    }

    /** @return array<int,bool> نقشه‌ی پوشش به‌صورتِ کلیدهای خانه */
    private function coveredSet(ContentView $view): array
    {
        $raw = $view->covered;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        $out = [];
        foreach ((array) $raw as $b) {
            $out[(int) $b] = true;
        }

        return $out;
    }
}
