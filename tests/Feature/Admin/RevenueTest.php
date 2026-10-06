<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AccessSource;
use App\Enums\SubscriptionPeriodType;
use App\Enums\SubscriptionStore;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Admin\AccessSources;
use App\Services\Admin\Revenue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Revenue::summary (spec 024, Seam 2): the Revenue tab's numbers from the
 * current state of production subscriptions.
 */
class RevenueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-07 12:00:00');
    }

    public function test_expected_monthly_revenue_counts_monthly_price_plus_yearly_price_over_twelve(): void
    {
        Subscription::factory()->create(['price' => 9.99]);
        Subscription::factory()->yearly()->create(['price' => 60.00]);

        $revenue = Revenue::summary();

        $this->assertSame(14.99, $revenue['expected_monthly_usd']);
        $this->assertSame(['total' => 2, 'monthly' => 1, 'yearly' => 1], $revenue['paying']);
    }

    public function test_a_trial_counts_as_a_trial_and_earns_nothing(): void
    {
        Subscription::factory()->trial()->create(['price' => 0]);
        Subscription::factory()->yearly()->trial()->create(['price' => 0]);

        $revenue = Revenue::summary();

        $this->assertSame(2, $revenue['trials']);
        $this->assertSame(0, $revenue['paying']['total']);
        $this->assertSame(0.0, $revenue['expected_monthly_usd']);
    }

    public function test_a_null_price_is_paying_but_earns_nothing_and_is_counted_as_unknown(): void
    {
        Subscription::factory()->create(['price' => 9.99]);
        Subscription::factory()->create(['price' => null]);

        $revenue = Revenue::summary();

        $this->assertSame(2, $revenue['paying']['total']);
        $this->assertSame(1, $revenue['unknown_price']);
        $this->assertSame(9.99, $revenue['expected_monthly_usd']);
    }

    public function test_sandbox_rows_are_excluded_and_counted(): void
    {
        Subscription::factory()->create(['price' => 9.99]);
        Subscription::factory()->sandbox()->create(['price' => 9.99]);
        Subscription::factory()->sandbox()->trial()->create();

        $revenue = Revenue::summary();

        $this->assertSame(2, $revenue['sandbox_excluded']);
        $this->assertSame(1, $revenue['paying']['total']);
        $this->assertSame(0, $revenue['trials']);
        $this->assertSame(9.99, $revenue['expected_monthly_usd']);
    }

    public function test_expired_is_not_paying_while_cancelled_and_billing_issue_still_are(): void
    {
        Subscription::factory()->expired()->create(['price' => 9.99]);
        Subscription::factory()->cancelled()->create(['price' => 9.99]);
        Subscription::factory()->billingIssue()->create(['price' => 9.99]);

        $revenue = Revenue::summary();

        $this->assertSame(2, $revenue['paying']['total']);
        $this->assertSame(19.98, $revenue['expected_monthly_usd']);
    }

    public function test_the_billing_issue_count_is_the_users_list_access_source_billing_issue(): void
    {
        Subscription::factory()->billingIssue()->count(2)->create();
        Subscription::factory()->billingIssue()->expired()->create();
        Subscription::factory()->create();

        $listed = AccessSources::constrain(User::query()->appUsers(), AccessSource::BillingIssue)->count();

        $this->assertSame(2, $listed);
        $this->assertSame($listed, Revenue::summary()['billing_issue']);
    }

    public function test_staff_subscriptions_never_count(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->id);
        Subscription::factory()->create(['user_id' => $admin->id, 'price' => 9.99]);
        Subscription::factory()->trial()->create(['user_id' => User::factory()->create()->id]);

        $revenue = Revenue::summary();

        $this->assertSame(0, $revenue['paying']['total']);
        $this->assertSame(1, $revenue['trials']);
        $this->assertSame(0.0, $revenue['expected_monthly_usd']);
    }

    public function test_revenue_splits_by_store_and_plan_reading_play_base_plan_ids(): void
    {
        Subscription::factory()->create(['price' => 9.99]);
        Subscription::factory()->yearly()->create(['price' => 120.00]);
        Subscription::factory()->create([
            'store' => SubscriptionStore::PlayStore,
            'product_id' => 'com.fitnation.app.premium.monthly:monthly',
            'price' => 8.00,
        ]);
        Subscription::factory()->count(2)->create([
            'store' => SubscriptionStore::PlayStore,
            'product_id' => 'com.fitnation.app.premium.yearly:yearly',
            'price' => 60.00,
        ]);

        $revenue = Revenue::summary();

        $this->assertSame([
            'app_store' => [
                'monthly' => ['subscribers' => 1, 'usd' => 9.99],
                'yearly' => ['subscribers' => 1, 'usd' => 10.0],
            ],
            'play_store' => [
                'monthly' => ['subscribers' => 1, 'usd' => 8.0],
                'yearly' => ['subscribers' => 2, 'usd' => 10.0],
            ],
        ], $revenue['by_store_and_plan']);
        $this->assertSame(['total' => 5, 'monthly' => 2, 'yearly' => 3], $revenue['paying']);
        $this->assertSame(37.99, $revenue['expected_monthly_usd']);
    }

    public function test_trial_to_paid_per_plan_leaves_users_still_in_trial_out(): void
    {
        // Monthly: two converted (one since expired), one lapsed trial, one still in trial.
        Subscription::factory()->create();
        Subscription::factory()->expired()->create();
        Subscription::factory()->trial()->expired()->create(['period_type' => SubscriptionPeriodType::Trial]);
        Subscription::factory()->trial()->create();
        // Yearly on Play: one converted, three lapsed trials (one cancelled during the trial).
        $play = ['store' => SubscriptionStore::PlayStore, 'product_id' => 'com.fitnation.app.premium.yearly:yearly'];
        Subscription::factory()->create($play);
        Subscription::factory()->trial()->expired()->count(2)->create([...$play, 'period_type' => SubscriptionPeriodType::Trial]);
        Subscription::factory()->trial()->cancelled()->create([...$play, 'expires_at' => now()->subDay()]);
        // Sandbox never counts.
        Subscription::factory()->sandbox()->trial()->expired()->create(['period_type' => SubscriptionPeriodType::Trial]);

        $this->assertSame([
            'monthly' => ['converted' => 2, 'lapsed_trial' => 1, 'still_in_trial' => 1, 'rate' => 67],
            'yearly' => ['converted' => 1, 'lapsed_trial' => 3, 'still_in_trial' => 0, 'rate' => 25],
        ], Revenue::summary()['conversion']);
    }

    public function test_a_plan_without_finished_trials_has_no_rate(): void
    {
        Subscription::factory()->trial()->create();

        $this->assertSame(
            ['converted' => 0, 'lapsed_trial' => 0, 'still_in_trial' => 1, 'rate' => null],
            Revenue::summary()['conversion']['monthly'],
        );
    }
}
