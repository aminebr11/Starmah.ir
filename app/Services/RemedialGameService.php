<?php

namespace App\Services;

use App\Models\EduGame;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * «🔁 آزمونِ جبرانی»: بازیِ تازه (پیش‌نویس) از سؤال‌های دشوارِ یک بازی.
 * مشترکِ گزارشِ بازی (دکمه) و ماهو («از سؤال‌های سخت آزمون جبرانی بساز»).
 */
class RemedialGameService
{
    /** شماره‌ی سؤال‌هایی که دستِ‌کم یک نفر غلط زده → شناسه‌ی دانش‌آموزانِ اشتباه‌زده. */
    public function wrongMap(EduGame $game): array
    {
        $map = [];
        foreach ($game->attempts()->get(['student_id', 'progress']) as $a) {
            foreach (($a->progress['answers'] ?? []) as $i => $ans) {
                if (isset($ans['correct']) && ! $ans['correct']) {
                    $map[$i][] = (int) $a->student_id;
                }
            }
        }

        return $map;
    }

    /** شناسه‌ی سؤال‌های دشوار، پرغلط‌ترین اول. */
    public function hardQuestionIds(EduGame $game): array
    {
        $game->loadMissing('questions');
        $map = $this->wrongMap($game);
        uasort($map, fn ($a, $b) => count($b) <=> count($a));

        return collect(array_keys($map))->map(fn ($i) => $game->questions->values()[$i]->id ?? null)->filter()->values()->all();
    }

    /**
     * @return array{game:EduGame,questions:int,students:int}|string  پیامِ خطا اگر ساخته نشد
     */
    public function build(EduGame $game, array $questionIds, bool $onlyWrong = true, ?string $title = null): array|string
    {
        $game->loadMissing('questions', 'targets');
        $all = $game->questions->values();
        $picked = $all->whereIn('id', array_map('intval', $questionIds))->values();
        if ($picked->isEmpty()) {
            return 'سؤال‌های انتخاب‌شده در این بازی نیستند.';
        }
        $index = $all->pluck('id')->flip();
        $map = $this->wrongMap($game);
        /** @var Collection<int,int> $wrongIds */
        $wrongIds = $picked->flatMap(fn ($q) => $map[$index[$q->id]] ?? [])->unique()->values();
        if ($onlyWrong && $wrongIds->isEmpty()) {
            return 'هیچ دانش‌آموزی این سؤال‌ها را اشتباه نزده است.';
        }

        $copy = DB::transaction(function () use ($game, $picked, $title, $onlyWrong, $wrongIds) {
            $copy = $game->replicate(['created_at', 'updated_at']);
            $copy->title = trim((string) $title) ?: mb_substr('🔁 جبرانی: ' . $game->title, 0, 150);
            $copy->status = 'draft';
            $copy->publish_at = null;
            $copy->close_at = null;
            $copy->save();
            foreach ($picked as $n => $q) {
                $nq = $q->replicate(['created_at', 'updated_at']);
                $nq->edu_game_id = $copy->id;
                $nq->sort = $n + 1;
                $nq->save();
            }
            if ($onlyWrong) {
                foreach ($wrongIds as $sid) {
                    $copy->targets()->create(['student_id' => $sid]);
                }
            } else {
                foreach ($game->targets as $t) {
                    $copy->targets()->create(['theme_id' => $t->theme_id, 'student_id' => $t->student_id]);
                }
            }

            return $copy;
        });

        return ['game' => $copy, 'questions' => $picked->count(), 'students' => $wrongIds->count()];
    }
}
