<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Authenticated user's own profile representation.
 * Includes email and phone, but never password or tokens.
 *
 * @mixin \App\Models\User
 */
class ProfileResource extends JsonResource
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
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'bio' => $this->bio,
            'is_online' => (bool) ($this->presence?->is_online ?? false),
            'last_seen_at' => $this->presence?->last_seen_at?->toIso8601String(),
            'presence' => $this->whenLoaded(
                'presence',
                fn () => $this->presence ? new UserPresenceResource($this->presence) : [
                    'is_online' => false,
                    'last_seen_at' => null,
                ]
            ),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'privacy_last_seen' => $this->privacy_last_seen ?? 'everyone',
            'privacy_status' => $this->privacy_status ?? 'everyone',
            'privacy_read_receipts' => (bool) ($this->privacy_read_receipts ?? true),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
