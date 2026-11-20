<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserDiscoveryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_other_users_without_sensitive_fields(): void
    {
        $me = User::factory()->create(['name' => 'Current User', 'username' => 'current_user']);
        $other = User::factory()->create([
            'name' => 'Siti Rahma',
            'username' => 'siti.rahma',
            'email' => 'siti.private@artdevata.id',
            'phone' => '+628123123123',
        ]);
        $other->presence()->create(['is_online' => true, 'last_seen_at' => now()]);

        Sanctum::actingAs($me);

        $response = $this->getJson('/api/users');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.users')
            ->assertJsonPath('data.users.0.id', $other->id)
            ->assertJsonPath('data.users.0.username', 'siti.rahma')
            ->assertJsonPath('data.users.0.is_online', true)
            ->assertJsonMissingPath('data.users.0.password')
            ->assertJsonMissingPath('data.users.0.email')
            ->assertJsonMissingPath('data.users.0.phone');
    }

    public function test_user_can_search_users_by_name_and_username(): void
    {
        $me = User::factory()->create();
        User::factory()->create(['name' => 'Dewi Lestari', 'username' => 'dewi_art']);
        User::factory()->create(['name' => 'Raka Pratama', 'username' => 'rakapratama']);

        Sanctum::actingAs($me);

        $this->getJson('/api/users?search=Dewi')
            ->assertOk()
            ->assertJsonCount(1, 'data.users')
            ->assertJsonPath('data.users.0.username', 'dewi_art');

        $this->getJson('/api/users?search=rakapratama')
            ->assertOk()
            ->assertJsonCount(1, 'data.users')
            ->assertJsonPath('data.users.0.name', 'Raka Pratama');
    }

    public function test_user_can_view_another_user_public_profile(): void
    {
        $me = User::factory()->create();
        $target = User::factory()->create([
            'name' => 'Andi Wijaya',
            'username' => 'andi_w',
            'bio' => 'Building ARTDEVATA Chat',
            'email' => 'andi.secret@artdevata.id',
        ]);
        $target->presence()->create(['is_online' => false, 'last_seen_at' => now()->subMinutes(5)]);

        Sanctum::actingAs($me);

        $this->getJson("/api/users/{$target->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.name', 'Andi Wijaya')
            ->assertJsonPath('data.user.username', 'andi_w')
            ->assertJsonPath('data.user.bio', 'Building ARTDEVATA Chat')
            ->assertJsonPath('data.user.is_online', false)
            ->assertJsonMissingPath('data.user.email')
            ->assertJsonMissingPath('data.user.password');
    }

    public function test_requesting_nonexistent_user_returns_404_json(): void
    {
        $me = User::factory()->create();
        Sanctum::actingAs($me);

        $this->getJson('/api/users/999999')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }
}
