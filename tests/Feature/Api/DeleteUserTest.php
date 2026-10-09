<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_delete_their_account(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withToken($token)->deleteJson('/api/user', [
            'password' => 'correct-password',
        ]);

        $response->assertNoContent();

        $this->assertSoftDeleted('users', ['id' => $user->id]);

        $user->refresh();
        $this->assertSame('Deleted User', $user->name);
        $this->assertSame('deleted_'.$user->id.'@deleted.invalid', $user->email);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_delete_fails_with_wrong_password(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
        ]);

        $response = $this->actingAs($user, 'sanctum')->deleteJson('/api/user', [
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
    }

    /**
     * The mobile app confirms deletion with a typed word, not the password
     * (2026-10-02): the bearer token already says who is asking, and a social
     * account has no password to type. Sending none deletes the account.
     */
    public function test_delete_succeeds_without_password(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->deleteJson('/api/user', []);

        $response->assertNoContent();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_a_social_only_account_deletes_without_a_password(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user, 'sanctum')->deleteJson('/api/user', [])->assertNoContent();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_delete_requires_authentication(): void
    {
        $response = $this->deleteJson('/api/user', [
            'password' => 'any-password',
        ]);

        $response->assertUnauthorized();
    }
}
