<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PlanSeeder::class,
            CurriculumBookSeeder::class,
            ThemeSeeder::class,
            GameTemplateSeeder::class,
            CurriculumSeeder::class,
            DemoSeeder::class,
        ]);
    }
}
