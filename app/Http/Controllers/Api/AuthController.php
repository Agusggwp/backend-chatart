<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\ProfileResource;
use App\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return ApiResponse::success(
            data: [
                'user' => new ProfileResource($result['user']),
                'token' => $result['token'],
            ],
            message: 'Registrasi berhasil.',
            status: 201
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return ApiResponse::success(
            data: [
                'user' => new ProfileResource($result['user']),
                'token' => $result['token'],
            ],
            message: 'Login berhasil.'
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return ApiResponse::success(
            data: null,
            message: 'Logout berhasil.'
        );
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('presence');

        return ApiResponse::success(
            data: [
                'user' => new ProfileResource($user),
            ],
            message: 'Data pengguna berhasil diambil.'
        );
    }
}
