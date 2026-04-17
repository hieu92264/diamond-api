<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory;

    protected $authPasswordName = 'password_hash';

    protected $fillable = [
        'username',
        'email',
        'password_hash',
        'role',
        'employee_id',
        'last_login_at',
        'is_active',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRole(Builder $query, UserRole|string $role): Builder
    {
        $value = $role instanceof UserRole ? $role->value : strtoupper($role);

        return $query->where('role', $value);
    }

    public function hasRole(UserRole|string $role): bool
    {
        $currentRole = strtoupper((string) ($this->role?->value ?? $this->role));
        $expectedRole = $role instanceof UserRole ? $role->value : strtoupper($role);

        return $currentRole === $expectedRole;
    }

    public function hasAnyRole(array $roles): bool
    {
        $currentRole = strtoupper((string) ($this->role?->value ?? $this->role));

        $expectedRoles = array_map(
            fn (UserRole|string $role) => $role instanceof UserRole ? $role->value : strtoupper($role),
            $roles
        );

        return in_array($currentRole, $expectedRoles, true);
    }

    public function isEnabled(): bool
    {
        return $this->is_active === true;
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'role' => $this->role?->value,
            'username' => $this->username,
        ];
    }
}