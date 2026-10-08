<?php

namespace App\Support;

use App\Models\CurriculumChapter;

/** فصلِ تک‌تکِ سؤال‌ها (آزمون/بازی/دفترِ نمره): اعتبارسنجی و برچسب. */
class QuestionChapter
{
    /** @return array<int,string> [chapter_id => «فصل ۲ — …»] فقط برای فصل‌های موجود */
    public static function labels(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (! $ids) {
            return [];
        }

        return CurriculumChapter::withoutGlobalScopes()->whereIn('id', $ids)->get()
            ->mapWithKeys(fn ($c) => [$c->id => $c->label()])->all();
    }
}
