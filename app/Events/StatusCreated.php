<?php

namespace App\Events;

use App\Http\Resources\StatusResource;
use App\Models\Status;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StatusCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Status $status;

    public function __construct(Status $status)
    {
        $this->status = $status->loadMissing('user.presence');
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('statuses'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'status.created';
    }

    public function broadcastWith(): array
    {
        return [
            'status' => (new StatusResource($this->status))->resolve(),
        ];
    }
}
