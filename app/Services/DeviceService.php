<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;

class DeviceService
{
    /**
     * Store or update device token for FCM push notification.
     */
    public function registerToken(
        User $user,
        string $token,
        string $platform = 'android',
        ?string $deviceName = null
    ): DeviceToken {
        return DeviceToken::updateOrCreate(
            [
                'user_id' => $user->id,
                'token' => $token,
            ],
            [
                'platform' => $platform,
                'device_name' => $deviceName,
                'last_used_at' => now(),
            ]
        );
    }

    /**
     * Remove device token (e.g. on logout).
     */
    public function removeToken(User $user, string $token): bool
    {
        return (bool) DeviceToken::query()
            ->where('user_id', $user->id)
            ->where('token', $token)
            ->delete();
    }
}
