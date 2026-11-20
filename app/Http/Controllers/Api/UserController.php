<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ListUsersRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\ChatService;
use App\Services\UserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    public function index(ListUsersRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $paginator = $this->userService->listUsers(
            currentUser: $request->user(),
            search: $validated['search'] ?? null,
            perPage: (int) ($validated['per_page'] ?? 20),
        );

        return ApiResponse::success(
            data: [
                'users' => UserResource::collection($paginator->items()),
            ],
            message: 'Daftar pengguna berhasil diambil.',
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function show(User $user): JsonResponse
    {
        $resolvedUser = $this->userService->getUser($user);

        return ApiResponse::success(
            data: [
                'user' => new UserResource($resolvedUser),
            ],
            message: 'Detail pengguna berhasil diambil.'
        );
    }

    public function block(Request $request, User $user, ChatService $chatService): JsonResponse
    {
        $chatService->blockUser($request->user(), $user->id);

        return ApiResponse::success(
            data: ['is_blocked' => true],
            message: 'Pengguna berhasil diblokir.'
        );
    }

    public function unblock(Request $request, User $user, ChatService $chatService): JsonResponse
    {
        $chatService->unblockUser($request->user(), $user->id);

        return ApiResponse::success(
            data: ['is_blocked' => false],
            message: 'Blokir pengguna berhasil dibuka.'
        );
    }

    public function blockedUsers(Request $request, ChatService $chatService): JsonResponse
    {
        $users = $chatService->listBlockedUsers($request->user());

        return ApiResponse::success(
            data: [
                'users' => UserResource::collection($users),
            ],
            message: 'Daftar pengguna terblokir berhasil diambil.'
        );
    }
}
