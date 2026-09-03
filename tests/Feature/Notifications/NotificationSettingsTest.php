<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_is_on_for_a_new_user_and_the_user_payload_says_so(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.push_enabled', true);
    }

    public function test_a_user_can_turn_push_off_and_back_on(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/notification-settings', ['push_enabled' => false])
            ->assertOk()
            ->assertJsonPath('user.push_enabled', false);

        $this->assertFalse($user->fresh()->push_enabled);

        $this->actingAs($user->fresh(), 'sanctum')
            ->getJson('/api/user')
            ->assertJsonPath('user.push_enabled', false);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/notification-settings', ['push_enabled' => true])
            ->assertOk()
            ->assertJsonPath('user.push_enabled', true);

        $this->assertTrue($user->fresh()->push_enabled);
    }

    public function test_the_switch_must_be_a_boolean(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/notification-settings', ['push_enabled' => 'maybe'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['push_enabled']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/notification-settings', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['push_enabled']);
    }
}
