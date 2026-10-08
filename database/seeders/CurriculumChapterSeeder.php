<?php

namespace Database\Seeders;

use App\Models\CurriculumBook;
use App\Models\CurriculumChapter;
use App\Support\Curriculum;
use Illuminate\Database\Seeder;

/**
 * فصل‌های آغازینِ چند کتاب — سراسری و قابلِ ویرایش در «ادمین کل → دروس و کتاب‌ها».
 *
 * فقط کتاب‌هایی اینجا آمده‌اند که فهرستِ فصل‌هایشان سال‌هاست ثابت مانده؛
 * بقیه را ادمین کل با «چسباندنِ فهرستِ فصل‌ها» در همان صفحه در چند ثانیه
 * اضافه می‌کند. اجرای دوباره چیزی را تکراری نمی‌سازد.
 */
class CurriculumChapterSeeder extends Seeder
{
    public const DATA = [
        'چهارم' => ['ریاضی' => [
            'عدد و الگوهای عددی', 'کسر', 'ضرب و تقسیم', 'اندازه‌گیری',
            'عددهای مخلوط و کسرهای اعشاری', 'محیط و مساحت', 'آمار و احتمال',
        ]],
        'پنجم' => ['ریاضی' => [
            'عدد و الگوهای عددی', 'کسر', 'عدد اعشاری', 'تقارن و مختصات',
            'اندازه‌گیری', 'تناسب و درصد', 'آمار و احتمال',
        ]],
        'ششم' => ['ریاضی' => [
            'عدد و الگوهای عددی', 'کسر', 'اعداد اعشاری', 'تقارن و مختصات',
            'اندازه‌گیری', 'تناسب و درصد', 'تقریب',
        ]],
    ];

    public function run(): void
    {
        foreach (self::DATA as $grade => $subjects) {
            $level = Curriculum::levelOf($grade);
            foreach ($subjects as $subject => $titles) {
                $book = CurriculumBook::where('level', $level)->where('grade', $grade)->where('name', $subject)->first();
                foreach ($titles as $i => $title) {
                    CurriculumChapter::firstOrCreate(
                        ['school_id' => null, 'grade' => $grade, 'subject' => $subject, 'number' => $i + 1],
                        ['title' => $title, 'level' => $level, 'curriculum_book_id' => $book?->id]
                    );
                }
            }
        }
    }
}
