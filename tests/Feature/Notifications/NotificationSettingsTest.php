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

    public function test_the_weekly_summary_email_is_on_for_a_new_user_and_the_user_payload_says_so(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->fresh()->notification_settings);
        $this->assertTrue($user->notificationSetting('weekly_summary_email', true));

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.notification_settings.weekly_summary_email', true);
    }

    public function test_a_user_can_turn_the_weekly_summary_email_off_and_back_on(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/notification-settings', [
                'push_enabled' => true,
                'notification_settings' => ['weekly_summary_email' => false],
            ])
            ->assertOk()
            ->assertJsonPath('user.notification_settings.weekly_summary_email', false)
            ->assertJsonPath('user.push_enabled', true);

        $this->assertFalse($user->fresh()->notificationSetting('weekly_summary_email', true));

        $this->actingAs($user->fresh(), 'sanctum')
            ->getJson('/api/user')
            ->assertJsonPath('user.notification_settings.weekly_summary_email', false);

        $this->actingAs($user->fresh(), 'sanctum')
            ->patchJson('/api/notification-settings', [
                'push_enabled' => true,
                'notification_settings' => ['weekly_summary_email' => true],
            ])
            ->assertOk()
            ->assertJsonPath('user.notification_settings.weekly_summary_email', true);

        $this->assertTrue($user->fresh()->notificationSetting('weekly_summary_email', true));
    }

    public function test_flipping_the_push_switch_alone_leaves_the_email_preference_as_it_was(): void
    {
        $user = User::factory()->create(['notification_settings' => ['weekly_summary_email' => false]]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/notification-settings', ['push_enabled' => false])
            ->assertOk()
            ->assertJsonPath('user.notification_settings.weekly_summary_email', false);
    }

    public function test_the_weekly_summary_email_preference_must_be_a_boolean(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/notification-settings', [
                'push_enabled' => true,
                'notification_settings' => ['weekly_summary_email' => 'maybe'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['notification_settings.weekly_summary_email']);
    }
}
