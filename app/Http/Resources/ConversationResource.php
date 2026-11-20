<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Conversation
 */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentUserId = $request->user()?->id;
        $isGroup = $this->isGroup();
        $otherUser = $isGroup
            ? null
            : $this->users->first(fn ($u) => $u->id !== $currentUserId);

        $currentUserPivot = $this->users->first(fn ($u) => $u->id === $currentUserId)?->pivot;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'is_group' => $isGroup,
            'name' => $this->name,
            'description' => $this->description,
            'avatar_url' => $this->avatar_url,
            'created_by' => $this->created_by,
            'my_role' => $currentUserPivot?->role,
            'is_pinned' => (bool) ($currentUserPivot?->is_pinned ?? false),
            'pinned_at' => $currentUserPivot?->pinned_at ? \Illuminate\Support\Carbon::parse($currentUserPivot->pinned_at)->toIso8601String() : null,
            'is_archived' => (bool) ($currentUserPivot?->is_archived ?? false),
            'archived_at' => $currentUserPivot?->archived_at ? \Illuminate\Support\Carbon::parse($currentUserPivot->archived_at)->toIso8601String() : null,
            'participants_count' => $this->users->count(),
            'participants' => $this->users->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'avatar_url' => $user->avatar_url,
                'role' => $user->pivot?->role ?? 'member',
                'is_online' => (bool) ($user->presence?->is_online ?? false),
            ])->values(),
            'other_user' => $otherUser ? new UserResource($otherUser) : null,
            'last_message' => $this->latestMessage ? new MessageResource($this->latestMessage) : null,
            'unread_count' => (int) ($this->unread_count ?? 0),
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
