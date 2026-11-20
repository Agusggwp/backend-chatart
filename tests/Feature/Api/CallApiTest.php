<?php

namespace Tests\Feature\Api;

use App\Events\CallIncoming;
use App\Events\CallStatusUpdated;
use App\Models\Call;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CallApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_initiate_audio_call(): void
    {
        Event::fake([CallIncoming::class]);

        $caller = User::factory()->create();
        $receiver = User::factory()->create();

        $response = $this->actingAs($caller)
            ->postJson('/api/calls', [
                'receiver_id' => $receiver->id,
                'type' => 'audio',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.call.status', 'ringing')
            ->assertJsonPath('data.call.caller_id', $caller->id)
            ->assertJsonPath('data.call.receiver_id', $receiver->id)
            ->assertJsonPath('data.call.type', 'audio');

        Event::assertDispatched(CallIncoming::class, function ($event) use ($receiver) {
            return (int) $event->call->receiver_id === (int) $receiver->id;
        });
    }

    public function test_user_cannot_call_themselves(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/calls', [
                'receiver_id' => $user->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_receiver_can_accept_call(): void
    {
        Event::fake([CallStatusUpdated::class]);

        $caller = User::factory()->create();
        $receiver = User::factory()->create();

        $call = Call::create([
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
            'type' => 'audio',
            'status' => 'ringing',
        ]);

        $response = $this->actingAs($receiver)
            ->postJson("/api/calls/{$call->id}/accept");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.call.status', 'accepted');

        $this->assertNotNull($response->json('data.call.started_at'));

        Event::assertDispatched(CallStatusUpdated::class, function ($event) {
            return $event->call->status === 'accepted';
        });
    }

    public function test_receiver_can_reject_call(): void
    {
        Event::fake([CallStatusUpdated::class]);

        $caller = User::factory()->create();
        $receiver = User::factory()->create();

        $call = Call::create([
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
            'type' => 'audio',
            'status' => 'ringing',
        ]);

        $response = $this->actingAs($receiver)
            ->postJson("/api/calls/{$call->id}/reject");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.call.status', 'rejected');

        Event::assertDispatched(CallStatusUpdated::class, function ($event) {
            return $event->call->status === 'rejected';
        });
    }

    public function test_participant_can_end_accepted_call_and_duration_is_calculated(): void
    {
        Event::fake([CallStatusUpdated::class]);

        $caller = User::factory()->create();
        $receiver = User::factory()->create();

        $call = Call::create([
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
            'type' => 'audio',
            'status' => 'accepted',
            'started_at' => now()->subSeconds(45),
        ]);

        $response = $this->actingAs($caller)
            ->postJson("/api/calls/{$call->id}/end");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.call.status', 'ended');

        $this->assertGreaterThanOrEqual(44, $response->json('data.call.duration'));

        Event::assertDispatched(CallStatusUpdated::class, function ($event) {
            return $event->call->status === 'ended';
        });
    }

    public function test_user_can_list_call_history(): void
    {
        $user = User::factory()->create();
        $other1 = User::factory()->create();
        $other2 = User::factory()->create();

        Call::create([
            'caller_id' => $user->id,
            'receiver_id' => $other1->id,
            'type' => 'audio',
            'status' => 'ended',
            'duration' => 120,
        ]);

        Call::create([
            'caller_id' => $other2->id,
            'receiver_id' => $user->id,
            'type' => 'audio',
            'status' => 'missed',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/calls');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.calls');
    }

    public function test_user_can_register_and_remove_device_token(): void
    {
        $user = User::factory()->create();

        $registerResponse = $this->actingAs($user)
            ->postJson('/api/devices/token', [
                'token' => 'fake_fcm_token_1234567890',
                'platform' => 'android',
                'device_name' => 'Pixel 8 Pro',
            ]);

        $registerResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'fake_fcm_token_1234567890',
            'platform' => 'android',
        ]);

        $deleteResponse = $this->actingAs($user)
            ->deleteJson('/api/devices/token', [
                'token' => 'fake_fcm_token_1234567890',
            ]);

        $deleteResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('device_tokens', [
            'token' => 'fake_fcm_token_1234567890',
        ]);
    }
}
