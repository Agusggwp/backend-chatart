<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<int>  $participantIds
     */
    public function __construct(
        public readonly Message $message,
        public readonly array $participantIds,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("conversation.{$this->message->conversation_id}"),
        ];

        foreach ($this->participantIds as $participantId) {
            $channels[] = new PrivateChannel("user.{$participantId}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $sender = $this->message->sender;
        $replyTo = $this->message->replyTo;

        return [
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'sender_id' => $this->message->sender_id,
                'body' => $this->message->is_deleted_for_everyone ? 'Pesan ini telah dihapus' : $this->message->body,
                'type' => $this->message->type,
                'attachment_url' => $this->message->full_attachment_url,
                'attachment_type' => $this->message->attachment_type,
                'attachment_size' => $this->message->attachment_size,
                'attachment_duration' => $this->message->attachment_duration,
                'is_deleted_for_everyone' => (bool) $this->message->is_deleted_for_everyone,
                'read_at' => $this->message->read_at?->toIso8601String(),
                'created_at' => $this->message->created_at?->toIso8601String(),
                'sender' => $sender ? [
                    'id' => $sender->id,
                    'name' => $sender->name,
                    'username' => $sender->username,
                    'avatar_url' => $sender->avatar_url,
                ] : null,
                'reply_to' => $replyTo ? [
                    'id' => $replyTo->id,
                    'sender_name' => $replyTo->sender?->name ?? 'Pengguna',
                    'body' => $replyTo->is_deleted_for_everyone ? 'Pesan ini telah dihapus' : $replyTo->body,
                    'type' => $replyTo->type,
                    'attachment_url' => $replyTo->full_attachment_url,
                ] : null,
            ],
        ];
    }
}
