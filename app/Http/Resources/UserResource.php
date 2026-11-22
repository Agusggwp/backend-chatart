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
        $viewer = $request->user();
        $canSeePresence = true;

        if ($viewer && (int) $viewer->id !== (int) $this->id) {
            $privacy = $this->privacy_last_seen ?? 'everyone';
            if ($privacy === 'nobody') {
                $canSeePresence = false;
            } elseif ($privacy === 'contacts') {
                $hasSharedChat = \App\Models\ConversationParticipant::where('user_id', $this->id)
                    ->whereIn('conversation_id', function ($query) use ($viewer) {
                        $query->select('conversation_id')
                            ->from('conversation_participants')
                            ->where('user_id', $viewer->id);
                    })->exists();
                if (! $hasSharedChat) {
                    $canSeePresence = false;
                }
            }
        }

        $isOnline = $canSeePresence ? (bool) ($this->presence?->is_online ?? false) : false;
        $lastSeenAt = $canSeePresence ? $this->presence?->last_seen_at?->toIso8601String() : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'avatar_url' => $this->avatar_url,
            'bio' => $this->bio,
            'is_blocked' => $request->user() ? $request->user()->hasBlocked($this->id) : false,
            'is_online' => $isOnline,
            'last_seen_at' => $lastSeenAt,
            'presence' => $this->whenLoaded(
                'presence',
                fn () => $canSeePresence && $this->presence ? new UserPresenceResource($this->presence) : [
                    'is_online' => false,
                    'last_seen_at' => null,
                ]
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
