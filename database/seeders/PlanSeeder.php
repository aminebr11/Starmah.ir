<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'key' => 'trial', 'name' => 'نسخه‌ی آزمایشی', 'description' => 'یک هفته رایگان برای آشنایی',
                'max_classes' => 5, 'max_students_per_class' => 20, 'duration_days' => 7,
                'price' => 0, 'is_active' => true, 'sort' => 1,
            ],
            [
                'key' => 'pro', 'name' => 'نسخه‌ی حرفه‌ای (پرو)', 'description' => 'کلاس و دانش‌آموز نامحدود، بدون محدودیت زمانی',
                'max_classes' => null, 'max_students_per_class' => null, 'duration_days' => null,
                'price' => 5000000, 'is_active' => true, 'sort' => 2,
            ],
        ];

        foreach ($plans as $p) {
            Plan::updateOrCreate(['key' => $p['key']], $p);
        }
    }
}
