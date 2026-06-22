<?php

namespace Database\Seeders;

use App\Support\Roles;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Roles::ALL as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
