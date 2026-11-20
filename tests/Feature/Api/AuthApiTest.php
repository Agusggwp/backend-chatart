<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_successfully(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Agus Artdevata',
            'username' => 'agus.artdevata',
            'email' => 'agus@artdevata.id',
            'phone' => '+6281234567890',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
            'device_name' => 'pixel-emulator',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.name', 'Agus Artdevata')
            ->assertJsonPath('data.user.username', 'agus.artdevata')
            ->assertJsonPath('data.user.email', 'agus@artdevata.id')
            ->assertJsonPath('data.user.is_online', true)
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'username', 'email', 'phone', 'avatar_url', 'bio', 'is_online'],
                    'token',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'agus@artdevata.id',
            'username' => 'agus.artdevata',
        ]);

        $user = User::where('email', 'agus@artdevata.id')->firstOrFail();
        $this->assertTrue(Hash::check('SecurePass123', $user->password));
        $this->assertDatabaseHas('user_presences', [
            'user_id' => $user->id,
            'is_online' => true,
        ]);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@artdevata.id']);

        $response = $this->postJson('/api/register', [
            'name' => 'Another User',
            'username' => 'another_user',
            'email' => 'taken@artdevata.id',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_rejects_duplicate_username(): void
    {
        User::factory()->create(['username' => 'artdevata_admin']);

        $response = $this->postJson('/api/register', [
            'name' => 'Another User',
            'username' => 'artdevata_admin',
            'email' => 'unique@artdevata.id',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['username']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'member@artdevata.id',
            'password' => Hash::make('ValidPass123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'member@artdevata.id',
            'password' => 'ValidPass123',
            'device_name' => 'android-test',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.is_online', true)
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_login_rejects_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'member@artdevata.id',
            'password' => Hash::make('ValidPass123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'member@artdevata.id',
            'password' => 'WrongPass999',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_protected_endpoints_reject_unauthenticated_requests(): void
    {
        $this->getJson('/api/me')->assertUnauthorized()->assertJsonPath('success', false);
        $this->getJson('/api/profile')->assertUnauthorized()->assertJsonPath('success', false);
        $this->getJson('/api/users')->assertUnauthorized()->assertJsonPath('success', false);
    }

    public function test_logout_revokes_token_and_marks_user_offline(): void
    {
        $user = User::factory()->create([
            'email' => 'logout@artdevata.id',
            'password' => Hash::make('ValidPass123'),
        ]);

        $loginResponse = $this->postJson('/api/login', [
            'email' => 'logout@artdevata.id',
            'password' => 'ValidPass123',
        ]);

        $token = $loginResponse->json('data.token');
        $this->assertNotEmpty($token);

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('user_presences', [
            'user_id' => $user->id,
            'is_online' => false,
        ]);

        // Ensure revoked token is rejected on subsequent calls
        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized();
    }
}
