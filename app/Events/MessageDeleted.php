<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<int>  $participantIds
     */
    public function __construct(
        public readonly int $messageId,
        public readonly int $conversationId,
        public readonly int $deletedByUserId,
        public readonly bool $isDeletedForEveryone,
        public readonly array $participantIds = [],
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("conversation.{$this->conversationId}"),
        ];

        foreach ($this->participantIds as $participantId) {
            $channels[] = new PrivateChannel("user.{$participantId}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'message.deleted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'conversation_id' => $this->conversationId,
            'deleted_by_user_id' => $this->deletedByUserId,
            'is_deleted_for_everyone' => $this->isDeletedForEveryone,
        ];
    }
}
