<?php

namespace App\Events;

use App\Models\Call;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallIncoming implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Call $call
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->call->receiver_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'call.incoming';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $caller = $this->call->caller;

        return [
            'call' => [
                'id' => $this->call->id,
                'caller_id' => $this->call->caller_id,
                'receiver_id' => $this->call->receiver_id,
                'conversation_id' => $this->call->conversation_id,
                'type' => $this->call->type,
                'status' => $this->call->status,
                'created_at' => $this->call->created_at?->toIso8601String(),
                'is_outgoing' => false,
                'other_user' => $caller ? [
                    'id' => $caller->id,
                    'name' => $caller->name,
                    'username' => $caller->username,
                    'avatar_url' => $caller->avatar_url,
                ] : null,
                'caller' => $caller ? [
                    'id' => $caller->id,
                    'name' => $caller->name,
                    'username' => $caller->username,
                    'avatar_url' => $caller->avatar_url,
                ] : null,
            ],
        ];
    }
}
