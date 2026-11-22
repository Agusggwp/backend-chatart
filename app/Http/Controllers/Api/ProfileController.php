<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdatePrivacyRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UploadAvatarRequest;
use App\Http\Resources\ProfileResource;
use App\Services\ProfileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $this->profileService->getProfile($request->user());

        return ApiResponse::success(
            data: [
                'user' => new ProfileResource($user),
            ],
            message: 'Profil berhasil diambil.'
        );
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->profileService->updateProfile($request->user(), $request->validated());

        return ApiResponse::success(
            data: [
                'user' => new ProfileResource($user),
            ],
            message: 'Profil berhasil diperbarui.'
        );
    }

    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $user = $this->profileService->uploadAvatar($request->user(), $request->file('avatar'));

        return ApiResponse::success(
            data: [
                'user' => new ProfileResource($user),
            ],
            message: 'Foto profil berhasil diperbarui.'
        );
    }

    public function privacy(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success(
            data: [
                'privacy_last_seen' => $user->privacy_last_seen ?? 'everyone',
                'privacy_status' => $user->privacy_status ?? 'everyone',
                'privacy_read_receipts' => (bool) ($user->privacy_read_receipts ?? true),
            ],
            message: 'Pengaturan privasi berhasil diambil.'
        );
    }

    public function updatePrivacy(UpdatePrivacyRequest $request): JsonResponse
    {
        $user = $this->profileService->updatePrivacySettings($request->user(), $request->validated());

        return ApiResponse::success(
            data: [
                'user' => new ProfileResource($user),
                'privacy' => [
                    'privacy_last_seen' => $user->privacy_last_seen,
                    'privacy_status' => $user->privacy_status,
                    'privacy_read_receipts' => (bool) $user->privacy_read_receipts,
                ],
            ],
            message: 'Pengaturan privasi berhasil diperbarui.'
        );
    }
}
