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

    public function test_unauthenticated_request_gets_401_not_403(): void
    {
        $this->getJson(self::GATED_ROUTE)->assertUnauthorized();
    }
}
