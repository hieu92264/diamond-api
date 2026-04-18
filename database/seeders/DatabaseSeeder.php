<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $users = [
            [
                'username' => 'admin',
                'email' => 'admin@diamond.local',
                'role' => UserRole::ADMIN,
            ],
            [
                'username' => 'manager',
                'email' => 'manager@diamond.local',
                'role' => UserRole::MANAGER,
            ],
            [
                'username' => 'hr.staff',
                'email' => 'hr.staff@diamond.local',
                'role' => UserRole::HR_STAFF,
            ],
            [
                'username' => 'warehouse.staff',
                'email' => 'warehouse.staff@diamond.local',
                'role' => UserRole::WAREHOUSE_STAFF,
            ],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['username' => $user['username']],
                [
                    'email' => $user['email'],
                    'password' => 'password',
                    'role' => $user['role'],
                    'is_active' => true,
                    'last_login_at' => null,
                ]
            );
        }
    }
}
