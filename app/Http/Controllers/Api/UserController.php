<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Interfaces\UserServiceInterface;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function __construct(protected UserServiceInterface $userService)
    {

    }

    public function index(): JsonResponse {
        $result = $this->userService->all();
        return $this->success($result, 'Lấy danh sách tài khoản thành công!');
    }

    public function show(int $id): JsonResponse {
        $result = $this->userService->find($id);
        return $this->success($result, 'Lấy thông tin tài khoản thành công!');
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $result = $this->userService->create($request->validated());
        return $this->success($result, 'Tạo tài khoản thành công!', Response::HTTP_CREATED);
    }

    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $result = $this->userService->update($request->validated(), $id);
        return $this->success($result, 'Cập nhật tài khoản thành công!');
    }

    public function delete(int $id): JsonResponse
    {
        $result = $this->userService->delete($id);
        return $this->success($result, 'Xóa tài khoản thành công!');
    }
}
