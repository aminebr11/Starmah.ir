<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Setting;
use App\Models\User;
use App\Support\Roles;

/** بررسی تولد دانش‌آموزان و ارسال پیام تبریک خودکار (یک‌بار در سال). */
class BirthdayService
{
    /** برای یک مدرسه اجرا می‌شود (هنگام بارگذاری داشبورد). */
    public function runForSchool(?int $schoolId): void
    {
        if (! $schoolId) {
            return;
        }

        $today = now();
        $students = User::role(Roles::STUDENT)->where('school_id', $schoolId)
            ->whereNotNull('birth_date')
            ->whereMonth('birth_date', $today->month)
            ->whereDay('birth_date', $today->day)
            ->get();

        foreach ($students as $student) {
            $flag = "bday:{$student->id}:{$today->year}";
            if (Setting::get($flag)) {
                continue; // امسال قبلاً تبریک گفته شده
            }

            $classroom = $student->classrooms()->first();
            $teacherId = $classroom?->teacher_id;

            // پیام تبریک به دانش‌آموز
            $studentMsg = Announcement::create([
                'school_id' => $schoolId,
                'sender_id' => $teacherId ?: $student->id,
                'title'     => '🎂 تولدت مبارک!',
                'audience'  => 'personal',
                'body'      => "🎂 تولدت مبارک {$student->name}!\nامیدواریم سالی پر از موفقیت، شادی و اتفاق‌های خوب پیش رو داشته باشی.",
            ]);
            $studentMsg->recipients()->sync([$student->id]);

            // اطلاع به معلم
            if ($teacherId) {
                $teacherMsg = Announcement::create([
                    'school_id' => $schoolId,
                    'sender_id' => $teacherId,
                    'title'     => '🎂 تولد دانش‌آموز',
                    'audience'  => 'personal',
                    'body'      => "امروز تولد «{$student->name}» است. یک تبریک کوچک می‌تواند روزش را بسازد! 🎉",
                ]);
                $teacherMsg->recipients()->sync([$teacherId]);
            }

            Setting::put($flag, '1');
        }
    }
}
