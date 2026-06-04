<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'admin',
        ]);

        $admin = AdminUser::updateOrCreate(
            ['username' => 'admin'],
            [
                'password' => Hash::make('jsadmin'),
                'status' => 'active',
            ]
        );

        $admin->assignRole($role);
    }
}