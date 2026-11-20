<?php

namespace Tests\Feature\Api;

use App\Events\MessagesRead;
use App\Events\MessageSent;
use App\Events\UserTyping;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_start_or_reuse_private_conversation(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        Sanctum::actingAs($alice);

        $first = $this->postJson('/api/conversations', ['user_id' => $bob->id]);
        $first->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.conversation.other_user.id', $bob->id);

        $conversationId = $first->json('data.conversation.id');

        // Calling again must return the exact same conversation (no duplicates)
        $second = $this->postJson('/api/conversations', ['user_id' => $bob->id]);
        $second->assertCreated()
            ->assertJsonPath('data.conversation.id', $conversationId);

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('conversation_participants', 2);
    }

    public function test_user_cannot_start_conversation_with_self(): void
    {
        $alice = User::factory()->create();
        Sanctum::actingAs($alice);

        $this->postJson('/api/conversations', ['user_id' => $alice->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_user_can_send_message_and_broadcast_event(): void
    {
        Event::fake([MessageSent::class]);

        $alice = User::factory()->create();
        $bob = User::factory()->create();

        Sanctum::actingAs($alice);

        $conversationId = $this->postJson('/api/conversations', ['user_id' => $bob->id])
            ->json('data.conversation.id');

        $response = $this->postJson("/api/conversations/{$conversationId}/messages", [
            'body' => 'Halo Bob, tes real-time chat!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message.body', 'Halo Bob, tes real-time chat!')
            ->assertJsonPath('data.message.sender_id', $alice->id);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversationId,
            'sender_id' => $alice->id,
            'body' => 'Halo Bob, tes real-time chat!',
        ]);

        Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($conversationId, $bob): bool {
            return $event->message->conversation_id === $conversationId
                && in_array($bob->id, $event->participantIds, true);
        });
    }

    public function test_non_participant_cannot_access_or_send_in_conversation(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $intruder = User::factory()->create();

        $conversation = Conversation::create(['type' => Conversation::TYPE_PRIVATE]);
        $conversation->participants()->createMany([
            ['user_id' => $alice->id],
            ['user_id' => $bob->id],
        ]);

        Sanctum::actingAs($intruder);

        $this->getJson("/api/conversations/{$conversation->id}")->assertForbidden();
        $this->getJson("/api/conversations/{$conversation->id}/messages")->assertForbidden();
        $this->postJson("/api/conversations/{$conversation->id}/messages", [
            'body' => 'Intruder message',
        ])->assertForbidden();
    }

    public function test_conversations_list_tracks_unread_count_and_mark_as_read_resets_it(): void
    {
        Event::fake([MessageSent::class, MessagesRead::class]);

        $alice = User::factory()->create();
        $bob = User::factory()->create();

        Sanctum::actingAs($alice);
        $conversationId = $this->postJson('/api/conversations', ['user_id' => $bob->id])
            ->json('data.conversation.id');

        $this->postJson("/api/conversations/{$conversationId}/messages", ['body' => 'Pesan 1'])
            ->assertCreated();
        $this->postJson("/api/conversations/{$conversationId}/messages", ['body' => 'Pesan 2'])
            ->assertCreated();

        // Switch to Bob: should see unread_count = 2 and latest message = "Pesan 2"
        Sanctum::actingAs($bob);

        $this->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonPath('data.conversations.0.id', $conversationId)
            ->assertJsonPath('data.conversations.0.unread_count', 2)
            ->assertJsonPath('data.conversations.0.last_message.body', 'Pesan 2');

        // Mark as read
        $this->postJson("/api/conversations/{$conversationId}/read")
            ->assertOk()
            ->assertJsonPath('data.marked_count', 2);

        Event::assertDispatched(MessagesRead::class, function (MessagesRead $event) use ($conversationId, $bob): bool {
            return $event->conversationId === $conversationId && $event->readerId === $bob->id;
        });

        // Unread count should now be 0
        $this->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonPath('data.conversations.0.unread_count', 0);
    }

    public function test_user_can_broadcast_typing_indicator(): void
    {
        Event::fake([UserTyping::class]);

        $alice = User::factory()->create();
        $bob = User::factory()->create();

        Sanctum::actingAs($alice);
        $conversationId = $this->postJson('/api/conversations', ['user_id' => $bob->id])
            ->json('data.conversation.id');

        $this->postJson("/api/conversations/{$conversationId}/typing", [
            'is_typing' => true,
        ])->assertOk();

        Event::assertDispatched(UserTyping::class, function (UserTyping $event) use ($conversationId, $alice): bool {
            return $event->conversationId === $conversationId
                && $event->userId === $alice->id
                && $event->isTyping === true;
        });
    }
}
