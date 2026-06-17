<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SpaAuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_seeded_user_can_log_in(): void
    {
        $this->ensureSeededUser();

        $response = $this->postJson('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.email', 'test@example.com');

        $this->assertAuthenticated();
    }

    public function test_invalid_credentials_return_validation_errors(): void
    {
        $this->ensureSeededUser();

        $response = $this->postJson('/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertStatus(422)
            ->assertInvalid(['email']);

        $this->assertGuest();
    }

    public function test_current_user_route_requires_authentication(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_access_current_user_route(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/user');

        $response
            ->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_logout_invalidates_session_and_protects_api_routes(): void
    {
        $this->ensureSeededUser();

        $this->postJson('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->getJson('/api/user')->assertOk();

        $this->postJson('/logout')->assertNoContent();

        $this->refreshApplication();

        $this->getJson('/api/user')->assertUnauthorized();
    }

    private function ensureSeededUser(): User
    {
        return User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
            ],
        );
    }
}
