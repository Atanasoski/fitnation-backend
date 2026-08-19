<?php

namespace Tests\Feature\Api;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $partner = Partner::factory()->create();

        $response = $this->postJson('/api/register', [
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'partner_id' => $partner->id,
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'partner'],
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'partner_id' => $partner->id,
        ]);
    }

    public function test_registration_requires_email_password_and_partner(): void
    {
        $response = $this->postJson('/api/register', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password', 'partner_id']);
    }

    public function test_registration_fails_with_unknown_partner(): void
    {
        $response = $this->postJson('/api/register', [
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'partner_id' => 999999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['partner_id']);
    }

    public function test_registration_fails_with_inactive_partner(): void
    {
        $partner = Partner::factory()->inactive()->create();

        $response = $this->postJson('/api/register', [
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'partner_id' => $partner->id,
        ]);

        $response->assertUnprocessable()
            ->assertJson([
                'message' => 'The selected partner is not currently active.',
            ]);

        $this->assertDatabaseMissing('users', ['email' => 'newuser@example.com']);
    }

    public function test_registration_rejects_a_duplicate_email(): void
    {
        $partner = Partner::factory()->create();
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/register', [
            'email' => 'taken@example.com',
            'password' => 'password123',
            'partner_id' => $partner->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email'],
                'token',
            ]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout');

        $response->assertOk()
            ->assertJson([
                'message' => 'Logged out successfully',
            ]);

        // Verify token was deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }
}
