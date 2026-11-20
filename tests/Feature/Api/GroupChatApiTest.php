<?php

namespace Tests\Feature\Api;

use App\Events\GroupUpdated;
use App\Events\MessageSent;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GroupChatApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_group_with_members(): void
    {
        Event::fake([GroupUpdated::class]);

        $admin = User::factory()->create();
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/groups', [
            'name' => 'Tim ARTDEVATA',
            'description' => 'Koordinasi proyek harian',
            'member_ids' => [$member1->id, $member2->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.conversation.is_group', true)
            ->assertJsonPath('data.conversation.name', 'Tim ARTDEVATA')
            ->assertJsonPath('data.conversation.my_role', 'admin')
            ->assertJsonPath('data.conversation.participants_count', 3);

        Event::assertDispatched(GroupUpdated::class, function (GroupUpdated $e) use ($admin, $member1, $member2): bool {
            return $e->action === 'created'
                && in_array($admin->id, $e->participantIds, true)
                && in_array($member1->id, $e->participantIds, true)
                && in_array($member2->id, $e->participantIds, true);
        });
    }

    public function test_group_messages_track_per_member_unread_counts(): void
    {
        Event::fake([GroupUpdated::class, MessageSent::class]);

        $admin = User::factory()->create();
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        Sanctum::actingAs($admin);
        $groupId = $this->postJson('/api/groups', [
            'name' => 'Dev Group',
            'member_ids' => [$member1->id, $member2->id],
        ])->json('data.conversation.id');

        $this->postJson("/api/conversations/{$groupId}/messages", [
            'body' => 'Halo semua anggota grup!',
        ])->assertCreated();

        // Both member1 and member2 have unread_count = 1
        Sanctum::actingAs($member1);
        $this->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonPath('data.conversations.0.unread_count', 1);

        // member1 reads it
        $this->postJson("/api/conversations/{$groupId}/read")->assertOk();
        $this->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonPath('data.conversations.0.unread_count', 0);

        // member2 STILL has unread_count = 1!
        Sanctum::actingAs($member2);
        $this->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonPath('data.conversations.0.unread_count', 1);
    }

    public function test_only_admin_can_update_group_and_manage_members(): void
    {
        Event::fake([GroupUpdated::class]);
        Storage::fake('public');

        $admin = User::factory()->create();
        $member = User::factory()->create();
        $extra = User::factory()->create();

        Sanctum::actingAs($admin);
        $groupId = $this->postJson('/api/groups', [
            'name' => 'Grup Lama',
            'member_ids' => [$member->id],
        ])->json('data.conversation.id');

        // Non-admin attempts are rejected with 403
        Sanctum::actingAs($member);
        $this->putJson("/api/groups/{$groupId}", ['name' => 'Hack'])->assertForbidden();
        $this->postJson("/api/groups/{$groupId}/members", ['member_ids' => [$extra->id]])->assertForbidden();
        $this->deleteJson("/api/groups/{$groupId}/members/{$admin->id}")->assertForbidden();

        // Admin succeeds
        Sanctum::actingAs($admin);
        $this->putJson("/api/groups/{$groupId}", [
            'name' => 'Grup Baru',
            'description' => 'Deskripsi terbaru',
        ])->assertOk()
            ->assertJsonPath('data.conversation.name', 'Grup Baru')
            ->assertJsonPath('data.conversation.description', 'Deskripsi terbaru');

        $this->postJson("/api/groups/{$groupId}/avatar", [
            'avatar' => UploadedFile::fake()->image('group.png', 200, 200),
        ])->assertOk();

        $this->postJson("/api/groups/{$groupId}/members", [
            'member_ids' => [$extra->id],
        ])->assertOk()->assertJsonPath('data.conversation.participants_count', 3);

        $this->deleteJson("/api/groups/{$groupId}/members/{$extra->id}")
            ->assertOk()
            ->assertJsonPath('data.conversation.participants_count', 2);

        // Removed user cannot access messages anymore
        Sanctum::actingAs($extra);
        $this->getJson("/api/conversations/{$groupId}/messages")->assertForbidden();
    }

    public function test_group_message_read_at_set_only_after_all_members_read(): void
    {
        Event::fake([GroupUpdated::class, MessageSent::class, \App\Events\MessagesRead::class]);

        $admin = User::factory()->create();
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        Sanctum::actingAs($admin);
        $groupId = $this->postJson('/api/groups', [
            'name' => 'Receipt Group',
            'member_ids' => [$member1->id, $member2->id],
        ])->json('data.conversation.id');

        $messageId = $this->postJson("/api/conversations/{$groupId}/messages", ['body' => 'Cek centang'])
            ->json('data.message.id');

        $this->travel(1)->seconds();
        Sanctum::actingAs($member1);
        $this->postJson("/api/conversations/{$groupId}/read")->assertOk();
        $this->assertDatabaseHas('messages', ['id' => $messageId, 'read_at' => null]);

        Sanctum::actingAs($member2);
        $this->postJson("/api/conversations/{$groupId}/read")->assertOk();
        $this->assertNotNull(\App\Models\Message::find($messageId)->read_at);
    }

    public function test_leaving_group_promotes_next_member_when_last_admin_leaves(): void
    {
        Event::fake([GroupUpdated::class]);

        $admin = User::factory()->create();
        $member = User::factory()->create();

        Sanctum::actingAs($admin);
        $groupId = $this->postJson('/api/groups', [
            'name' => 'Handover Group',
            'member_ids' => [$member->id],
        ])->json('data.conversation.id');

        $this->postJson("/api/groups/{$groupId}/leave")->assertOk();

        $this->assertDatabaseMissing('conversation_participants', [
            'conversation_id' => $groupId,
            'user_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $groupId,
            'user_id' => $member->id,
            'role' => ConversationParticipant::ROLE_ADMIN,
        ]);
    }
}
