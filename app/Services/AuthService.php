<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    /**
     * Register a new user, initialize presence, and issue a Sanctum token.
     *
     * @param  array<string, mixed>  $data
     * @return array{user: User, token: string}
     */
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $user = User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
            ]);

            $user->presence()->create([
                'is_online' => true,
                'last_seen_at' => now(),
            ]);

            $deviceName = $data['device_name'] ?? 'android-mobile';
            $token = $user->createToken($deviceName)->plainTextToken;

            $user->load('presence');

            return [
                'user' => $user,
                'token' => $token,
            ];
        });
    }

    /**
     * Authenticate a user with email & password and issue a Sanctum token.
     *
     * @param  array<string, mixed>  $credentials
     * @return array{user: User, token: string}
     *
     * @throws AuthenticationException
     */
    public function login(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw new AuthenticationException('Email atau password salah.');
        }

        $user->presence()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'is_online' => true,
                'last_seen_at' => now(),
            ]
        );

        $deviceName = $credentials['device_name'] ?? 'android-mobile';
        $token = $user->createToken($deviceName)->plainTextToken;

        $user->load('presence');

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Revoke the current access token and mark user offline if no active tokens remain.
     */
    public function logout(User $user): void
    {
        $currentToken = $user->currentAccessToken();

        if ($currentToken !== null) {
            $currentToken->delete();
        }

        $hasOtherTokens = $user->tokens()->exists();

        if (! $hasOtherTokens) {
            $user->presence()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'is_online' => false,
                    'last_seen_at' => now(),
                ]
            );
        }
    }
}
