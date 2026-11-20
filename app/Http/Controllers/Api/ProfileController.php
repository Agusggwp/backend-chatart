<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
}
