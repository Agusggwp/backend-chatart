<?php

namespace Tests\Feature\Api;

use App\Events\CallSignalSent;
use App\Models\Call;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use App\Models\UserPresence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PrivacyAndWebRTCTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_get_and_update_privacy_settings(): void
    {
        $user = User::factory()->create([
            'privacy_last_seen' => 'everyone',
            'privacy_status' => 'everyone',
            'privacy_read_receipts' => true,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/profile/privacy');

        $response->assertOk()
            ->assertJsonPath('data.privacy_last_seen', 'everyone')
            ->assertJsonPath('data.privacy_status', 'everyone')
            ->assertJsonPath('data.privacy_read_receipts', true);

        $updateResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/profile/privacy', [
                'privacy_last_seen' => 'nobody',
                'privacy_status' => 'contacts',
                'privacy_read_receipts' => false,
            ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.privacy.privacy_last_seen', 'nobody')
            ->assertJsonPath('data.privacy.privacy_status', 'contacts')
            ->assertJsonPath('data.privacy.privacy_read_receipts', false);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'privacy_last_seen' => 'nobody',
            'privacy_status' => 'contacts',
            'privacy_read_receipts' => false,
        ]);
    }

    public function test_privacy_last_seen_masks_presence_when_set_to_nobody(): void
    {
        $alice = User::factory()->create(['privacy_last_seen' => 'nobody']);
        UserPresence::create([
            'user_id' => $alice->id,
            'is_online' => true,
            'last_seen_at' => now(),
        ]);

        $bob = User::factory()->create();
        $bobToken = $bob->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$bobToken}")
            ->getJson("/api/users/{$alice->id}");

        $response->assertOk()
            ->assertJsonPath('data.user.is_online', false)
            ->assertJsonPath('data.user.last_seen_at', null);
    }

    public function test_user_can_initiate_video_call_and_send_webrtc_signals(): void
    {
        Event::fake([CallSignalSent::class]);

        $caller = User::factory()->create();
        $receiver = User::factory()->create();
        $callerToken = $caller->createToken('test')->plainTextToken;

        $initiateResponse = $this->withHeader('Authorization', "Bearer {$callerToken}")
            ->postJson('/api/calls', [
                'receiver_id' => $receiver->id,
                'type' => 'video',
            ]);

        $initiateResponse->assertCreated()
            ->assertJsonPath('data.call.type', 'video')
            ->assertJsonPath('data.call.status', 'ringing');

        $callId = $initiateResponse->json('data.call.id');

        // Caller sends WebRTC offer
        $signalResponse = $this->withHeader('Authorization', "Bearer {$callerToken}")
            ->postJson("/api/calls/{$callId}/signal", [
                'signal_type' => 'offer',
                'signal_data' => ['sdp' => 'v=0\r\no=...'],
            ]);

        $signalResponse->assertOk();

        Event::assertDispatched(CallSignalSent::class, function ($event) use ($callId, $caller, $receiver) {
            return $event->call->id === $callId
                && $event->senderId === $caller->id
                && $event->targetUserId === $receiver->id
                && $event->signalType === 'offer';
        });
    }
}
