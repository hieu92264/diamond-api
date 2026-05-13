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
                'role' => UserRole::ADMIN,
            ],
            [
                'username' => 'user',
                'role' => UserRole::USER,
            ],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['username' => $user['username']],
                [
                    'password' => 'password',
                    'role' => $user['role'],
                    'is_active' => true,
                ]
            );
        }
    }
}
