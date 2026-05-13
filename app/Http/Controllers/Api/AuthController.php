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
        if (! $token = auth('api')->attempt([
            'username' => $data['username'],
            'password' => $data['password'],
            'is_active' => true,
        ])) {
            return $this->error(null, 'Tên đăng nhập hoặc mật khẩu không đúng, hoặc tài khoản đang bị khóa.', Response::HTTP_UNAUTHORIZED);
        }

        /** @var User $user */
        $user = auth('api')->user();

        return $this->responseWithToken($token, $user->fresh(), 'Đăng nhập thành công.');
    }

    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user()->loadMissing('employee');

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
        $user->loadMissing('employee');

        return [
            'id' => $user->id,
            'username' => $user->username,
            'role' => $user->role?->value,
            'employee_id' => $user->employee_id,
            'employee' => $user->employee ? [
                'id' => $user->employee->id,
                'employee_code' => $user->employee->employee_code,
                'full_name' => $user->employee->full_name,
                'phone' => $user->employee->phone,
                'email' => $user->employee->email,
                'citizen_id_number' => $user->employee->citizen_id_number,
                'position' => $user->employee->position?->value,
                'work_status' => $user->employee->work_status?->value,
            ] : null,
            'is_active' => $user->is_active,
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }
}
