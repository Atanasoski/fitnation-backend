<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequiresSubscriptionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /** A cheap gated endpoint — list is empty on a fresh DB but returns 200. */
    private const GATED_ROUTE = '/api/muscle-groups';

    public function test_user_without_access_gets_403_with_machine_readable_code(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson(self::GATED_ROUTE)
            ->assertForbidden()
            ->assertJsonPath('code', 'subscription_required');
    }

    public function test_active_subscription_opens_the_gate(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson(self::GATED_ROUTE)
            ->assertOk();
    }

    public function test_grace_period_opens_the_gate(): void
    {
        $user = User::factory()->entitled()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson(self::GATED_ROUTE)
            ->assertOk();
    }

    public function test_lapsed_grace_period_does_not_open_the_gate(): void
    {
        $user = User::factory()->create(['grace_period_ends_at' => now()->subDay()]);

        $this->actingAs($user, 'sanctum')
            ->getJson(self::GATED_ROUTE)
            ->assertForbidden();
    }

    public function test_sponsoring_gym_opens_the_gate(): void
    {
        $partner = Partner::factory()->sponsor()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson(self::GATED_ROUTE)
            ->assertOk();
    }

    public function test_a_deactivated_sponsoring_gym_no_longer_opens_the_gate_until_reactivated(): void
    {
        $partner = Partner::factory()->sponsor()->inactive()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson(self::GATED_ROUTE)
            ->assertForbidden()
            ->assertJsonPath('code', 'subscription_required');

        $partner->update(['is_active' => true]);

        $this->actingAs($user->fresh(), 'sanctum')
            ->getJson(self::GATED_ROUTE)
            ->assertOk();
    }

    public function test_the_user_endpoint_reports_a_deactivated_sponsoring_gym_as_no_sponsorship(): void
    {
        $partner = Partner::factory()->sponsor()->inactive()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.entitlements', [])
            ->assertJsonPath('user.subscription.is_sponsored_by_gym', false)
            ->assertJsonPath('user.subscription.access_source', 'none');

        $partner->update(['is_active' => true]);

        $this->actingAs($user->fresh(), 'sanctum')
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.entitlements', ['app_access'])
            ->assertJsonPath('user.subscription.is_sponsored_by_gym', true)
            ->assertJsonPath('user.subscription.access_source', 'sponsored');
    }

    public function test_billing_issue_subscription_still_opens_the_gate(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->billingIssue()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson(self::GATED_ROUTE)
            ->assertOk();
    }

    public function test_expired_subscription_does_not_open_the_gate(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->expired()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson(self::GATED_ROUTE)
            ->assertForbidden();
    }

    public function test_user_endpoint_stays_reachable_without_access(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/user')
            ->assertOk();
    }

    public function test_logout_stays_reachable_without_access(): void
    {
        $user = User::factory()->create();

        // Logout deletes the current access token, so it needs a real one —
        // actingAs would fake the auth without one.
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertOk();
    }

    public function test_device_registration_stays_reachable_without_access(): void
    {
        $user = User::factory()->create();

        // A Device is the bearer-token session itself (ADR-0003), so register
        // with a real token rather than actingAs.
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/devices', [
                'push_token' => 'ExponentPushToken[paywalled-paywalled-xx]',
                'platform' => 'ios',
            ])
            ->assertOk();
    }

    public function test_profile_update_stays_reachable_without_access(): void
    {
        $user = User::factory()->create();

        // Onboarding saves the profile before the paywall is ever shown.
        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile', [
                'fitness_goal' => 'muscle_gain',
                'training_days_per_week' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('user.profile.training_days_per_week', 3);
    }

    public function test_notification_settings_stay_reachable_without_access(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/notification-settings', ['push_enabled' => false])
            ->assertOk();
    }

    public function test_enforcement_off_opens_the_gate_for_everyone(): void
    {
        config(['subscriptions.enforced' => false]);
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson(self::GATED_ROUTE)
            ->assertOk();
    }

    public function test_enforcement_off_reports_app_access_on_the_user_endpoint(): void
    {
        config(['subscriptions.enforced' => false]);
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.entitlements', ['app_access'])
            ->assertJsonPath('user.subscription.status', null);
    }

    public function test_unauthenticated_request_gets_401_not_403(): void
    {
        $this->getJson(self::GATED_ROUTE)->assertUnauthorized();
    }
}
