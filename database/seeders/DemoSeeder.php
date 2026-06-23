<?php

namespace Database\Seeders;

use App\Models\Badge;
use App\Models\Classroom;
use App\Models\School;
use App\Models\Season;
use App\Models\Theme;
use App\Models\User;
use App\Models\XpEntry;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * یک مدرسه‌ی نمونه‌ی کامل برای دموی فاز ۰:
 * مدیر مدرسه، معلم، چند دانش‌آموز (با تم)، یک والد، کلاس، فصل و کمی XP.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // سوپرادمین پلتفرم (بدون مدرسه)
        $this->makeUser(null, Roles::SUPER_ADMIN, 'مدیر پلتفرم', '09120000000');

        $school = School::updateOrCreate(['slug' => 'starmah-demo'], [
            'name' => 'دبستان نمونه ستاره ماه',
            'city' => 'تهران',
            'plan' => 'pro',
            'status' => 'active',
            'seats' => 60,
            'subscription_ends_at' => now()->addYear(),
        ]);

        $admin   = $this->makeUser($school, Roles::SCHOOL_ADMIN, 'خانم مدیری', '09120000001');
        $teacher = $this->makeUser($school, Roles::TEACHER, 'خانم احمدی', '09120000002');

        $fire   = Theme::where('key', 'fire-strikers')->first();
        $blue   = Theme::where('key', 'blue-thunders')->first();
        $creeper = Theme::where('key', 'creeper-warriors')->first();
        $speed  = Theme::where('key', 'super-speed')->first();

        $season = Season::updateOrCreate(
            ['school_id' => $school->id, 'name' => 'فصل پاییز'],
            ['starts_at' => now()->startOfMonth(), 'ends_at' => now()->addMonth(), 'is_active' => true]
        );

        $classroom = Classroom::updateOrCreate(
            ['school_id' => $school->id, 'name' => 'چهارم الف'],
            ['teacher_id' => $teacher->id, 'grade' => 'چهارم', 'join_code' => 'STAR4A']
        );

        // دانش‌آموزان نمونه در گروه‌های مختلف (برای دیدن رقابت گروه‌ها)
        $students = [
            ['آرمین رستمی', '09120000010', $fire, 1240],
            ['سارا محمدی', '09120000011', $blue, 1510],
            ['کیان رضایی', '09120000012', $speed, 1180],
            ['نیلوفر کاظمی', '09120000013', $creeper, 1050],
            ['پارسا احمدی', '09120000014', $fire, 980],
            ['مریم حسینی', '09120000015', $blue, 1120],
            ['رضا کریمی', '09120000016', $creeper, 870],
            ['زهرا نوری', '09120000017', $speed, 760],
        ];

        foreach ($students as [$name, $phone, $theme, $xp]) {
            $student = $this->makeUser($school, Roles::STUDENT, $name, $phone, [
                'theme_id' => $theme?->id, 'grade' => 'چهارم',
            ]);
            $classroom->students()->syncWithoutDetaching([$student->id => ['joined_at' => now()]]);

            // کمی XP اولیه در دفترکل
            if (! XpEntry::where('student_id', $student->id)->exists()) {
                XpEntry::create([
                    'student_id' => $student->id,
                    'season_id'  => $season->id,
                    'amount'     => $xp,
                    'reason'     => 'امتیاز اولیه‌ی دمو',
                ]);
            }
        }

        // یک والد متصل به آرمین
        $parent = $this->makeUser($school, Roles::PARENT, 'مادر آرمین', '09120000020');
        $armin  = User::where('phone', '09120000010')->first();
        if ($armin) {
            $parent->children()->syncWithoutDetaching([$armin->id => ['relation' => 'مادر']]);
        }

        // نشان‌های پایه
        foreach ([
            ['king-mul', 'سلطان ضرب', '🥇'],
            ['streak-7', '۷ روز پیاپی', '🔥'],
            ['fast', 'سریع‌ترین پاسخ', '⚡'],
        ] as [$key, $label, $emoji]) {
            Badge::updateOrCreate(['key' => $key], ['name' => $label, 'emoji' => $emoji]);
        }
    }

    private function makeUser(?School $school, string $role, string $name, string $phone, array $extra = []): User
    {
        $user = User::updateOrCreate(
            ['phone' => $phone],
            array_merge([
                'school_id' => $school?->id,
                'name'      => $name,
                'password'  => Hash::make('password'), // فقط برای دمو
                'phone_verified_at' => now(),
            ], $extra)
        );

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        return $user;
    }
}
