<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<int>  $participantIds
     */
    public function __construct(
        public readonly int $conversationId,
        public readonly array $participantIds,
        public readonly string $action = 'updated',
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
        return 'group.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'action' => $this->action,
        ];
    }
}
