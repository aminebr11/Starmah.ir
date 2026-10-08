<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\User;
use App\Support\Jalali;
use App\Support\Roles;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * تولدِ دانش‌آموزان — بر اساسِ تاریخِ تولدِ ثبت‌شده در پرونده.
 *
 * روزِ تولد با تقویمِ شمسی حساب می‌شود (بچه‌ها تولدشان را شمسی جشن
 * می‌گیرند؛ سالگردِ میلادی گاهی یک روز جابه‌جا می‌افتاد). در روزِ تولد
 * یک اعلان برای معلمِ کلاس می‌رود تا خودش از میانِ قالب‌ها پیامِ تبریک
 * بفرستد؛ فقط اگر دانش‌آموز معلم نداشته باشد، تبریکِ خودکارِ سامانه می‌رود.
 *
 * هر تولد در هر سال فقط یک‌بار ثبت می‌شود — با درجِ اتمی در جدولِ
 * settings، تا بارگذاریِ همزمانِ چند داشبورد اعلانِ تکراری نسازد (پیش از
 * این چهار اعلانِ یکسانِ «تولد دانش‌آموز» برای معلم ساخته می‌شد).
 */
class BirthdayService
{
    public const TEMPLATES = [
        '🎂 {name} عزیز، تولدت مبارک! امیدوارم سالِ تازه‌ات پر از شادی، دوستیِ خوب و کشف‌های قشنگ باشد. همیشه به تو افتخار می‌کنم. — {teacher}',
        '🎉 {name} جان، {age} سالگی‌ات مبارک! تو ستاره‌ی کلاسِ ما هستی؛ امسال هم مثلِ همیشه بدرخش. ⭐ — {teacher}',
        '🌟 تولدت مبارک {name}! آرزو می‌کنم هر روزِ امسال یک دلیلِ تازه برای لبخند داشته باشی. — {teacher}',
        '🎈 {name} عزیزم، امروز روزِ توست! از این‌که در کلاسِ ما هستی خوشحالم. سالِ پر از یادگیری و خنده داشته باشی. — {teacher}',
        '🚀 {name} قهرمان، تولدت مبارک! امسال هم مأموریت‌های بزرگی در انتظارِ توست؛ مطمئنم همه را با موفقیت انجام می‌دهی. — {teacher}',
        '💐 {name} خوبم، زادروزت خجسته! برایت سلامتی، شادی و روزهای روشن آرزو می‌کنم. — {teacher}',
        '🎁 هدیه‌ی کوچکِ من برای تولدت: کلی امتیاز و یک عالمه آرزوی خوب! تولدت مبارک {name} 🎂 — {teacher}',
        '📚✨ {name} عزیز، {age} ساله شدنت مبارک! هر کتابی که ورق می‌زنی، تو را یک قدم به رؤیاهایت نزدیک‌تر می‌کند. — {teacher}',
    ];

    /** برای یک مدرسه اجرا می‌شود (هنگامِ بارگذاریِ داشبورد و cron). */
    public function runForSchool(?int $schoolId): void
    {
        if (! $schoolId) {
            return;
        }
        [$jy] = self::jToday();

        foreach ($this->birthdaysOn(now(), $schoolId) as $student) {
            $flag = "bday:{$student->id}:{$jy}";
            $fresh = DB::table('settings')->insertOrIgnore(['key' => $flag, 'value' => '1', 'created_at' => now(), 'updated_at' => now()]);
            if (! $fresh) {
                continue;   // امسال قبلاً ثبت شده
            }

            $classroom = $student->classrooms()->first();
            $teacherId = $classroom?->teacher_id;
            $age = self::ageTurning($student);

            if ($teacherId) {
                $a = Announcement::create([
                    'school_id' => $schoolId,
                    'sender_id' => $teacherId,
                    'title'     => "🎂 تولدِ {$student->name}",
                    'audience'  => 'personal',
                    'link'      => '/teacher/birthdays?student=' . $student->id,
                    'body'      => "امروز تولدِ «{$student->name}»" . ($classroom ? " از کلاسِ {$classroom->name}" : '')
                        . ($age ? " است و {$age} ساله می‌شود." : ' است.')
                        . ' با یک لمس از میانِ قالب‌ها برایش پیامِ تبریک بفرست — یک تبریکِ کوچک روزش را می‌سازد! 🎉',
                ]);
                $a->recipients()->sync([$teacherId]);
            } else {
                // دانش‌آموزِ بدونِ معلم — تبریکِ خودکارِ سامانه
                $a = Announcement::create([
                    'school_id' => $schoolId,
                    'sender_id' => $student->id,
                    'title'     => '🎂 تولدت مبارک!',
                    'audience'  => 'personal',
                    'body'      => "🎂 تولدت مبارک {$student->name}!\nامیدواریم سالی پر از موفقیت، شادی و اتفاق‌های خوب پیش رو داشته باشی.",
                ]);
                $a->recipients()->sync([$student->id]);
            }
        }
    }

    /** دانش‌آموزانی که تولدِ شمسی‌شان در این روز است. */
    public function birthdaysOn(Carbon $day, int $schoolId)
    {
        [, $jm, $jd] = Jalali::fromGregorian((int) $day->year, (int) $day->month, (int) $day->day);

        return User::role(Roles::STUDENT)->where('school_id', $schoolId)->whereNotNull('birth_date')->get()
            ->filter(fn (User $s) => self::matches($s, $jm, $jd, $day))->values();
    }

    /** آیا تولدِ شمسیِ این دانش‌آموز با ماه/روزِ داده‌شده یکی است؟ */
    public static function matches(User $s, int $jm, int $jd, Carbon $day): bool
    {
        [, $bm, $bd] = Jalali::fromGregorian((int) $s->birth_date->year, (int) $s->birth_date->month, (int) $s->birth_date->day);
        if ($bm === $jm && $bd === $jd) {
            return true;
        }
        // متولدِ ۳۰ اسفند در سال‌های غیرکبیسه: ۲۹ اسفند جشن گرفته می‌شود
        if ($bm === 12 && $bd === 30 && $jm === 12 && $jd === 29) {
            $next = $day->copy()->addDay();
            [, $nm] = Jalali::fromGregorian((int) $next->year, (int) $next->month, (int) $next->day);

            return $nm === 1;
        }

        return false;
    }

    /** سنی که امسال (شمسی) کامل می‌شود. */
    public static function ageTurning(User $s): ?int
    {
        if (! $s->birth_date) {
            return null;
        }
        [$by] = Jalali::fromGregorian((int) $s->birth_date->year, (int) $s->birth_date->month, (int) $s->birth_date->day);
        [$jy] = self::jToday();
        $age = $jy - $by;

        return $age > 0 && $age < 30 ? $age : null;
    }

    public static function jToday(): array
    {
        $t = now();

        return Jalali::fromGregorian((int) $t->year, (int) $t->month, (int) $t->day);
    }

    /**
     * تولدهای دانش‌آموزانِ کلاس‌های یک معلم: امروز، ۳۰ روزِ آینده و ۷ روزِ گذشته.
     *
     * @return array<int,array>
     */
    public function forTeacher(User $teacher): array
    {
        $ids = Classroom::where('teacher_id', $teacher->id)->pluck('id');
        $students = User::role(Roles::STUDENT)->whereNotNull('birth_date')
            ->whereHas('classrooms', fn ($q) => $q->whereIn('classrooms.id', $ids))
            ->with(['classrooms:id,name', 'theme:id,name,emoji'])->get();
        [$jy] = self::jToday();

        $out = [];
        foreach ($students as $s) {
            for ($off = -7; $off <= 30; $off++) {
                $day = now()->startOfDay()->addDays($off);
                [, $jm, $jd] = Jalali::fromGregorian((int) $day->year, (int) $day->month, (int) $day->day);
                if (self::matches($s, $jm, $jd, $day)) {
                    $out[] = [
                        'id' => $s->id, 'name' => $s->name, 'avatar' => $s->avatar_url,
                        'class' => $s->classrooms->first()?->name,
                        'team' => $s->theme ? trim(($s->theme->emoji ?? '') . ' ' . $s->theme->name) : null,
                        'offset' => $off, 'date' => Jalali::format($day, true),
                        'age' => self::ageTurning($s),
                        'sent' => (bool) DB::table('settings')->where('key', "bday-sent:{$s->id}:{$jy}")->exists(),
                        'has_parent' => (bool) ($s->parent_phone || ($s->settings['guardian']['phone'] ?? null)),
                    ];
                    break;
                }
            }
        }
        usort($out, fn ($a, $b) => [abs($a['offset']) > 0 ? 1 : 0, $a['offset'] < 0 ? 1 : 0, abs($a['offset'])]
            <=> [abs($b['offset']) > 0 ? 1 : 0, $b['offset'] < 0 ? 1 : 0, abs($b['offset'])]);

        return $out;
    }

    public static function fill(string $tpl, User $student, User $teacher): string
    {
        $first = trim(explode(' ', trim($student->name))[0] ?? $student->name);
        $age = self::ageTurning($student);

        return strtr($tpl, [
            '{name}' => $first,
            '{age}' => $age ? Jalali::fa((string) $age) : '',
            '{teacher}' => $teacher->name,
        ]);
    }
}
