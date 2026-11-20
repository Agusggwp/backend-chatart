<?php

namespace App\Events;

use App\Models\Call;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallStatusUpdated implements ShouldBroadcastNow
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
            new PrivateChannel("user.{$this->call->caller_id}"),
            new PrivateChannel("user.{$this->call->receiver_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'call.status_updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->call->loadMissing(['caller', 'receiver']);
        $caller = $this->call->caller;
        $receiver = $this->call->receiver;

        return [
            'call' => [
                'id' => $this->call->id,
                'caller_id' => $this->call->caller_id,
                'receiver_id' => $this->call->receiver_id,
                'status' => $this->call->status,
                'duration' => $this->call->duration,
                'started_at' => $this->call->started_at?->toIso8601String(),
                'ended_at' => $this->call->ended_at?->toIso8601String(),
                'caller' => $caller ? [
                    'id' => $caller->id,
                    'name' => $caller->name,
                    'username' => $caller->username,
                    'avatar_url' => $caller->avatar_url,
                ] : null,
                'receiver' => $receiver ? [
                    'id' => $receiver->id,
                    'name' => $receiver->name,
                    'username' => $receiver->username,
                    'avatar_url' => $receiver->avatar_url,
                ] : null,
            ],
        ];
    }
}
