<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\GymLocation;
use App\Models\JoinPromptDismissal;
use App\Models\Partner;
use App\Models\PartnerIdentity;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The nearby endpoint behind the Join Prompt (issue #88): an eligible House
 * Partner customer sends their coordinates and gets back the active,
 * geo-enabled Partners whose Gym Location covers the point, nearest first.
 * Everyone the feature must not reach gets an empty list, decided server-side
 * (GLOSSARY: Join Prompt, Gym Location, House Partner).
 *
 * Reference point: 41.9981, 21.4254. At this latitude one degree of latitude
 * is ~111,000 m, so 0.001° ≈ 111 m.
 */
class NearbyPartnersTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = 41.9981;

    private const LNG = 21.4254;

    public function test_an_eligible_customer_inside_the_radius_gets_the_partner_with_branding_and_benefits(): void
    {
        $partner = $this->joinablePartner(['name' => 'Iron Temple', 'benefits' => ['Free towel service', 'Sauna access']]);
        GymLocation::factory()->for($partner)->create([
            'latitude' => self::LAT,
            'longitude' => self::LNG,
            'radius_m' => 150,
        ]);

        $response = $this->actingAs($this->eligibleCustomer(), 'sanctum')
            // ~55 m north of the gym's point, well inside the 150 m radius.
            ->getJson($this->url(self::LAT + 0.0005, self::LNG));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $partner->id)
            ->assertJsonPath('data.0.name', 'Iron Temple')
            ->assertJsonPath('data.0.benefits', ['Free towel service', 'Sauna access'])
            // Joinable Partners are never the House Partner; the flag is
            // carried anyway so every partner payload carries is_house (#95).
            ->assertJsonPath('data.0.is_house', false);

        $this->assertIsArray($response->json('data.0.visual_identity'));
        $this->assertArrayHasKey('primary_color', $response->json('data.0.visual_identity'));
    }

    public function test_a_point_outside_the_radius_returns_an_empty_list(): void
    {
        $partner = $this->joinablePartner();
        GymLocation::factory()->for($partner)->create([
            'latitude' => self::LAT,
            'longitude' => self::LNG,
            'radius_m' => 150,
        ]);

        $this->actingAs($this->eligibleCustomer(), 'sanctum')
            // ~333 m north: a street away, outside the 150 m radius.
            ->getJson($this->url(self::LAT + 0.003, self::LNG))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_an_inactive_partner_never_matches(): void
    {
        $partner = $this->joinablePartner(['is_active' => false]);
        GymLocation::factory()->for($partner)->create([
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ]);

        $this->actingAs($this->eligibleCustomer(), 'sanctum')
            ->getJson($this->url(self::LAT, self::LNG))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_a_partner_without_the_join_prompt_enabled_never_matches(): void
    {
        $partner = $this->joinablePartner(['geo_prompt_enabled' => false]);
        GymLocation::factory()->for($partner)->create([
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ]);

        $this->actingAs($this->eligibleCustomer(), 'sanctum')
            ->getJson($this->url(self::LAT, self::LNG))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_a_customer_already_on_a_gym_gets_an_empty_list(): void
    {
        $partner = $this->joinablePartner();
        GymLocation::factory()->for($partner)->create([
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ]);

        $house = Partner::factory()->create(['name' => 'Fit Nation']);
        config(['partners.house_partner_id' => $house->id]);
        $joined = User::factory()->create(['partner_id' => $partner->id]);

        $this->actingAs($joined, 'sanctum')
            ->getJson($this->url(self::LAT, self::LNG))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_an_active_subscription_suppresses_the_prompt_whatever_the_dark_deploy_flag_says(): void
    {
        $partner = $this->joinablePartner();
        GymLocation::factory()->for($partner)->create([
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ]);

        $subscriber = $this->eligibleCustomer();
        Subscription::factory()->for($subscriber)->create();

        foreach ([true, false] as $enforced) {
            config(['subscriptions.enforced' => $enforced]);

            $this->actingAs($subscriber, 'sanctum')
                ->getJson($this->url(self::LAT, self::LNG))
                ->assertOk()
                ->assertJsonCount(0, 'data');
        }
    }

    public function test_cancelled_in_period_and_billing_issue_subscriptions_count_as_active(): void
    {
        $partner = $this->joinablePartner();
        GymLocation::factory()->for($partner)->create([
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ]);

        foreach (['cancelled', 'billingIssue'] as $state) {
            $customer = $this->eligibleCustomer();
            Subscription::factory()->{$state}()->for($customer)->create();

            $this->actingAs($customer, 'sanctum')
                ->getJson($this->url(self::LAT, self::LNG))
                ->assertOk()
                ->assertJsonCount(0, 'data');
        }
    }

    public function test_an_expired_subscription_leaves_the_customer_eligible(): void
    {
        $partner = $this->joinablePartner();
        GymLocation::factory()->for($partner)->create([
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ]);

        $lapsed = $this->eligibleCustomer();
        Subscription::factory()->expired()->for($lapsed)->create();

        $this->actingAs($lapsed, 'sanctum')
            ->getJson($this->url(self::LAT, self::LNG))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $partner->id);
    }

    public function test_an_unsubscribed_customer_stays_eligible_whatever_the_dark_deploy_flag_says(): void
    {
        $partner = $this->joinablePartner();
        GymLocation::factory()->for($partner)->create([
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ]);

        $customer = $this->eligibleCustomer();

        // With enforcement off the dark-deploy flag grants everyone every
        // entitlement; eligibility must come from the raw subscription row,
        // so the flag changes nothing in either direction.
        foreach ([true, false] as $enforced) {
            config(['subscriptions.enforced' => $enforced]);

            $this->actingAs($customer, 'sanctum')
                ->getJson($this->url(self::LAT, self::LNG))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $partner->id);
        }
    }

    public function test_a_customer_without_a_partner_gets_an_empty_list(): void
    {
        $partner = $this->joinablePartner();
        GymLocation::factory()->for($partner)->create([
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ]);

        $house = Partner::factory()->create(['name' => 'Fit Nation']);
        config(['partners.house_partner_id' => $house->id]);
        $stray = User::factory()->create(['partner_id' => null]);

        $this->actingAs($stray, 'sanctum')
            ->getJson($this->url(self::LAT, self::LNG))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_staff_get_an_empty_list(): void
    {
        $partner = $this->joinablePartner();
        GymLocation::factory()->for($partner)->create([
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ]);

        foreach (User::STAFF_ROLES as $slug) {
            $staff = $this->eligibleCustomer();
            $staff->roles()->attach(Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)])->id);

            $this->actingAs($staff, 'sanctum')
                ->getJson($this->url(self::LAT, self::LNG))
                ->assertOk()
                ->assertJsonCount(0, 'data');
        }
    }

    public function test_matching_partners_are_ordered_nearest_first_and_each_appears_once(): void
    {
        // The caller stands between two gyms; both cover the point. The
        // farther gym also has a second, closer Gym Location — the partner
        // must still appear once, ranked by its nearest location.
        $nearPartner = $this->joinablePartner(['name' => 'Near Gym']);
        GymLocation::factory()->for($nearPartner)->create([
            'latitude' => self::LAT + 0.0004, // ~44 m away
            'longitude' => self::LNG,
            'radius_m' => 150,
        ]);

        $farPartner = $this->joinablePartner(['name' => 'Far Gym']);
        GymLocation::factory()->for($farPartner)->create([
            'latitude' => self::LAT + 0.0011, // ~122 m away
            'longitude' => self::LNG,
            'radius_m' => 150,
        ]);
        GymLocation::factory()->for($farPartner)->create([
            'latitude' => self::LAT + 0.0009, // ~100 m away
            'longitude' => self::LNG,
            'radius_m' => 150,
        ]);

        $this->actingAs($this->eligibleCustomer(), 'sanctum')
            ->getJson($this->url(self::LAT, self::LNG))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Near Gym')
            ->assertJsonPath('data.1.name', 'Far Gym');
    }

    public function test_a_declined_partner_never_appears_again(): void
    {
        $partner = $this->joinablePartner();
        GymLocation::factory()->for($partner)->create([
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ]);

        $customer = $this->eligibleCustomer();
        JoinPromptDismissal::factory()->declined()->for($customer)->for($partner)->create();

        $this->actingAs($customer, 'sanctum')
            ->getJson($this->url(self::LAT, self::LNG))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Still gone long after any snooze would have expired.
        $this->travel(30)->days();

        $this->actingAs($customer, 'sanctum')
            ->getJson($this->url(self::LAT, self::LNG))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_a_snoozed_partner_is_hidden_until_the_snooze_expires(): void
    {
        $partner = $this->joinablePartner();
        GymLocation::factory()->for($partner)->create([
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ]);

        $customer = $this->eligibleCustomer();
        JoinPromptDismissal::factory()->snoozed()->for($customer)->for($partner)->create();

        $this->actingAs($customer, 'sanctum')
            ->getJson($this->url(self::LAT, self::LNG))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // 25 hours later the snooze has expired: the Partner reappears even
        // though the (now stale) dismissal row is still there.
        $this->travel(25)->hours();

        $this->actingAs($customer, 'sanctum')
            ->getJson($this->url(self::LAT, self::LNG))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $partner->id);

        $this->assertDatabaseCount('join_prompt_dismissals', 1);
    }

    public function test_another_customers_dismissals_do_not_leak(): void
    {
        $partner = $this->joinablePartner();
        GymLocation::factory()->for($partner)->create([
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ]);

        $customer = $this->eligibleCustomer();
        $decliner = User::factory()->create(['partner_id' => $customer->partner_id]);
        JoinPromptDismissal::factory()->declined()->for($decliner)->for($partner)->create();

        $this->actingAs($customer, 'sanctum')
            ->getJson($this->url(self::LAT, self::LNG))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $partner->id);
    }

    public function test_coordinates_are_validated(): void
    {
        $customer = $this->eligibleCustomer();

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/partners/nearby')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);

        $this->actingAs($customer, 'sanctum')
            ->getJson($this->url(91, 0))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude']);

        $this->actingAs($customer, 'sanctum')
            ->getJson($this->url(0, -181))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['longitude']);
    }

    public function test_the_endpoint_requires_authentication(): void
    {
        $this->getJson($this->url(self::LAT, self::LNG))->assertUnauthorized();
    }

    private function url(float $latitude, float $longitude): string
    {
        return '/api/partners/nearby?'.http_build_query([
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

    /** An active Partner with the Join Prompt enabled and branding set up. */
    private function joinablePartner(array $attributes = []): Partner
    {
        $partner = Partner::factory()->create(array_merge([
            'is_active' => true,
            'geo_prompt_enabled' => true,
            'benefits' => ['Member pricing'],
        ], $attributes));

        PartnerIdentity::create(['partner_id' => $partner->id, 'primary_color' => '#14b3c1']);

        return $partner;
    }

    /**
     * A House Partner customer with no subscription and no staff role: the
     * audience the Join Prompt exists for.
     */
    private function eligibleCustomer(): User
    {
        $house = Partner::factory()->create(['name' => 'Fit Nation']);
        config(['partners.house_partner_id' => $house->id]);

        return User::factory()->create(['partner_id' => $house->id]);
    }
}
