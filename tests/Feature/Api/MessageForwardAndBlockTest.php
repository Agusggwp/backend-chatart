<?php

namespace Tests\Feature\Api;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessageForwardAndBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_block_and_unblock_another_user_and_view_blocked_list(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Sanctum::actingAs($userA);

        // Block user B
        $response = $this->postJson("/api/users/{$userB->id}/block");
        $response->assertOk()
            ->assertJsonPath('data.is_blocked', true);

        // Check blocked list
        $listResponse = $this->getJson('/api/users/blocked');
        $listResponse->assertOk()
            ->assertJsonCount(1, 'data.users')
            ->assertJsonPath('data.users.0.id', $userB->id);

        // Unblock user B
        $unblockResponse = $this->postJson("/api/users/{$userB->id}/unblock");
        $unblockResponse->assertOk()
            ->assertJsonPath('data.is_blocked', false);

        // Check blocked list again
        $listResponse2 = $this->getJson('/api/users/blocked');
        $listResponse2->assertOk()
            ->assertJsonCount(0, 'data.users');
    }

    public function test_blocked_user_cannot_send_message_in_private_chat(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // Create private conversation between userA and userB
        $conversation = Conversation::create(['type' => 'private']);
        ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $userA->id, 'role' => 'member']);
        ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $userB->id, 'role' => 'member']);

        // User A blocks User B
        Sanctum::actingAs($userA);
        $this->postJson("/api/users/{$userB->id}/block")->assertOk();

        // User B tries to send message
        Sanctum::actingAs($userB);
        $response = $this->postJson("/api/conversations/{$conversation->id}/messages", [
            'body' => 'Halo, apakah kamu ada di sana?',
        ]);

        $response->assertStatus(403);
    }

    public function test_blocked_user_cannot_call_user_who_blocked_them(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // User A blocks User B
        Sanctum::actingAs($userA);
        $this->postJson("/api/users/{$userB->id}/block")->assertOk();

        // User B tries to call User A
        Sanctum::actingAs($userB);
        $response = $this->postJson('/api/calls', [
            'receiver_id' => $userA->id,
            'type' => 'audio',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_forward_message_to_multiple_conversations(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();

        // Conversation 1 (A & B)
        $conv1 = Conversation::create(['type' => 'private']);
        ConversationParticipant::create(['conversation_id' => $conv1->id, 'user_id' => $userA->id, 'role' => 'member']);
        ConversationParticipant::create(['conversation_id' => $conv1->id, 'user_id' => $userB->id, 'role' => 'member']);

        // Conversation 2 (A & C)
        $conv2 = Conversation::create(['type' => 'private']);
        ConversationParticipant::create(['conversation_id' => $conv2->id, 'user_id' => $userA->id, 'role' => 'member']);
        ConversationParticipant::create(['conversation_id' => $conv2->id, 'user_id' => $userC->id, 'role' => 'member']);

        // Original message in conv1
        $message = Message::create([
            'conversation_id' => $conv1->id,
            'sender_id' => $userB->id,
            'type' => 'text',
            'body' => 'Pengumuman penting acara reuni',
        ]);

        Sanctum::actingAs($userA);

        $response = $this->postJson('/api/messages/forward', [
            'message_id' => $message->id,
            'conversation_ids' => [$conv1->id, $conv2->id],
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data.messages');

        // Check messages in conv2
        $conv2Messages = Message::where('conversation_id', $conv2->id)->get();
        $this->assertCount(1, $conv2Messages);
        $this->assertEquals('Pengumuman penting acara reuni', $conv2Messages->first()->body);
        $this->assertTrue((bool) $conv2Messages->first()->is_forwarded);
        $this->assertEquals($userA->id, $conv2Messages->first()->sender_id);
    }
}
