<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $field = filter_var($data['username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (! $token = auth('api')->attempt([
            $field => $data['username'],
            'password' => $data['password'],
            'is_active' => true,
        ])) {
            return $this->error(null, 'Tên đăng nhập hoặc mật khẩu không đúng, hoặc tài khoản đang bị khóa.', Response::HTTP_UNAUTHORIZED);
        }

        /** @var User $user */
        $user = auth('api')->user();
        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        return $this->responseWithToken($token, $user->fresh(), 'Đăng nhập thành công.');
    }

    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();

        return $this->success($this->transformUser($user));
    }

    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return $this->success(null, 'Đăng xuất thành công.');
    }

    public function refresh(): JsonResponse
    {
        $token = auth('api')->refresh();

        /** @var User $user */
        $user = auth('api')->setToken($token)->user();

        return $this->responseWithToken($token, $user, 'Làm mới token thành công.');
    }

    private function responseWithToken(string $token, User $user, string $message): JsonResponse
    {
        return $this->success([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => $this->transformUser($user),
        ], $message);
    }

    private function transformUser(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role?->value,
            'is_active' => $user->is_active,
            'last_login_at' => $user->last_login_at?->toISOString(),
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }
}
