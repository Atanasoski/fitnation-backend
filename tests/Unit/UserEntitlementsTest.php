<?php

namespace Tests\Unit;

use App\Enums\Entitlement;
use App\Models\Partner;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserEntitlementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_user_has_no_entitlements(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->entitlements()->isEmpty());
        $this->assertFalse($user->hasAppAccess());
    }

    public function test_future_grace_period_grants_app_access(): void
    {
        $user = User::factory()->create(['grace_period_ends_at' => now()->addDays(10)]);

        $this->assertTrue($user->entitlements()->contains(Entitlement::AppAccess));
        $this->assertTrue($user->hasAppAccess());
    }

    public function test_lapsed_grace_period_grants_nothing(): void
    {
        $user = User::factory()->create(['grace_period_ends_at' => now()->subDay()]);

        $this->assertFalse($user->hasAppAccess());
    }

    public function test_sponsoring_partner_grants_app_access(): void
    {
        $partner = Partner::factory()->sponsor()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);

        $this->assertTrue($user->hasAppAccess());
    }

    public function test_sponsoring_partner_with_future_expiry_grants_app_access(): void
    {
        $partner = Partner::factory()->sponsor()->create(['plan_expires_at' => now()->addMonth()]);
        $user = User::factory()->create(['partner_id' => $partner->id]);

        $this->assertTrue($user->hasAppAccess());
    }

    public function test_expired_sponsorship_grants_nothing(): void
    {
        $partner = Partner::factory()->sponsorExpired()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);

        $this->assertFalse($user->hasAppAccess());
    }

    public function test_free_partner_grants_nothing(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);

        $this->assertFalse($user->hasAppAccess());
    }

    public function test_active_subscription_grants_app_access(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->fresh()->hasAppAccess());
    }

    public function test_expired_subscription_grants_nothing(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->expired()->create(['user_id' => $user->id]);

        $this->assertFalse($user->fresh()->hasAppAccess());
    }

    public function test_billing_issue_subscription_still_grants_app_access(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->billingIssue()->create(['user_id' => $user->id]);

        $this->assertTrue($user->fresh()->hasAppAccess());
    }

    public function test_entitlements_deduplicate_across_sources(): void
    {
        $partner = Partner::factory()->sponsor()->create();
        $user = User::factory()->create([
            'partner_id' => $partner->id,
            'grace_period_ends_at' => now()->addDays(10),
        ]);
        Subscription::factory()->create(['user_id' => $user->id]);

        $entitlements = $user->fresh()->entitlements();

        $this->assertCount(1, $entitlements);
        $this->assertTrue($entitlements->contains(Entitlement::AppAccess));
    }
}
