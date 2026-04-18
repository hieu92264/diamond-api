<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function authenticateAs(UserRole $role): array
    {
        $user = User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);

        $token = auth('api')->login($user);

        return [
            'Authorization' => 'Bearer '.$token,
        ];
    }

    public function test_admin_can_access_user_routes(): void
    {
        $targetUser = User::factory()->create();

        $this->withHeaders($this->authenticateAs(UserRole::ADMIN))
            ->getJson('/api/users')
            ->assertOk();

        $this->withHeaders($this->authenticateAs(UserRole::ADMIN))
            ->getJson("/api/users/{$targetUser->id}")
            ->assertOk();
    }

    public function test_non_admin_cannot_access_user_list(): void
    {
        $this->withHeaders($this->authenticateAs(UserRole::MANAGER))
            ->getJson('/api/users')
            ->assertForbidden()
            ->assertJsonPath('message', 'Bạn không có quyền truy cập.');

        $this->withHeaders($this->authenticateAs(UserRole::HR_STAFF))
            ->getJson('/api/users')
            ->assertForbidden()
            ->assertJsonPath('message', 'Bạn không có quyền truy cập.');

        $this->withHeaders($this->authenticateAs(UserRole::WAREHOUSE_STAFF))
            ->getJson('/api/users')
            ->assertForbidden()
            ->assertJsonPath('message', 'Bạn không có quyền truy cập.');
    }

    public function test_non_admin_cannot_mutate_users(): void
    {
        $targetUser = User::factory()->create();

        $this->withHeaders($this->authenticateAs(UserRole::MANAGER))
            ->postJson('/api/users/create', [
                'username' => 'blocked-user',
                'email' => 'blocked@example.com',
                'password' => 'secret123',
                'role' => UserRole::HR_STAFF->value,
            ])
            ->assertForbidden();

        $this->withHeaders($this->authenticateAs(UserRole::HR_STAFF))
            ->patchJson("/api/users/update/{$targetUser->id}", [
                'username' => 'updated-by-hr',
            ])
            ->assertForbidden();

        $this->withHeaders($this->authenticateAs(UserRole::WAREHOUSE_STAFF))
            ->deleteJson("/api/users/delete/{$targetUser->id}")
            ->assertForbidden();
    }
}
