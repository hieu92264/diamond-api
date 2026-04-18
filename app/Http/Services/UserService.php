<?php

namespace App\Http\Services;

use App\Http\Interfaces\UserServiceInterface;
use App\Models\User;
use App\Support\Concerns\BaseService;

class UserService extends BaseService implements UserServiceInterface
{
    public function __construct(User $user)
    {
        parent::__construct(
            model: $user,
            relations: ['employee'],
        );
    }
}
