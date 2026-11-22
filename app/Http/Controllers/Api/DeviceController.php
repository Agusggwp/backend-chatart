<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Device\RegisterDeviceTokenRequest;
use App\Services\DeviceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function __construct(
        private readonly DeviceService $deviceService
    ) {}

    public function registerToken(RegisterDeviceTokenRequest $request): JsonResponse
    {
        $deviceToken = $this->deviceService->registerToken(
            user: $request->user(),
            token: $request->validated('token'),
            platform: $request->validated('platform', 'android'),
            deviceName: $request->validated('device_name'),
        );

        return ApiResponse::success(
            data: [
                'device_token' => [
                    'id' => $deviceToken->id,
                    'platform' => $deviceToken->platform,
                    'last_used_at' => $deviceToken->last_used_at?->toIso8601String(),
                ],
            ],
            message: 'Token perangkat berhasil disimpan.'
        );
    }

    public function index(Request $request): JsonResponse
    {
        $devices = \App\Models\UserDevice::where('user_id', $request->user()->id)
            ->orderByDesc('last_active_at')
            ->get();

        return ApiResponse::success(
            data: [
                'devices' => $devices,
            ],
            message: 'Daftar perangkat berhasil diambil.'
        );
    }

    public function destroy(Request $request, int $deviceId): JsonResponse
    {
        \App\Models\UserDevice::where('user_id', $request->user()->id)
            ->where('id', $deviceId)
            ->delete();

        return ApiResponse::success(
            data: null,
            message: 'Perangkat berhasil dicabut.'
        );
    }

    public function removeToken(Request $request): JsonResponse
    {
        $token = (string) $request->input('token');
        if ($token !== '') {
            $this->deviceService->removeToken($request->user(), $token);
        }

        return ApiResponse::success(
            data: null,
            message: 'Token perangkat berhasil dihapus.'
        );
    }
}
