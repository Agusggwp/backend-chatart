<?php

namespace Tests\Feature\Api;

use App\Events\MessageReactionUpdated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessageReactionAndPinTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_react_to_message_and_broadcast_event(): void
    {
        Event::fake([MessageReactionUpdated::class]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation = Conversation::create(['type' => Conversation::TYPE_PRIVATE]);
        $conversation->participants()->createMany([
            ['user_id' => $user1->id],
            ['user_id' => $user2->id],
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user1->id,
            'body' => 'Kabar baik semuanya!',
            'type' => 'text',
        ]);

        Sanctum::actingAs($user2);

        $response = $this->postJson("/api/messages/{$message->id}/reactions", [
            'emoji' => '👍',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.action', 'added')
            ->assertJsonPath('data.emoji', '👍')
            ->assertJsonPath('data.reactions.0.emoji', '👍')
            ->assertJsonPath('data.reactions.0.count', 1);

        $this->assertDatabaseHas('message_reactions', [
            'message_id' => $message->id,
            'user_id' => $user2->id,
            'emoji' => '👍',
        ]);

        Event::assertDispatched(MessageReactionUpdated::class, function ($event) use ($message, $user2) {
            return $event->messageId === $message->id &&
                $event->userId === $user2->id &&
                $event->emoji === '👍' &&
                $event->action === 'added';
        });
    }

    public function test_user_can_toggle_reaction_off_by_tapping_same_emoji(): void
    {
        Event::fake([MessageReactionUpdated::class]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation = Conversation::create(['type' => Conversation::TYPE_PRIVATE]);
        $conversation->participants()->createMany([
            ['user_id' => $user1->id],
            ['user_id' => $user2->id],
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user1->id,
            'body' => 'Hebat sekali',
            'type' => 'text',
        ]);

        Sanctum::actingAs($user2);

        // Add ❤️
        $this->postJson("/api/messages/{$message->id}/reactions", ['emoji' => '❤️']);
        $this->assertDatabaseHas('message_reactions', [
            'message_id' => $message->id,
            'user_id' => $user2->id,
            'emoji' => '❤️',
        ]);

        // Tap ❤️ again -> should remove
        $response = $this->postJson("/api/messages/{$message->id}/reactions", ['emoji' => '❤️']);

        $response->assertOk()
            ->assertJsonPath('data.action', 'removed')
            ->assertJsonPath('data.emoji', null);

        $this->assertDatabaseMissing('message_reactions', [
            'message_id' => $message->id,
            'user_id' => $user2->id,
        ]);
    }

    public function test_user_can_pin_and_unpin_conversation(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation = Conversation::create(['type' => Conversation::TYPE_PRIVATE]);
        $conversation->participants()->createMany([
            ['user_id' => $user1->id],
            ['user_id' => $user2->id],
        ]);

        Sanctum::actingAs($user1);

        // Pin
        $response = $this->postJson("/api/conversations/{$conversation->id}/pin");
        $response->assertOk()
            ->assertJsonPath('data.is_pinned', true);

        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $user1->id,
            'is_pinned' => true,
        ]);

        // Unpin
        $response2 = $this->postJson("/api/conversations/{$conversation->id}/pin");
        $response2->assertOk()
            ->assertJsonPath('data.is_pinned', false);

        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $user1->id,
            'is_pinned' => false,
        ]);
    }

    public function test_user_can_archive_and_unarchive_conversation_and_list_archived(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation = Conversation::create(['type' => Conversation::TYPE_PRIVATE]);
        $conversation->participants()->createMany([
            ['user_id' => $user1->id],
            ['user_id' => $user2->id],
        ]);

        Sanctum::actingAs($user1);

        // Archive
        $response = $this->postJson("/api/conversations/{$conversation->id}/archive");
        $response->assertOk()
            ->assertJsonPath('data.is_archived', true);

        // Regular conversations list should NOT include archived conversation
        $listRes = $this->getJson('/api/conversations');
        $listRes->assertOk();
        $this->assertCount(0, $listRes->json('data.conversations'));

        // Archived list should include it
        $archivedRes = $this->getJson('/api/conversations/archived');
        $archivedRes->assertOk();
        $this->assertCount(1, $archivedRes->json('data.conversations'));
        $this->assertEquals($conversation->id, $archivedRes->json('data.conversations.0.id'));

        // Unarchive
        $unarchiveRes = $this->postJson("/api/conversations/{$conversation->id}/archive");
        $unarchiveRes->assertOk()
            ->assertJsonPath('data.is_archived', false);

        // Regular list now contains it again
        $listRes2 = $this->getJson('/api/conversations');
        $this->assertCount(1, $listRes2->json('data.conversations'));
    }
}
