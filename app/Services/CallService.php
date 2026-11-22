<?php

namespace App\Services;

use App\Events\CallIncoming;
use App\Events\CallSignalSent;
use App\Events\CallStatusUpdated;
use App\Models\Call;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class CallService
{
    /**
     * List call logs for user ordered by newest first.
     */
    public function listCalls(User $user, int $perPage = 30): LengthAwarePaginator
    {
        return Call::query()
            ->where(function ($q) use ($user) {
                $q->where('caller_id', $user->id)
                    ->orWhere('receiver_id', $user->id);
            })
            ->with(['caller', 'receiver'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Initiate a new audio or video call and broadcast to receiver.
     */
    public function initiateCall(
        User $caller,
        int $receiverId,
        ?int $conversationId = null,
        string $type = Call::TYPE_AUDIO,
    ): Call {
        if ($caller->id === $receiverId) {
            throw new UnprocessableEntityHttpException('Tidak dapat melakukan panggilan ke diri sendiri.');
        }

        if ($caller->isBlockedBy($receiverId)) {
            throw new AccessDeniedHttpException('Tidak dapat melakukan panggilan karena telah diblokir oleh pengguna ini.');
        }

        $call = Call::create([
            'caller_id' => $caller->id,
            'receiver_id' => $receiverId,
            'conversation_id' => $conversationId,
            'type' => $type,
            'status' => Call::STATUS_RINGING,
            'started_at' => null,
            'ended_at' => null,
        ]);

        $call->load(['caller', 'receiver']);

        $this->safeBroadcast(new CallIncoming($call));

        return $call;
    }

    /**
     * Receiver accepts incoming call.
     */
    public function acceptCall(User $user, Call $call): Call
    {
        if ((int) $call->receiver_id !== (int) $user->id) {
            throw new AccessDeniedHttpException('Anda bukan penerima panggilan ini.');
        }

        if ($call->status === Call::STATUS_RINGING) {
            $call->update([
                'status' => Call::STATUS_ACCEPTED,
                'started_at' => now(),
            ]);

            $this->safeBroadcast(new CallStatusUpdated($call));
        }

        return $call;
    }

    /**
     * Receiver rejects incoming call.
     */
    public function rejectCall(User $user, Call $call): Call
    {
        if ((int) $call->receiver_id !== (int) $user->id) {
            throw new AccessDeniedHttpException('Anda bukan penerima panggilan ini.');
        }

        $call->update([
            'status' => Call::STATUS_REJECTED,
            'ended_at' => now(),
        ]);

        $this->safeBroadcast(new CallStatusUpdated($call));

        return $call;
    }

    /**
     * End ongoing or ringing call.
     */
    public function endCall(User $user, Call $call): Call
    {
        $isCaller = (int) $call->caller_id === (int) $user->id;
        $isReceiver = (int) $call->receiver_id === (int) $user->id;

        if (! $isCaller && ! $isReceiver) {
            throw new AccessDeniedHttpException('Anda bukan peserta panggilan ini.');
        }

        $now = now();
        $status = Call::STATUS_ENDED;
        $duration = 0;

        if ($call->status === Call::STATUS_RINGING) {
            $status = $isCaller ? Call::STATUS_MISSED : Call::STATUS_REJECTED;
        } elseif ($call->status === Call::STATUS_ACCEPTED) {
            $status = Call::STATUS_ENDED;
            $duration = $call->started_at !== null ? (int) abs($call->started_at->diffInSeconds($now)) : 0;
        }

        $call->update([
            'status' => $status,
            'duration' => $duration,
            'ended_at' => $now,
        ]);

        $this->safeBroadcast(new CallStatusUpdated($call));

        return $call;
    }

    /**
     * Send WebRTC signal (offer, answer, ice-candidate) to the other participant.
     */
    public function sendSignal(User $user, Call $call, string $signalType, mixed $signalData): void
    {
        $isCaller = (int) $call->caller_id === (int) $user->id;
        $isReceiver = (int) $call->receiver_id === (int) $user->id;

        if (! $isCaller && ! $isReceiver) {
            throw new AccessDeniedHttpException('Anda bukan peserta panggilan ini.');
        }

        $targetUserId = $isCaller ? (int) $call->receiver_id : (int) $call->caller_id;

        $this->safeBroadcast(new CallSignalSent($call, (int) $user->id, $targetUserId, $signalType, $signalData));
    }

    private function safeBroadcast(object $event): void
    {
        try {
            broadcast($event)->toOthers();
        } catch (\Throwable $e) {
            Log::warning('Call Broadcast gagal dikirim: '.$e->getMessage());
        }
    }
}
