<?php

namespace App\Support;

use App\Services\MasteryService;
use Illuminate\Support\Collection;

/**
 * نقشه‌ی تسلطِ کلاس برای معلم: دانش‌آموز × مبحث، از همان موتورِ تسلطِ کارنامه.
 * و فهرستِ «چه کسی امروز کمک لازم دارد».
 */
class MasteryGrid
{
    public const MAX_TOPICS = 12;

    /** حداقلِ مبحث‌های «نیاز به تلاش بیشتر» برای قرار گرفتن در فهرستِ کمک. */
    public const HELP_MIN_LOW = 2;

    /** افتِ درس (امتیازِ تسلط در دو هفته‌ی اخیر نسبت به قبل) که «افت» حساب می‌شود. */
    public const HELP_DROP = -10;

    /** @param  Collection<int,\App\Models\User>  $students */
    public static function build(Collection $students): array
    {
        if ($students->isEmpty()) {
            return ['subjects' => [], 'needsHelp' => [], 'levels' => MasteryService::LEVELS];
        }
        $reports = app(MasteryService::class)->forStudents($students->pluck('id')->all());

        // درس‌ها و مبحث‌ها با تعدادِ شواهد در کلِ کلاس
        $obs = [];
        foreach ($reports as $rep) {
            foreach ($rep['subjects'] ?? [] as $s) {
                foreach ($s['topics'] ?? [] as $t) {
                    $obs[$s['name']][$t['name']] = ($obs[$s['name']][$t['name']] ?? 0) + $t['n'];
                }
            }
        }
        uasort($obs, fn ($a, $b) => array_sum($b) <=> array_sum($a));

        $subjects = [];
        foreach ($obs as $subject => $topics) {
            arsort($topics);
            $cols = array_slice(array_keys($topics), 0, self::MAX_TOPICS);
            $rows = [];
            $sum = array_fill_keys($cols, []);
            foreach ($students as $st) {
                $subj = collect($reports[$st->id]['subjects'] ?? [])->firstWhere('name', $subject);
                $byTopic = collect($subj['topics'] ?? [])->keyBy('name');
                $cells = [];
                foreach ($cols as $c) {
                    $t = $byTopic->get($c);
                    $cells[$c] = $t ? self::cell($t['mastery'], $t['n']) : null;
                    if ($t && $t['mastery'] !== null) {
                        $sum[$c][] = $t['mastery'];
                    }
                }
                $rows[] = ['id' => $st->id, 'name' => $st->name, 'cells' => $cells,
                    'trend' => $subj['trend'] ?? null];
            }
            $avg = [];
            $low = [];
            foreach ($cols as $c) {
                $avg[$c] = $sum[$c] ? self::cell((int) round(array_sum($sum[$c]) / count($sum[$c])), count($sum[$c])) : null;
                $low[$c] = collect($rows)->filter(fn ($r) => ($r['cells'][$c]['key'] ?? null) === 'beginning')->count();
            }
            $subjects[] = ['name' => $subject, 'topics' => $cols, 'rows' => $rows, 'avg' => $avg, 'low' => $low];
        }

        return ['subjects' => $subjects, 'needsHelp' => self::needsHelp($students, $reports), 'levels' => MasteryService::LEVELS];
    }

    private static function cell(?int $mastery, int $n): array
    {
        $l = MasteryService::level($mastery);

        return ['mastery' => $mastery, 'n' => $n, 'key' => $l['key'] ?? null, 'label' => $l['label'] ?? null];
    }

    private static function needsHelp(Collection $students, array $reports): array
    {
        $out = [];
        foreach ($students as $st) {
            $lowTopics = [];
            $drops = [];
            foreach ($reports[$st->id]['subjects'] ?? [] as $s) {
                foreach ($s['topics'] ?? [] as $t) {
                    if ($t['mastery'] !== null && ($t['level']['key'] ?? null) === 'beginning') {
                        $lowTopics[] = $t['name'];
                    }
                }
                if (($s['trend'] ?? null) !== null && $s['trend'] <= self::HELP_DROP) {
                    $drops[] = ['subject' => $s['name'], 'trend' => $s['trend']];
                }
            }
            if (count($lowTopics) >= self::HELP_MIN_LOW || $drops) {
                $out[] = ['id' => $st->id, 'name' => $st->name,
                    'low' => count($lowTopics) >= self::HELP_MIN_LOW ? array_slice($lowTopics, 0, 4) : [],
                    'drops' => $drops];
            }
        }
        usort($out, fn ($a, $b) => [count($b['low']), count($b['drops'])] <=> [count($a['low']), count($a['drops'])]);

        return array_slice($out, 0, 8);
    }
}
