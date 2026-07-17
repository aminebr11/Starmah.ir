<?php

namespace App\Services;

use App\Models\EduGameAttempt;
use App\Models\MissionCompletion;
use App\Models\SmartExamAttempt;
use App\Models\User;
use App\Models\WorksheetSubmission;
use Illuminate\Support\Collection;

/**
 * کارنامه‌ی درس‌به‌درسِ یکپارچه — عملکردِ هر دانش‌آموز (یا کلِ مدرسه) را در همه‌ی بخش‌ها
 * (آزمون هوشمند، بازی، مأموریت، کاربرگ) بر اساسِ «درس» تجمیع می‌کند.
 */
class CrossSubjectService
{
    private const SECTIONS = [
        'smart' => ['label' => 'آزمون هوشمند', 'icon' => '🧠'],
        'game'  => ['label' => 'بازی', 'icon' => '🎮'],
        'mission' => ['label' => 'مأموریت', 'icon' => '🎯'],
        'worksheet' => ['label' => 'کاربرگ', 'icon' => '🎨'],
    ];

    /** کارنامه‌ی یک دانش‌آموز. */
    public function forStudent(User $student): array
    {
        return $this->build([$student->id]);
    }

    /** کارنامه‌ی مجموعه‌ای از دانش‌آموزان (کلاس/مدرسه). */
    public function forStudents(Collection $studentIds): array
    {
        return $this->build($studentIds->all());
    }

    private function build(array $studentIds): array
    {
        if (empty($studentIds)) {
            return ['subjects' => [], 'sections' => $this->sectionMeta(), 'overall' => 0, 'activities' => 0];
        }

        // subject => ['smart'=>[pcts], 'game'=>[pcts], 'mission'=>[pcts], 'worksheet'=>count]
        $acc = [];
        $add = function ($subject, $section, $pct) use (&$acc) {
            $subject = $subject ?: 'عمومی';
            $acc[$subject] ??= ['smart' => [], 'game' => [], 'mission' => [], 'worksheet' => 0];
            if ($section === 'worksheet') {
                $acc[$subject]['worksheet']++;
            } elseif ($pct !== null) {
                $acc[$subject][$section][] = $pct;
            }
        };

        // آزمون هوشمند
        SmartExamAttempt::whereIn('student_id', $studentIds)
            ->where('status', 'completed')->where('max_score', '>', 0)
            ->with('exam:id,subject')->get()
            ->each(fn ($a) => $add(optional($a->exam)->subject, 'smart', (int) round($a->score / max(1, $a->max_score) * 100)));

        // بازی
        EduGameAttempt::whereIn('student_id', $studentIds)
            ->where('status', 'completed')->where('max_score', '>', 0)
            ->with('game:id,subject')->get()
            ->each(fn ($a) => $add(optional($a->game)->subject, 'game', (int) round($a->score / max(1, $a->max_score) * 100)));

        // مأموریت
        MissionCompletion::whereIn('student_id', $studentIds)
            ->where('total', '>', 0)->with('mission:id,subject')->get()
            ->each(fn ($c) => $add(optional($c->mission)->subject, 'mission', (int) round($c->score / max(1, $c->total) * 100)));

        // کاربرگ (مشارکت — ارسالِ پرشده)
        WorksheetSubmission::whereIn('student_id', $studentIds)
            ->whereNotNull('file_path')->with('worksheet:id,subject')->get()
            ->each(fn ($s) => $add(optional($s->worksheet)->subject, 'worksheet', null));

        $subjects = [];
        $allPcts = [];
        $totalActs = 0;
        foreach ($acc as $subject => $secs) {
            $sections = [];
            $subjPcts = [];
            $acts = 0;
            foreach (['smart', 'game', 'mission'] as $k) {
                $list = $secs[$k];
                $acts += count($list);
                if (count($list)) {
                    $avg = (int) round(array_sum($list) / count($list));
                    $sections[$k] = ['pct' => $avg, 'count' => count($list)];
                    $subjPcts[] = $avg;
                    $allPcts[] = $avg;
                }
            }
            if ($secs['worksheet'] > 0) {
                $sections['worksheet'] = ['count' => $secs['worksheet']];
                $acts += $secs['worksheet'];
            }
            $totalActs += $acts;
            $pct = count($subjPcts) ? (int) round(array_sum($subjPcts) / count($subjPcts)) : null;
            $subjects[] = [
                'subject' => $subject, 'pct' => $pct, 'activities' => $acts,
                'sections' => $sections,
                'status' => $pct === null ? 'na' : ($pct >= 70 ? 'good' : ($pct >= 50 ? 'mid' : 'low')),
            ];
        }

        // مرتب‌سازی: ضعیف‌ترین‌ها بالاتر (برای تمرکزِ تمرین)، درس‌های بدونِ نمره آخر
        usort($subjects, function ($a, $b) {
            if (($a['pct'] === null) !== ($b['pct'] === null)) return $a['pct'] === null ? 1 : -1;
            return ($a['pct'] ?? 0) <=> ($b['pct'] ?? 0);
        });

        return [
            'subjects' => $subjects,
            'sections' => $this->sectionMeta(),
            'overall' => count($allPcts) ? (int) round(array_sum($allPcts) / count($allPcts)) : 0,
            'activities' => $totalActs,
        ];
    }

    private function sectionMeta(): array
    {
        $out = [];
        foreach (self::SECTIONS as $k => $m) {
            $out[] = ['key' => $k, 'label' => $m['label'], 'icon' => $m['icon']];
        }
        return $out;
    }
}
