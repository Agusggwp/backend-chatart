<?php

namespace Tests\Feature\Api;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private User $user1;
    private User $user2;
    private string $token1;
    private string $token2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user1 = User::factory()->create([
            'email' => 'user1@artdevata.test',
            'username' => 'user1',
        ]);
        $this->user2 = User::factory()->create([
            'email' => 'user2@artdevata.test',
            'username' => 'user2',
        ]);

        $this->token1 = $this->user1->createToken('test-token')->plainTextToken;
        $this->token2 = $this->user2->createToken('test-token')->plainTextToken;
    }

    public function test_user_can_edit_sent_text_message(): void
    {
        $conv = Conversation::create(['type' => 'private']);
        $conv->participants()->createMany([
            ['user_id' => $this->user1->id],
            ['user_id' => $this->user2->id],
        ]);

        $msg = Message::create([
            'conversation_id' => $conv->id,
            'sender_id' => $this->user1->id,
            'body' => 'Pesan asli sebelum diedit',
            'type' => 'text',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token1)
            ->putJson("/api/conversations/{$conv->id}/messages/{$msg->id}", [
                'body' => 'Pesan telah berhasil diperbarui',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message.is_edited', true)
            ->assertJsonPath('data.message.body', 'Pesan telah berhasil diperbarui');

        $this->assertDatabaseHas('messages', [
            'id' => $msg->id,
            'body' => 'Pesan telah berhasil diperbarui',
            'original_body' => 'Pesan asli sebelum diedit',
            'is_edited' => true,
        ]);
    }

    public function test_user_can_mute_and_unmute_conversation(): void
    {
        $conv = Conversation::create(['type' => 'private']);
        $conv->participants()->createMany([
            ['user_id' => $this->user1->id],
            ['user_id' => $this->user2->id],
        ]);

        $muteRes = $this->withHeader('Authorization', 'Bearer ' . $this->token1)
            ->postJson("/api/conversations/{$conv->id}/mute", [
                'duration_minutes' => 60,
            ]);

        $muteRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_muted', true);

        $unmuteRes = $this->withHeader('Authorization', 'Bearer ' . $this->token1)
            ->postJson("/api/conversations/{$conv->id}/unmute");

        $unmuteRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_muted', false);
    }

    public function test_user_can_clear_conversation_history(): void
    {
        $conv = Conversation::create(['type' => 'private']);
        $conv->participants()->createMany([
            ['user_id' => $this->user1->id],
            ['user_id' => $this->user2->id],
        ]);

        Message::create([
            'conversation_id' => $conv->id,
            'sender_id' => $this->user2->id,
            'body' => 'Pesan lama',
            'type' => 'text',
            'created_at' => now()->subMinutes(10),
        ]);

        $clearRes = $this->withHeader('Authorization', 'Bearer ' . $this->token1)
            ->postJson("/api/conversations/{$conv->id}/clear");

        $clearRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $listRes = $this->withHeader('Authorization', 'Bearer ' . $this->token1)
            ->getJson("/api/conversations/{$conv->id}/messages");

        $listRes->assertStatus(200)
            ->assertJsonCount(0, 'data.messages');
    }

    public function test_user_can_export_conversation(): void
    {
        $conv = Conversation::create(['type' => 'private']);
        $conv->participants()->createMany([
            ['user_id' => $this->user1->id],
            ['user_id' => $this->user2->id],
        ]);

        Message::create([
            'conversation_id' => $conv->id,
            'sender_id' => $this->user1->id,
            'body' => 'Halo dari ekspor',
            'type' => 'text',
        ]);

        $jsonRes = $this->withHeader('Authorization', 'Bearer ' . $this->token1)
            ->getJson("/api/conversations/{$conv->id}/export?format=json");

        $jsonRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_messages', 1);

        $txtRes = $this->withHeader('Authorization', 'Bearer ' . $this->token1)
            ->get("/api/conversations/{$conv->id}/export?format=txt");

        $txtRes->assertStatus(200);
        $this->assertStringContainsString('Halo dari ekspor', $txtRes->getContent());
    }

    public function test_group_invite_link_and_join(): void
    {
        $group = Conversation::create([
            'type' => 'group',
            'name' => 'Komunitas Devata',
        ]);
        $group->participants()->create([
            'user_id' => $this->user1->id,
            'role' => 'admin',
        ]);

        $inviteRes = $this->withHeader('Authorization', 'Bearer ' . $this->token1)
            ->postJson("/api/groups/{$group->id}/invite-token");

        $inviteRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $token = $inviteRes->json('data.invite_token');

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        $joinRes = $this->withHeader('Authorization', 'Bearer ' . $this->token2)
            ->postJson("/api/groups/join-invite/{$token}");

        $joinRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $group->id,
            'user_id' => $this->user2->id,
        ]);
    }

    public function test_user_can_submit_report(): void
    {
        $res = $this->withHeader('Authorization', 'Bearer ' . $this->token1)
            ->postJson('/api/reports', [
                'reported_user_id' => $this->user2->id,
                'reason' => 'spam',
                'description' => 'Mengirim pesan berulang-ulang tanpa izin.',
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $this->user1->id,
            'reported_user_id' => $this->user2->id,
            'reason' => 'spam',
        ]);
    }

    public function test_user_e2ee_keys_registration_and_retrieval(): void
    {
        $storeRes = $this->withHeader('Authorization', 'Bearer ' . $this->token1)
            ->postJson('/api/e2ee/keys', [
                'identity_key' => 'curve25519_id_user1',
                'signed_prekey' => 'signed_prekey_1',
                'prekey_signature' => 'sig_user1_val',
                'one_time_prekeys' => ['opk_1', 'opk_2'],
            ]);

        $storeRes->assertStatus(201)
            ->assertJsonPath('success', true);

        $getRes = $this->withHeader('Authorization', 'Bearer ' . $this->token2)
            ->getJson("/api/e2ee/keys/{$this->user1->id}");

        $getRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.identity_key', 'curve25519_id_user1')
            ->assertJsonPath('data.signed_prekey', 'signed_prekey_1');
    }
}
