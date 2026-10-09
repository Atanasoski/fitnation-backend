<?php

namespace Tests\Unit;

use App\Enums\Entitlement;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_subscription_with_time_left_is_active(): void
    {
        $subscription = Subscription::factory()->create();

        $this->assertTrue($subscription->isActive());
        $this->assertFalse($subscription->isInGracePeriod());
    }

    public function test_cancelled_with_time_left_is_in_grace_period_and_active(): void
    {
        $subscription = Subscription::factory()->cancelled()->create();

        $this->assertTrue($subscription->isInGracePeriod());
        $this->assertTrue($subscription->isActive());
    }

    public function test_cancelled_past_expiry_is_neither_active_nor_in_grace(): void
    {
        $subscription = Subscription::factory()->cancelled()->create([
            'expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($subscription->isInGracePeriod());
        $this->assertFalse($subscription->isActive());
    }

    public function test_expired_subscription_is_not_active(): void
    {
        $subscription = Subscription::factory()->expired()->create();

        $this->assertFalse($subscription->isActive());
    }

    public function test_billing_issue_keeps_access_until_expiry(): void
    {
        $subscription = Subscription::factory()->billingIssue()->create();

        $this->assertTrue($subscription->isActive());
    }

    public function test_billing_issue_past_expiry_is_not_active(): void
    {
        $subscription = Subscription::factory()->billingIssue()->create([
            'expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($subscription->isActive());
    }

    public function test_paused_keeps_access_until_expiry(): void
    {
        $subscription = Subscription::factory()->paused()->create();

        $this->assertTrue($subscription->isActive());
    }

    public function test_active_status_without_expiry_date_is_not_active(): void
    {
        $subscription = Subscription::factory()->create(['expires_at' => null]);

        $this->assertFalse($subscription->isActive());
    }

    public function test_trial_subscription_is_in_trial(): void
    {
        $subscription = Subscription::factory()->trial()->create();

        $this->assertTrue($subscription->isInTrial());
    }

    public function test_normal_period_subscription_is_not_in_trial(): void
    {
        $subscription = Subscription::factory()->create();

        $this->assertFalse($subscription->isInTrial());
    }

    public function test_monthly_product_grants_app_access(): void
    {
        $subscription = Subscription::factory()->create();

        $this->assertTrue($subscription->grantedEntitlements()->contains(Entitlement::AppAccess));
    }

    public function test_yearly_product_grants_app_access(): void
    {
        $subscription = Subscription::factory()->yearly()->create();

        $this->assertTrue($subscription->grantedEntitlements()->contains(Entitlement::AppAccess));
    }

    public function test_play_products_with_their_base_plan_grant_app_access(): void
    {
        // RevenueCat reports a Google Play subscription as `<product>:<base plan>`.
        foreach (['com.fitnation.app.premium.monthly:monthly', 'com.fitnation.app.premium.yearly:yearly'] as $productId) {
            $subscription = Subscription::factory()->make(['product_id' => $productId]);

            $this->assertTrue($subscription->grantedEntitlements()->contains(Entitlement::AppAccess), $productId);
        }
    }

    public function test_unknown_product_grants_nothing(): void
    {
        $subscription = Subscription::factory()->create([
            'product_id' => 'com.fitnation.app.unknown',
        ]);

        $this->assertTrue($subscription->grantedEntitlements()->isEmpty());
    }

    public function test_active_scope_includes_all_access_granting_statuses(): void
    {
        Subscription::factory()->create();
        Subscription::factory()->cancelled()->create();
        Subscription::factory()->billingIssue()->create();
        Subscription::factory()->paused()->create();
        Subscription::factory()->expired()->create();
        Subscription::factory()->create(['expires_at' => now()->subDay()]);

        $this->assertSame(4, Subscription::active()->count());
    }
}
