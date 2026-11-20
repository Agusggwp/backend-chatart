<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_own_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Budi Santoso',
            'username' => 'budi_s',
            'email' => 'budi@artdevata.id',
            'phone' => '+628111222333',
            'bio' => 'Available on ARTDEVATA Chat',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/profile');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.name', 'Budi Santoso')
            ->assertJsonPath('data.user.username', 'budi_s')
            ->assertJsonPath('data.user.email', 'budi@artdevata.id')
            ->assertJsonPath('data.user.phone', '+628111222333')
            ->assertJsonPath('data.user.bio', 'Available on ARTDEVATA Chat')
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.token');
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'username' => 'old_username',
            'bio' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/profile', [
            'name' => 'New Name',
            'username' => 'new_username',
            'bio' => 'Senior Flutter & Laravel Engineer',
            'phone' => '+628999888777',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.name', 'New Name')
            ->assertJsonPath('data.user.username', 'new_username')
            ->assertJsonPath('data.user.bio', 'Senior Flutter & Laravel Engineer')
            ->assertJsonPath('data.user.phone', '+628999888777');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'username' => 'new_username',
            'bio' => 'Senior Flutter & Laravel Engineer',
        ]);
    }

    public function test_profile_update_rejects_duplicate_username_and_email_changes(): void
    {
        User::factory()->create(['username' => 'existing_handle']);
        $user = User::factory()->create(['email' => 'fixed@artdevata.id']);

        Sanctum::actingAs($user);

        $this->putJson('/api/profile', [
            'username' => 'existing_handle',
        ])->assertUnprocessable()->assertJsonValidationErrors(['username']);

        $this->putJson('/api/profile', [
            'email' => 'hacked@artdevata.id',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'fixed@artdevata.id',
        ]);
    }

    public function test_user_can_upload_avatar_and_replace_previous_file(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $firstImage = UploadedFile::fake()->image('avatar1.jpg', 300, 300);

        $firstResponse = $this->postJson('/api/profile/avatar', [
            'avatar' => $firstImage,
        ]);

        $firstResponse->assertOk()
            ->assertJsonPath('success', true);

        $user->refresh();
        $firstPath = $user->avatar;
        $this->assertNotNull($firstPath);
        Storage::disk('public')->assertExists($firstPath);

        $secondImage = UploadedFile::fake()->image('avatar2.png', 300, 300);
        $this->postJson('/api/profile/avatar', [
            'avatar' => $secondImage,
        ])->assertOk();

        $user->refresh();
        $this->assertNotEquals($firstPath, $user->avatar);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($user->avatar);
    }

    public function test_avatar_upload_rejects_invalid_file_type(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $invalidFile = UploadedFile::fake()->create('malware.pdf', 100, 'application/pdf');

        $this->postJson('/api/profile/avatar', [
            'avatar' => $invalidFile,
        ])->assertUnprocessable()->assertJsonValidationErrors(['avatar']);
    }
}
