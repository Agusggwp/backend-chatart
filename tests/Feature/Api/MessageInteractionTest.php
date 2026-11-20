<?php

namespace Tests\Feature\Api;

use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MessageInteractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_send_image_message(): void
    {
        Storage::fake('public');
        Event::fake([MessageSent::class]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation = Conversation::create(['type' => Conversation::TYPE_PRIVATE]);
        $conversation->participants()->createMany([
            ['user_id' => $user1->id],
            ['user_id' => $user2->id],
        ]);

        $image = UploadedFile::fake()->image('photo.jpg', 600, 400);

        $response = $this->actingAs($user1)
            ->postJson("/api/conversations/{$conversation->id}/messages", [
                'body' => 'Lihat foto ini!',
                'attachment' => $image,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message.type', 'image')
            ->assertJsonPath('data.message.body', 'Lihat foto ini!')
            ->assertJsonPath('data.message.is_deleted_for_everyone', false);

        $this->assertNotNull($response->json('data.message.attachment_url'));

        Event::assertDispatched(MessageSent::class, function ($event) {
            return $event->message->type === 'image';
        });
    }

    public function test_user_can_reply_to_an_existing_message(): void
    {
        Event::fake([MessageSent::class]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation = Conversation::create(['type' => Conversation::TYPE_PRIVATE]);
        $conversation->participants()->createMany([
            ['user_id' => $user1->id],
            ['user_id' => $user2->id],
        ]);

        $firstMessage = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user1->id,
            'body' => 'Pesan pertama yang akan dibalas',
            'type' => 'text',
        ]);

        $response = $this->actingAs($user2)
            ->postJson("/api/conversations/{$conversation->id}/messages", [
                'body' => 'Ini jawaban balasan saya',
                'reply_to_id' => $firstMessage->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message.reply_to.id', $firstMessage->id)
            ->assertJsonPath('data.message.reply_to.body', 'Pesan pertama yang akan dibalas');
    }

    public function test_author_can_delete_message_for_everyone(): void
    {
        Storage::fake('public');
        Event::fake([MessageDeleted::class]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation = Conversation::create(['type' => Conversation::TYPE_PRIVATE]);
        $conversation->participants()->createMany([
            ['user_id' => $user1->id],
            ['user_id' => $user2->id],
        ]);

        $filePath = 'chat_media/test/photo.jpg';
        Storage::disk('public')->put($filePath, 'fake content');

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user1->id,
            'body' => 'Pesan rahasia yang ingin ditarik',
            'type' => 'image',
            'attachment_url' => $filePath,
        ]);

        $response = $this->actingAs($user1)
            ->deleteJson("/api/conversations/{$conversation->id}/messages/{$message->id}", [
                'delete_type' => 'for_everyone',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message.is_deleted_for_everyone', true)
            ->assertJsonPath('data.message.body', 'Pesan ini telah dihapus')
            ->assertJsonPath('data.message.attachment_url', null);

        Storage::disk('public')->assertMissing($filePath);

        Event::assertDispatched(MessageDeleted::class, function ($event) use ($message) {
            return $event->messageId === $message->id && $event->isDeletedForEveryone === true;
        });
    }

    public function test_non_author_cannot_delete_message_for_everyone(): void
    {
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
            'body' => 'Pesan milik user 1',
            'type' => 'text',
        ]);

        $response = $this->actingAs($user2)
            ->deleteJson("/api/conversations/{$conversation->id}/messages/{$message->id}", [
                'delete_type' => 'for_everyone',
            ]);

        $response->assertStatus(403);
    }

    public function test_user_can_delete_message_for_me_and_it_is_hidden_from_their_list(): void
    {
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
            'body' => 'Pesan yang hanya dihapus di sisi user 2',
            'type' => 'text',
        ]);

        // User 2 deletes for me
        $deleteResponse = $this->actingAs($user2)
            ->deleteJson("/api/conversations/{$conversation->id}/messages/{$message->id}", [
                'delete_type' => 'for_me',
            ]);

        $deleteResponse->assertStatus(200);

        // User 2 fetches messages -> message is NOT present
        $listResponseUser2 = $this->actingAs($user2)
            ->getJson("/api/conversations/{$conversation->id}/messages");

        $listResponseUser2->assertStatus(200);
        $idsUser2 = collect($listResponseUser2->json('data.messages'))->pluck('id')->all();
        $this->assertNotContains($message->id, $idsUser2);

        // User 1 fetches messages -> message IS still present!
        $listResponseUser1 = $this->actingAs($user1)
            ->getJson("/api/conversations/{$conversation->id}/messages");

        $listResponseUser1->assertStatus(200);
        $idsUser1 = collect($listResponseUser1->json('data.messages'))->pluck('id')->all();
        $this->assertContains($message->id, $idsUser1);
    }

    public function test_user_can_send_voice_note_message(): void
    {
        Storage::fake('public');
        Event::fake([MessageSent::class]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation = Conversation::create(['type' => Conversation::TYPE_PRIVATE]);
        $conversation->participants()->createMany([
            ['user_id' => $user1->id],
            ['user_id' => $user2->id],
        ]);

        $audioFile = UploadedFile::fake()->create('voice_note.m4a', 250, 'audio/m4a');

        $response = $this->actingAs($user1)
            ->postJson("/api/conversations/{$conversation->id}/messages", [
                'attachment' => $audioFile,
                'duration' => 14,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message.type', 'voice')
            ->assertJsonPath('data.message.attachment_duration', 14);

        $this->assertNotNull($response->json('data.message.attachment_url'));

        Event::assertDispatched(MessageSent::class, function ($event) {
            return $event->message->type === 'voice' && $event->message->attachment_duration === 14;
        });
    }

    public function test_user_can_send_document_message(): void
    {
        Storage::fake('public');
        Event::fake([MessageSent::class]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation = Conversation::create(['type' => Conversation::TYPE_PRIVATE]);
        $conversation->participants()->createMany([
            ['user_id' => $user1->id],
            ['user_id' => $user2->id],
        ]);

        $docFile = UploadedFile::fake()->create('Dokumen_Spesifikasi.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($user1)
            ->postJson("/api/conversations/{$conversation->id}/messages", [
                'attachment' => $docFile,
                'body' => 'Silakan tinjau berkas ini',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message.type', 'document')
            ->assertJsonPath('data.message.file_name', 'Dokumen_Spesifikasi.pdf')
            ->assertJsonPath('data.message.body', 'Silakan tinjau berkas ini');

        $this->assertNotNull($response->json('data.message.attachment_url'));
    }

    public function test_user_can_toggle_star_message_and_view_starred_list(): void
    {
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
            'body' => 'Pesan penting yang harus diingat',
            'type' => 'text',
        ]);

        // Star message
        $starRes = $this->actingAs($user1)->postJson("/api/messages/{$message->id}/star");
        $starRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_starred', true);

        // Get starred messages
        $listRes = $this->actingAs($user1)->getJson('/api/messages/starred');
        $listRes->assertStatus(200)
            ->assertJsonCount(1, 'data.messages')
            ->assertJsonPath('data.messages.0.id', $message->id)
            ->assertJsonPath('data.messages.0.is_starred', true);

        // Unstar message
        $unstarRes = $this->actingAs($user1)->postJson("/api/messages/{$message->id}/star");
        $unstarRes->assertStatus(200)
            ->assertJsonPath('data.is_starred', false);

        // Starred list is now empty
        $emptyRes = $this->actingAs($user1)->getJson('/api/messages/starred');
        $emptyRes->assertStatus(200)
            ->assertJsonCount(0, 'data.messages');
    }

    public function test_user_can_search_messages_in_conversation(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation = Conversation::create(['type' => Conversation::TYPE_PRIVATE]);
        $conversation->participants()->createMany([
            ['user_id' => $user1->id],
            ['user_id' => $user2->id],
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user1->id,
            'body' => 'Laporan keuangan kuartal 4 telah selesai',
            'type' => 'text',
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user2->id,
            'body' => 'Baik, nanti saya cek ya',
            'type' => 'text',
        ]);

        // Search for 'keuangan'
        $searchRes = $this->actingAs($user1)->getJson("/api/conversations/{$conversation->id}/search?q=keuangan");
        $searchRes->assertStatus(200)
            ->assertJsonCount(1, 'data.messages')
            ->assertJsonPath('data.messages.0.body', 'Laporan keuangan kuartal 4 telah selesai');

        // Search non-existent
        $emptySearch = $this->actingAs($user1)->getJson("/api/conversations/{$conversation->id}/search?q=xyz123");
        $emptySearch->assertStatus(200)
            ->assertJsonCount(0, 'data.messages');
    }
}
