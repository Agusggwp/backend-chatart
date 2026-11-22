<?php

namespace App\Events;

use App\Models\Call;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallSignalSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Call $call,
        public readonly int $senderId,
        public readonly int $targetUserId,
        public readonly string $signalType,
        public readonly mixed $signalData
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->targetUserId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'call.signal';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'call_id' => $this->call->id,
            'sender_id' => $this->senderId,
            'signal_type' => $this->signalType,
            'signal_data' => $this->signalData,
        ];
    }
}
