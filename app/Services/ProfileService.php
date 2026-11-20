<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileService
{
    /**
     * Retrieve the authenticated user's profile with presence loaded.
     */
    public function getProfile(User $user): User
    {
        return $user->load('presence');
    }

    /**
     * Update allowed profile fields (name, username, bio, phone).
     * Email is intentionally excluded.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProfile(User $user, array $data): User
    {
        $allowed = array_intersect_key($data, array_flip([
            'name',
            'username',
            'bio',
            'phone',
        ]));

        if ($allowed !== []) {
            $user->fill($allowed);
            $user->save();
        }

        return $user->fresh()->load('presence');
    }

    /**
     * Store a new avatar image on the public disk and delete the previous one.
     */
    public function uploadAvatar(User $user, UploadedFile $file): User
    {
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg');
        $filename = sprintf('avatars/user_%d_%s.%s', $user->id, Str::uuid()->toString(), $extension);

        $storedPath = Storage::disk('public')->putFileAs(
            'avatars',
            $file,
            basename($filename)
        );

        $oldAvatar = $user->avatar;

        $user->avatar = $storedPath;
        $user->save();

        if ($oldAvatar && $oldAvatar !== $storedPath && Storage::disk('public')->exists($oldAvatar)) {
            Storage::disk('public')->delete($oldAvatar);
        }

        return $user->fresh()->load('presence');
    }
}
