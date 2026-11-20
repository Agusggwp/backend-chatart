<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserService
{
    /**
     * List registered users (excluding the authenticated user) with optional search.
     */
    public function listUsers(User $currentUser, ?string $search = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = User::query()
            ->with('presence')
            ->where('id', '!=', $currentUser->id);

        if ($search !== null && trim($search) !== '') {
            $term = trim($search);
            $query->where(function ($builder) use ($term): void {
                $builder->where('name', 'like', "%{$term}%")
                    ->orWhere('username', 'like', "%{$term}%");
            });
        }

        return $query
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Load a single user's public profile with presence.
     */
    public function getUser(User $user): User
    {
        return $user->load('presence');
    }
}
