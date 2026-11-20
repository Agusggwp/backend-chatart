<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReactionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param list<array<string, mixed>> $reactions
     * @param list<int> $participantIds
     */
    public function __construct(
        public readonly int $messageId,
        public readonly int $conversationId,
        public readonly int $userId,
        public readonly ?string $emoji,
        public readonly string $action, // 'added', 'removed', 'updated'
        public readonly array $reactions,
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
        return 'message.reaction';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'conversation_id' => $this->conversationId,
            'user_id' => $this->userId,
            'emoji' => $this->emoji,
            'action' => $this->action,
            'reactions' => $this->reactions,
        ];
    }
}
