<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => 'password',
            'role' => UserRole::EMPLOYEE_PORTAL,
            'status' => UserStatus::ACTIVE,
            'is_active' => true,
            'last_login_at' => null,
        ];
    }
}
