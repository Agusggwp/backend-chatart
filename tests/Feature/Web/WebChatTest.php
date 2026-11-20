<?php

namespace Tests\Feature\Web;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/login');
    }

    public function test_login_page_renders_successfully(): void
    {
        User::factory()->create([
            'name' => 'Agus Artdevata',
            'username' => 'agus',
            'email' => 'agus@artdevata.id',
        ]);

        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('ARTDEVATA WEB');
        $response->assertSee('Agus Artdevata');
    }

    public function test_quick_login_authenticates_user_and_redirects_to_chat(): void
    {
        $user = User::factory()->create([
            'name' => 'Agus Artdevata',
            'username' => 'agus',
            'email' => 'agus@artdevata.id',
        ]);

        $response = $this->get(route('web.quick-login', $user));
        $response->assertRedirect(route('web.chat'));
        $this->assertAuthenticatedAs($user, 'web');

        $chatResponse = $this->get('/');
        $chatResponse->assertStatus(200);
        $chatResponse->assertSee('Agus Artdevata');
    }

    public function test_authenticated_user_can_view_chat_dashboard(): void
    {
        $user1 = User::factory()->create(['name' => 'User One']);
        $user2 = User::factory()->create(['name' => 'User Two']);

        $conversation = Conversation::create(['type' => 'private']);
        $conversation->users()->attach([
            $user1->id => ['role' => 'member'],
            $user2->id => ['role' => 'member'],
        ]);

        $response = $this->actingAs($user1, 'web')->get('/');
        $response->assertStatus(200);
        $response->assertSee('User Two');
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')->post(route('web.logout'));
        $response->assertRedirect(route('login'));
        $this->assertGuest('web');
    }
}
