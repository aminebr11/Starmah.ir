<?php

namespace App\Support;

use App\Models\CurriculumChapter;
use App\Models\LearningObjective;
use App\Services\MasteryService;

/**
 * «هدفِ درسی» یک سؤال = پایه + درس + فصل (یا شماره‌درس) + مبحث.
 *
 * کلید با همان نرمال‌سازیِ اثرانگشتِ بانک ساخته می‌شود تا «ي/ی» یا نیم‌فاصله‌ی
 * متفاوت، یک هدف را دو هدف نکند.
 */
class Objectives
{
    /** کشِ همین درخواست (در کانتینر، نه static — تا بینِ درخواست‌ها/تست‌ها شناسه‌ی کهنه نماند). */
    private static function memo(): \ArrayObject
    {
        $k = self::class . '.memo';
        if (! app()->bound($k)) {
            app()->instance($k, new \ArrayObject(['ids' => [], 'chapters' => []]));
        }

        return app($k);
    }

    /** فیلدهای دسته‌بندیِ یک ردیفِ بانک یا هر آرایه‌ی مشابه. */
    public static function attrs(object|array $src): array
    {
        $g = fn (string $k) => is_array($src) ? ($src[$k] ?? null) : ($src->{$k} ?? null);
        $clean = fn ($v) => ($v === null || trim((string) $v) === '') ? null : trim((string) $v);

        return [
            'level' => $clean($g('level')),
            'grade' => $clean($g('grade')),
            // همان یکسان‌سازیِ موتورِ تسلط تا «ریاضی چهارم» و «ریاضی» یک درس باشند
            'subject' => MasteryService::subject($clean($g('subject')) ?? $clean($g('book'))),
            'chapter_id' => $g('chapter_id') ? (int) $g('chapter_id') : null,
            'chapter' => $clean($g('chapter')),
            'lesson_no' => $clean($g('lesson_no')),
            'topic' => $clean($g('topic')),
        ];
    }

    public static function key(array $a): string
    {
        $fp = fn ($v) => Curriculum::fingerprint((string) $v);
        $unit = match (true) {
            ! empty($a['chapter_id']) => 'c' . $a['chapter_id'],
            ! empty($a['chapter']) => 't' . $fp($a['chapter']),
            ! empty($a['lesson_no']) => 'l' . $fp($a['lesson_no']),
            default => '',
        };

        return sha1(implode('|', [$fp($a['grade'] ?? ''), $fp($a['subject'] ?? ''), $unit, $fp($a['topic'] ?? '')]));
    }

    public static function label(array $a): string
    {
        if (! empty($a['topic'])) {
            return mb_substr($a['topic'], 0, 300);
        }
        if (! empty($a['chapter_id']) && ($c = self::chapterLabel((int) $a['chapter_id']))) {
            return $c;
        }
        if (! empty($a['chapter'])) {
            return $a['chapter'];
        }
        if (! empty($a['lesson_no'])) {
            return 'درس ' . Jalali::fa((string) $a['lesson_no']) . ($a['subject'] ? ' — ' . $a['subject'] : '');
        }

        return $a['subject'] ?: 'عمومی';
    }

    /** شناسه‌ی هدف (اگر نبود ساخته می‌شود). */
    public static function idFor(object|array $src): int
    {
        $a = self::attrs($src);
        $key = self::key($a);
        $memo = self::memo();
        if (isset($memo['ids'][$key])) {
            return $memo['ids'][$key];
        }

        $obj = LearningObjective::firstOrCreate(['key' => $key], [
            'level' => $a['level'] ?? Curriculum::levelOf($a['grade']),
            'grade' => $a['grade'],
            'subject' => $a['subject'],
            'chapter_id' => $a['chapter_id'],
            'chapter' => $a['chapter_id'] ? self::chapterLabel($a['chapter_id']) : $a['chapter'],
            'lesson_no' => $a['lesson_no'],
            'topic' => $a['topic'],
            'label' => self::label($a),
        ]);

        $ids = $memo['ids'];
        $ids[$key] = $obj->id;
        $memo['ids'] = $ids;

        return $obj->id;
    }

    private static function chapterLabel(int $id): ?string
    {
        $memo = self::memo();
        $labels = $memo['chapters'];
        if (! array_key_exists($id, $labels)) {
            $labels[$id] = CurriculumChapter::withoutGlobalScopes()->find($id)?->label();
            $memo['chapters'] = $labels;
        }

        return $labels[$id];
    }

    public static function flush(): void
    {
        app()->forgetInstance(self::class . '.memo');
    }
}
