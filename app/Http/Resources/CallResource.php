<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Call
 */
class CallResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentUserId = $request->user()?->id;
        $isOutgoing = $currentUserId !== null && (int) $this->caller_id === (int) $currentUserId;
        $otherUser = $isOutgoing ? $this->receiver : $this->caller;

        return [
            'id' => $this->id,
            'caller_id' => $this->caller_id,
            'receiver_id' => $this->receiver_id,
            'conversation_id' => $this->conversation_id,
            'type' => $this->type,
            'status' => $this->status,
            'duration' => $this->duration,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'is_outgoing' => $isOutgoing,
            'is_missed' => $this->status === 'missed' || ($this->status === 'ringing' && ! $isOutgoing),
            'other_user' => $otherUser ? [
                'id' => $otherUser->id,
                'name' => $otherUser->name,
                'username' => $otherUser->username,
                'avatar_url' => $otherUser->avatar_url,
            ] : null,
            'caller' => $this->whenLoaded('caller', fn () => [
                'id' => $this->caller->id,
                'name' => $this->caller->name,
                'username' => $this->caller->username,
                'avatar_url' => $this->caller->avatar_url,
            ]),
            'receiver' => $this->whenLoaded('receiver', fn () => [
                'id' => $this->receiver->id,
                'name' => $this->receiver->name,
                'username' => $this->receiver->username,
                'avatar_url' => $this->receiver->avatar_url,
            ]),
        ];
    }
}
