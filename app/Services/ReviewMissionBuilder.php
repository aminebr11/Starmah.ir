<?php

namespace App\Services;

use App\Models\ObjectiveReview;
use App\Models\PracticeAnswer;
use App\Models\SmartQuestionBank;
use App\Models\User;
use App\Support\BankAccess;
use Illuminate\Support\Collection;

/**
 * «مرورِ امروز»: یک مأموریتِ کوتاهِ خودکار برای هر دانش‌آموز.
 *
 * - بیشترِ سؤال‌ها از هدف‌هایی که این روزها تمرین کرده (درسِ جاری)،
 *   بقیه از هدف‌هایی که موعدِ مرورشان (جعبه‌ی لایتنر) رسیده یا ضعیف‌اند.
 * - سؤال‌ها درهم چیده می‌شوند (interleaving) و یک هدف دو بار پشتِ سرِ هم نمی‌آید.
 * - فقط سؤال‌های تأییدشده‌ای که معلمِ کلاسِ دانش‌آموز به آن‌ها دسترسی دارد.
 */
class ReviewMissionBuilder
{
    public const SIZE = 8;

    /** کمتر از این تعداد سؤال، مرور ساخته نمی‌شود. */
    public const MIN = 4;

    public const RECENT_DAYS = 7;

    /** @return array<int,array{bank:SmartQuestionBank,objective_id:int}> */
    public function build(User $student, int $n = self::SIZE): array
    {
        if (! LearningService::ready()) {
            return [];
        }
        $teacher = $student->classrooms()->with('teacher')->get()->pluck('teacher')->filter()->first();
        if (! $teacher) {
            return [];
        }

        $reviews = ObjectiveReview::withoutGlobalScopes()->where('student_id', $student->id)->get();
        if ($reviews->isEmpty()) {
            return [];
        }

        $today = now()->startOfDay();
        $recentCut = now()->subDays(self::RECENT_DAYS);
        $current = $reviews->filter(fn ($r) => $r->last_seen_at && $r->last_seen_at->gte($recentCut))
            ->sortByDesc('last_seen_at')->pluck('objective_id')->values();
        $review = $reviews
            ->filter(fn ($r) => ($r->due_at && $r->due_at->lte($today)) || ($r->attempts > 0 && $r->correct / $r->attempts < 0.7))
            ->sortBy(fn ($r) => [$r->due_at?->timestamp ?? 0, $r->attempts ? $r->correct / $r->attempts : 0])
            ->pluck('objective_id')->values();
        // هدفی که هم «جاری» است و هم «سررسید»، در سهمِ مرور حساب می‌شود
        $current = $current->diff($review)->values();

        $ids = $current->merge($review)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        // سؤالی که امروز در بارِ اول درست جواب داده، امروز دوباره نمی‌آید
        $skip = PracticeAnswer::withoutGlobalScopes()->where('student_id', $student->id)
            ->where('created_at', '>=', $today)->where('correct', true)->where('first_try', true)
            ->whereNotNull('bank_id')->pluck('bank_id')->all();

        $pool = BankAccess::visibleQuery($teacher)
            ->whereIn('type', ['mc', 'tf'])
            ->where(fn ($q) => $q->whereNull('approval')->orWhere('approval', 'approved'))
            ->whereIn('objective_id', $ids)
            ->when($skip, fn ($q) => $q->whereNotIn('id', $skip))
            ->get()
            ->filter(fn (SmartQuestionBank $q) => collect($q->choices ?? [])->contains(fn ($c) => ! empty($c['correct'])))
            ->shuffle()
            ->groupBy('objective_id');

        // وقتی چند هدف داریم، هیچ هدفی بیش از نیمی از سؤال‌ها را نمی‌گیرد (تا درهم‌چیدن ممکن باشد)
        $maxPer = $pool->count() > 1 ? (int) ceil($n / 2) : $n;
        $counts = [];
        $nReview = max(1, intdiv($n * 3, 8));
        $picked = $this->take($pool, $review, $nReview, $maxPer, $counts);
        $picked = $picked->merge($this->take($pool, $current, $n - $picked->count(), $maxPer, $counts));
        if ($picked->count() < $n) {
            // سهمِ یکی کم بود؟ از دیگری پر کن
            $picked = $picked->merge($this->take($pool, $ids, $n - $picked->count(), $maxPer, $counts));
        }

        return $this->interleave($picked)->all();
    }

    /** نوبتی از هر هدف یک سؤال برمی‌دارد تا سهمِ هدف‌ها متعادل بماند. */
    private function take(Collection $pool, Collection $objectiveIds, int $limit, int $maxPer, array &$counts): Collection
    {
        $out = collect();
        while ($limit > 0) {
            $progress = false;
            foreach ($objectiveIds as $oid) {
                if ($limit <= 0) {
                    break;
                }
                $list = $pool->get($oid);
                if ($list && $list->isNotEmpty() && ($counts[$oid] ?? 0) < $maxPer) {
                    $out->push(['bank' => $list->shift(), 'objective_id' => (int) $oid]);
                    $counts[$oid] = ($counts[$oid] ?? 0) + 1;
                    $limit--;
                    $progress = true;
                }
            }
            if (! $progress) {
                break;
            }
        }

        return $out;
    }

    /** چیدمانِ درهم: هر بار از هدفی که بیشترین سؤالِ باقی‌مانده را دارد، به‌شرطِ تکراری نبودن با قبلی. */
    private function interleave(Collection $items): Collection
    {
        $groups = $items->groupBy('objective_id')->map(fn ($g) => $g->values());
        $out = collect();
        $last = null;
        while ($groups->isNotEmpty()) {
            $order = $groups->sortByDesc(fn ($g) => $g->count())->keys();
            $key = $order->first(fn ($k) => $k !== $last) ?? $order->first();
            $out->push($groups[$key]->shift());
            $last = $key;
            if ($groups[$key]->isEmpty()) {
                $groups->forget($key);
            }
        }

        return $out;
    }

    public function isDoneToday(User $student): bool
    {
        return LearningService::ready() && \App\Models\ReviewCompletion::where('student_id', $student->id)
            ->whereDate('play_date', now()->toDateString())->exists();
    }
}
