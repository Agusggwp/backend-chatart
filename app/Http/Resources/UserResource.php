<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public user representation for user discovery and viewing other users.
 * Never exposes password, tokens, email, or phone.
 *
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'avatar_url' => $this->avatar_url,
            'bio' => $this->bio,
            'is_blocked' => $request->user() ? $request->user()->hasBlocked($this->id) : false,
            'is_online' => (bool) ($this->presence?->is_online ?? false),
            'last_seen_at' => $this->presence?->last_seen_at?->toIso8601String(),
            'presence' => $this->whenLoaded(
                'presence',
                fn () => $this->presence ? new UserPresenceResource($this->presence) : [
                    'is_online' => false,
                    'last_seen_at' => null,
                ]
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
