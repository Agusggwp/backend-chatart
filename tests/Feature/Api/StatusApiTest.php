<?php

namespace Tests\Feature\Api;

use App\Events\StatusCreated;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StatusApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_text_status_with_background_color_and_font(): void
    {
        Event::fake([StatusCreated::class]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/statuses', [
                'type' => 'text',
                'content' => 'Hari yang menyenangkan bersama ARTDEVATA!',
                'background_color' => '#128C7E',
                'font_style' => 'serif',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'text')
            ->assertJsonPath('data.content', 'Hari yang menyenangkan bersama ARTDEVATA!')
            ->assertJsonPath('data.background_color', '#128C7E')
            ->assertJsonPath('data.font_style', 'serif')
            ->assertJsonPath('data.is_mine', true);

        $this->assertDatabaseHas('statuses', [
            'user_id' => $user->id,
            'type' => 'text',
            'content' => 'Hari yang menyenangkan bersama ARTDEVATA!',
        ]);

        Event::assertDispatched(StatusCreated::class);
    }

    public function test_user_can_create_image_status_with_media_file(): void
    {
        Storage::fake('public');
        Event::fake([StatusCreated::class]);

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('holiday.jpg');

        $response = $this->actingAs($user)
            ->post('/api/statuses', [
                'content' => 'Liburan akhir pekan',
                'media' => $file,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'image')
            ->assertJsonPath('data.content', 'Liburan akhir pekan')
            ->assertJsonPath('data.is_mine', true);

        $this->assertNotNull($response->json('data.media_url'));

        $this->assertDatabaseHas('statuses', [
            'user_id' => $user->id,
            'type' => 'image',
            'content' => 'Liburan akhir pekan',
        ]);
    }

    public function test_active_statuses_are_grouped_into_my_statuses_and_recent_updates(): void
    {
        $userA = User::factory()->create(['name' => 'Agus']);
        $userB = User::factory()->create(['name' => 'Budi']);

        // User A's status
        Status::create([
            'user_id' => $userA->id,
            'type' => 'text',
            'content' => 'Status Agus',
            'expires_at' => now()->addHours(24),
        ]);

        // User B's status
        Status::create([
            'user_id' => $userB->id,
            'type' => 'text',
            'content' => 'Status Budi',
            'expires_at' => now()->addHours(24),
        ]);

        $response = $this->actingAs($userA)->getJson('/api/statuses');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.my_statuses')
            ->assertJsonPath('data.my_statuses.0.content', 'Status Agus')
            ->assertJsonCount(1, 'data.recent_updates')
            ->assertJsonPath('data.recent_updates.0.user.name', 'Budi')
            ->assertJsonPath('data.recent_updates.0.has_unviewed', true)
            ->assertJsonCount(0, 'data.viewed_updates');
    }

    public function test_expired_statuses_older_than_24_hours_are_excluded(): void
    {
        $user = User::factory()->create();

        // Expired status
        Status::create([
            'user_id' => $user->id,
            'type' => 'text',
            'content' => 'Status kedaluwarsa',
            'expires_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/statuses');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data.my_statuses');
    }

    public function test_user_can_mark_status_as_viewed(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $statusB = Status::create([
            'user_id' => $userB->id,
            'type' => 'text',
            'content' => 'Hai dunia!',
            'expires_at' => now()->addHours(24),
        ]);

        // Mark as viewed by User A
        $markResponse = $this->actingAs($userA)->postJson("/api/statuses/{$statusB->id}/view");
        $markResponse->assertStatus(200);

        $this->assertDatabaseHas('status_views', [
            'status_id' => $statusB->id,
            'viewer_id' => $userA->id,
        ]);

        // When User A fetches statuses, User B's status is in viewed_updates
        $listResponse = $this->actingAs($userA)->getJson('/api/statuses');
        $listResponse->assertStatus(200)
            ->assertJsonCount(0, 'data.recent_updates')
            ->assertJsonCount(1, 'data.viewed_updates')
            ->assertJsonPath('data.viewed_updates.0.has_unviewed', false);
    }

    public function test_author_can_view_status_viewers_and_non_author_is_forbidden(): void
    {
        $author = User::factory()->create(['name' => 'Author User']);
        $viewer = User::factory()->create(['name' => 'Viewer User']);
        $stranger = User::factory()->create(['name' => 'Stranger']);

        $status = Status::create([
            'user_id' => $author->id,
            'type' => 'text',
            'content' => 'Status dengan penonton',
            'expires_at' => now()->addHours(24),
        ]);

        // Viewer views the status
        $this->actingAs($viewer)->postJson("/api/statuses/{$status->id}/view");

        // Author views list of viewers
        $response = $this->actingAs($author)->getJson("/api/statuses/{$status->id}/viewers");
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.viewer.name', 'Viewer User');

        // Stranger tries to view list of viewers -> 403 Forbidden
        $forbiddenResponse = $this->actingAs($stranger)->getJson("/api/statuses/{$status->id}/viewers");
        $forbiddenResponse->assertStatus(403);
    }

    public function test_author_can_delete_status_and_non_author_is_forbidden(): void
    {
        $author = User::factory()->create();
        $stranger = User::factory()->create();

        $status = Status::create([
            'user_id' => $author->id,
            'type' => 'text',
            'content' => 'Akan segera dihapus',
            'expires_at' => now()->addHours(24),
        ]);

        // Stranger tries to delete -> 403
        $this->actingAs($stranger)
            ->deleteJson("/api/statuses/{$status->id}")
            ->assertStatus(403);

        // Author deletes -> 200
        $this->actingAs($author)
            ->deleteJson("/api/statuses/{$status->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('statuses', [
            'id' => $status->id,
        ]);
    }
}
