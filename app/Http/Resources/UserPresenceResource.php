<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\UserPresence
 */
class UserPresenceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'is_online' => (bool) $this->is_online,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
        ];
    }
}
