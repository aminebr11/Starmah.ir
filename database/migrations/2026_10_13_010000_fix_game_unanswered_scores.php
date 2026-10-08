<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * اصلاحِ امتیازِ بازی‌هایی که اشتباه حساب شده بود.
 *
 * وقتی جان‌ها تمام می‌شد، سؤال‌های بی‌پاسخ «گزینه‌ی ۰» حساب می‌شدند و اگر پاسخِ درست
 * گزینه‌ی اول بود، درست به حساب می‌آمدند (مثلاً «۶ از ۱۵ درست، +۶۰» با صفر پاسخِ درست).
 * پاسخ‌های ذخیره‌شده‌ی هر تلاش نشان می‌دهد کدام سؤال بی‌پاسخ بوده (picked = null)؛
 * پس امتیازِ واقعی دقیق قابلِ محاسبه است:
 *  - نشانِ «درست» از سؤال‌های بی‌پاسخ برداشته می‌شود؛
 *  - امتیازِ تلاش و امتیازِ XP فقط وقتی اصلاح می‌شود که قطعاً از همین بازیِ اشتباه‌حساب‌شده آمده باشد
 *    (برابر با امتیازِ اشتباهِ همین بازی)؛ نتیجه‌ی درستِ بازی‌های دیگر دست نمی‌خورد.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('edu_game_attempts')) {
            return;
        }
        $t0 = microtime(true);
        $fixed = 0;
        $xpRemoved = 0;
        \App\Models\EduGameAttempt::where('status', 'completed')->with('game.questions')->orderBy('id')
            ->chunkById(100, function ($chunk) use (&$fixed, &$xpRemoved, $t0) {
                foreach ($chunk as $att) {
                    if (microtime(true) - $t0 > 20) {
                        return false;
                    }
                    rescue(function () use ($att, &$fixed, &$xpRemoved) {
                        $progress = $att->progress ?? [];
                        $detail = (array) ($progress['answers'] ?? []);
                        $qs = $att->game?->questions?->values();
                        if (! $detail || ! $qs) {
                            return;
                        }
                        $bad = false;
                        $wrongScore = 0;
                        $trueScore = 0;
                        foreach ($detail as $i => $d) {
                            $pts = (int) ($qs[$i]->points ?? 0);
                            $picked = $d['picked'] ?? null;
                            if (! empty($d['correct'])) {
                                $wrongScore += $pts;
                                if ($picked === null || $picked === '') {
                                    $bad = true;
                                    $detail[$i]['correct'] = false;
                                } else {
                                    $trueScore += $pts;
                                }
                            }
                        }
                        if (! $bad) {
                            return;
                        }
                        $update = ['progress' => array_merge($progress, ['answers' => $detail])];
                        if ((int) $att->score === $wrongScore) {
                            $update['score'] = $trueScore;
                        }
                        $att->update($update);
                        $entry = \App\Models\XpEntry::where('source_type', \App\Models\EduGameAttempt::class)->where('source_id', $att->id)->first();
                        if ($entry && (int) $entry->amount === $wrongScore && $wrongScore > $trueScore) {
                            $xpRemoved += $wrongScore - $trueScore;
                            $trueScore > 0 ? $entry->update(['amount' => $trueScore]) : $entry->delete();
                        }
                        $fixed++;
                    }, null, true);
                }
            });
        Log::info('[fix-game-scores] attempts fixed', ['attempts' => $fixed, 'xp_removed' => $xpRemoved]);
        rescue(fn () => \App\Models\Setting::put('game_score_fix', json_encode(['at' => now()->toDateTimeString(), 'attempts' => $fixed, 'xp_removed' => $xpRemoved])), null, false);
    }

    public function down(): void
    {
    }
};
