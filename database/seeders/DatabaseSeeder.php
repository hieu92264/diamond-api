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
        User::query()->updateOrCreate(
            ['username' => 'admin'],
            [
                'email' => 'admin@diamond.local',
                'password_hash' => 'password',
                'role' => UserRole::ADMIN,
                'is_active' => true,
                'last_login_at' => null,
            ]
        );

        User::query()->updateOrCreate(
            ['username' => 'operator'],
            [
                'email' => 'operator@diamond.local',
                'password_hash' => 'password',
                'role' => UserRole::MANAGER,
                'is_active' => true,
                'last_login_at' => null,
            ]
        );
    }
}
