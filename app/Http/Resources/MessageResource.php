<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Message
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isDeleted = (bool) $this->is_deleted_for_everyone;
        $replyTo = $this->relationLoaded('replyTo') ? $this->replyTo : null;

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'body' => $isDeleted ? 'Pesan ini telah dihapus' : $this->body,
            'type' => $this->type,
            'attachment_url' => $isDeleted ? null : $this->full_attachment_url,
            'file_name' => $isDeleted ? null : $this->file_name,
            'attachment_type' => $isDeleted ? null : $this->attachment_type,
            'attachment_size' => $isDeleted ? null : $this->attachment_size,
            'attachment_duration' => $isDeleted ? null : $this->attachment_duration,
            'is_starred' => $request->user() ? $this->isStarredBy($request->user()->id) : false,
            'reactions' => $this->reactionsSummary($request->user()?->id),
            'is_forwarded' => (bool) $this->is_forwarded,
            'is_deleted_for_everyone' => $isDeleted,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'sender' => $this->whenLoaded('sender', fn () => [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
                'username' => $this->sender->username,
                'avatar_url' => $this->sender->avatar_url,
            ]),
            'reply_to' => $replyTo ? [
                'id' => $replyTo->id,
                'sender_name' => $replyTo->sender?->name ?? 'Pengguna',
                'body' => $replyTo->is_deleted_for_everyone ? 'Pesan ini telah dihapus' : $replyTo->body,
                'type' => $replyTo->type,
                'attachment_url' => $replyTo->full_attachment_url,
            ] : null,
        ];
    }
}
