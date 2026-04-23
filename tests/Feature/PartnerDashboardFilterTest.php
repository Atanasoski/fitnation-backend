<?php

namespace Tests\Feature;

use App\Enums\BillingCycle;
use App\Models\Partner;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerDashboardFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_member_role_cannot_access_dashboard(): void
    {
        $partner = Partner::factory()->create();
        $member = User::factory()->create(['partner_id' => $partner->id]);
        $member->roles()->attach(Role::where('slug', 'user')->first());

        $this->actingAs($member)
            ->get(route('dashboard', ['start_date' => '2020-01-01']))
            ->assertForbidden();
    }

    public function test_date_range_filters_by_subscription_created_at(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-15 12:00:00', 'UTC'));

        $partner = Partner::factory()->create();
        $admin = $this->makePartnerAdmin($partner);
        $memberIn = User::factory()->create(['partner_id' => $partner->id, 'name' => 'In Range Member']);
        $memberOut = User::factory()->create(['partner_id' => $partner->id, 'name' => 'Out Range Member']);
        $plan = $this->makePlan($partner);

        $inRange = Subscription::query()->create([
            'user_id' => $memberIn->id,
            'subscription_plan_id' => $plan->id,
            'starts_at' => Carbon::parse('2025-06-01'),
            'ends_at' => Carbon::parse('2026-06-01'),
            'cancelled_at' => null,
        ]);
        $inRange->forceFill([
            'created_at' => Carbon::parse('2025-06-10 10:00:00'),
            'updated_at' => Carbon::parse('2025-06-10 10:00:00'),
        ])->saveQuietly();

        $outOfRange = Subscription::query()->create([
            'user_id' => $memberOut->id,
            'subscription_plan_id' => $plan->id,
            'starts_at' => Carbon::parse('2025-06-01'),
            'ends_at' => Carbon::parse('2026-06-01'),
            'cancelled_at' => null,
        ]);
        $outOfRange->forceFill([
            'created_at' => Carbon::parse('2025-01-05 10:00:00'),
            'updated_at' => Carbon::parse('2025-01-05 10:00:00'),
        ])->saveQuietly();

        $response = $this->actingAs($admin)->get(route('dashboard', [
            'start_date' => '2025-06-01',
            'end_date' => '2025-06-30',
        ]));

        $response->assertOk();
        $response->assertSee('In Range Member', escape: false);
        $response->assertDontSee('Out Range Member', escape: false);

        Carbon::setTestNow();
    }

    public function test_plan_filter_limits_results(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-15 12:00:00', 'UTC'));

        $partner = Partner::factory()->create();
        $admin = $this->makePartnerAdmin($partner);
        $member = User::factory()->create(['partner_id' => $partner->id, 'name' => 'Plan Filter Member']);
        $planA = $this->makePlan($partner, 'Plan A');
        $planB = $this->makePlan($partner, 'Plan B');

        $subA = Subscription::query()->create([
            'user_id' => $member->id,
            'subscription_plan_id' => $planA->id,
            'starts_at' => Carbon::parse('2025-06-01'),
            'ends_at' => Carbon::parse('2026-06-01'),
            'cancelled_at' => null,
        ]);
        $subA->forceFill([
            'created_at' => Carbon::parse('2025-06-10'),
            'updated_at' => Carbon::parse('2025-06-10'),
        ])->saveQuietly();

        $subB = Subscription::query()->create([
            'user_id' => $member->id,
            'subscription_plan_id' => $planB->id,
            'starts_at' => Carbon::parse('2025-06-01'),
            'ends_at' => Carbon::parse('2026-06-01'),
            'cancelled_at' => null,
        ]);
        $subB->forceFill([
            'created_at' => Carbon::parse('2025-06-11'),
            'updated_at' => Carbon::parse('2025-06-11'),
        ])->saveQuietly();

        $response = $this->actingAs($admin)->get(route('dashboard', [
            'start_date' => '2025-06-01',
            'end_date' => '2025-06-30',
            'plan_id' => $planA->id,
        ]));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertSame(1, preg_match('/<tbody[^>]*>(.*?)<\/tbody>/s', $html, $matches));
        $tbody = $matches[1];
        $this->assertStringContainsString('Plan A', $tbody);
        $this->assertStringNotContainsString('Plan B', $tbody);

        Carbon::setTestNow();
    }

    public function test_expiring_filter_shows_active_subscriptions_ending_within_seven_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-15 12:00:00', 'UTC'));

        $partner = Partner::factory()->create();
        $admin = $this->makePartnerAdmin($partner);
        $memberExpiring = User::factory()->create(['partner_id' => $partner->id, 'name' => 'Expiring Member']);
        $memberLater = User::factory()->create(['partner_id' => $partner->id, 'name' => 'Later End Member']);
        $plan = $this->makePlan($partner);

        $expiring = Subscription::query()->create([
            'user_id' => $memberExpiring->id,
            'subscription_plan_id' => $plan->id,
            'starts_at' => Carbon::parse('2025-01-01'),
            'ends_at' => Carbon::parse('2025-06-18'),
            'cancelled_at' => null,
        ]);
        $expiring->forceFill([
            'created_at' => Carbon::parse('2025-01-01'),
            'updated_at' => Carbon::parse('2025-06-14'),
        ])->saveQuietly();

        $notExpiringWindow = Subscription::query()->create([
            'user_id' => $memberLater->id,
            'subscription_plan_id' => $plan->id,
            'starts_at' => Carbon::parse('2025-01-01'),
            'ends_at' => Carbon::parse('2025-08-01'),
            'cancelled_at' => null,
        ]);
        $notExpiringWindow->forceFill([
            'created_at' => Carbon::parse('2025-06-01'),
            'updated_at' => Carbon::parse('2025-06-01'),
        ])->saveQuietly();

        $response = $this->actingAs($admin)->get(route('dashboard', ['expiring' => 1]));

        $response->assertOk();
        $response->assertSee('Filtered: expiring within 7 days', escape: false);
        $response->assertSee('Expiring Member', escape: false);
        $response->assertDontSee('Later End Member', escape: false);

        Carbon::setTestNow();
    }

    public function test_invalid_start_date_returns_ok_and_warning(): void
    {
        $partner = Partner::factory()->create();
        $admin = $this->makePartnerAdmin($partner);

        $response = $this->actingAs($admin)->get(route('dashboard', [
            'start_date' => 'not-a-date',
            'end_date' => '2025-06-30',
        ]));

        $response->assertOk();
        $response->assertSee('Invalid filters were reset', escape: false);
    }

    public function test_start_after_end_returns_ok_and_warning(): void
    {
        $partner = Partner::factory()->create();
        $admin = $this->makePartnerAdmin($partner);

        $response = $this->actingAs($admin)->get(route('dashboard', [
            'start_date' => '2025-06-30',
            'end_date' => '2025-06-01',
        ]));

        $response->assertOk();
        $response->assertSee('Invalid date range was reset', escape: false);
    }

    public function test_foreign_plan_id_is_rejected_with_warning(): void
    {
        $partnerA = Partner::factory()->create();
        $partnerB = Partner::factory()->create();
        $admin = $this->makePartnerAdmin($partnerA);
        $planB = $this->makePlan($partnerB, 'Other gym plan');

        $response = $this->actingAs($admin)->get(route('dashboard', [
            'plan_id' => $planB->id,
        ]));

        $response->assertOk();
        $response->assertSee('That subscription plan is not available', escape: false);
    }

    public function test_pagination_preserves_query_string(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-15 12:00:00', 'UTC'));

        $partner = Partner::factory()->create();
        $admin = $this->makePartnerAdmin($partner);
        $member = User::factory()->create(['partner_id' => $partner->id]);
        $plan = $this->makePlan($partner);

        for ($i = 0; $i < 11; $i++) {
            $sub = Subscription::query()->create([
                'user_id' => $member->id,
                'subscription_plan_id' => $plan->id,
                'starts_at' => Carbon::parse('2025-06-01'),
                'ends_at' => Carbon::parse('2026-06-01'),
                'cancelled_at' => null,
            ]);
            $sub->forceFill([
                'created_at' => Carbon::parse('2025-06-10')->addMinutes($i),
                'updated_at' => Carbon::parse('2025-06-10')->addMinutes($i),
            ])->saveQuietly();
        }

        $page1 = $this->actingAs($admin)->get(route('dashboard', [
            'start_date' => '2025-06-01',
            'end_date' => '2025-06-30',
            'plan_id' => $plan->id,
        ]));

        $page1->assertOk();
        $page1->assertSee('page=2', escape: false);
        $page1->assertSee('start_date=2025-06-01', escape: false);
        $page1->assertSee('plan_id='.$plan->id, escape: false);

        Carbon::setTestNow();
    }

    private function makePartnerAdmin(Partner $partner): User
    {
        $admin = User::factory()->create(['partner_id' => $partner->id]);
        $admin->roles()->attach(Role::where('slug', 'partner_admin')->first());

        return $admin;
    }

    private function makePlan(Partner $partner, string $name = 'Test plan'): SubscriptionPlan
    {
        return SubscriptionPlan::query()->create([
            'partner_id' => $partner->id,
            'name' => $name,
            'description' => null,
            'price' => 49.99,
            'billing_cycle' => BillingCycle::Monthly,
            'is_active' => true,
        ]);
    }
}
