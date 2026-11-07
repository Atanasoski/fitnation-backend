<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Gym Join endpoint (issue #90): one authenticated call moves a House
 * Partner customer onto the target Partner. The move is one-way per ADR 0006
 * — the endpoint only accepts House → active, geo-enabled Partner, never
 * Partner → House or Partner → Partner, and no leave-gym endpoint exists
 * (GLOSSARY: Gym Join, House Partner, Join Prompt).
 */
class GymJoinTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_house_partner_customer_joins_a_gym_and_gets_the_refreshed_user_payload(): void
    {
        $customer = $this->houseCustomer();
        $gym = $this->joinableGym(['name' => 'Iron Temple']);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/me/partner', ['partner_id' => $gym->id]);

        $response->assertOk()
            ->assertJsonPath('user.id', $customer->id)
            ->assertJsonPath('user.partner.id', $gym->id)
            ->assertJsonPath('user.partner.name', 'Iron Temple');

        $this->assertSame($gym->id, $customer->fresh()->partner_id);
    }

    public function test_a_customer_already_on_a_gym_cannot_switch_to_another_gym(): void
    {
        $this->houseCustomer(); // establishes which Partner is the House
        $currentGym = $this->joinableGym();
        $otherGym = $this->joinableGym();
        $member = User::factory()->create(['partner_id' => $currentGym->id]);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/me/partner', ['partner_id' => $otherGym->id])
            ->assertForbidden();

        $this->assertSame($currentGym->id, $member->fresh()->partner_id);
    }

    public function test_a_customer_already_on_a_gym_cannot_rejoin_the_same_gym(): void
    {
        $this->houseCustomer();
        $gym = $this->joinableGym();
        $member = User::factory()->create(['partner_id' => $gym->id]);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/me/partner', ['partner_id' => $gym->id])
            ->assertForbidden();
    }

    public function test_an_inactive_partner_cannot_be_joined(): void
    {
        $customer = $this->houseCustomer();
        $gym = $this->joinableGym(['is_active' => false]);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/me/partner', ['partner_id' => $gym->id])
            ->assertForbidden();

        $this->assertTrue($customer->fresh()->partner->isHouse());
    }

    public function test_a_partner_without_the_join_prompt_enabled_cannot_be_joined(): void
    {
        $customer = $this->houseCustomer();
        $gym = $this->joinableGym(['geo_prompt_enabled' => false]);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/me/partner', ['partner_id' => $gym->id])
            ->assertForbidden();

        $this->assertTrue($customer->fresh()->partner->isHouse());
    }

    public function test_the_house_partner_itself_is_never_a_join_target(): void
    {
        // Even misconfigured as geo-enabled, the House Partner is not a gym:
        // accepting it would hand gym members a way back (ADR 0006).
        $customer = $this->houseCustomer();
        $customer->partner->update(['geo_prompt_enabled' => true, 'is_active' => true]);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/me/partner', ['partner_id' => $customer->partner_id])
            ->assertForbidden();

        $gymMember = User::factory()->create(['partner_id' => $this->joinableGym()->id]);

        $this->actingAs($gymMember, 'sanctum')
            ->postJson('/api/me/partner', ['partner_id' => Partner::houseId()])
            ->assertForbidden();

        $this->assertFalse($gymMember->fresh()->partner->isHouse());
    }

    public function test_a_gym_join_leaves_everything_but_the_partner_untouched(): void
    {
        $customer = $this->houseCustomer();
        $customer->forceFill(['email_verified_at' => now()->subWeek()])->save();
        $before = $customer->fresh();
        $gym = $this->joinableGym();

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/me/partner', ['partner_id' => $gym->id])
            ->assertOk();

        // GLOSSARY: a Gym Join keeps the verified email from the House
        // registration — nothing but the partner changes.
        $after = $customer->fresh();
        $this->assertTrue($before->email_verified_at->equalTo($after->email_verified_at));
        $this->assertSame($before->email, $after->email);
        $this->assertSame($before->name, $after->name);
        $this->assertSame($before->onboarding_completed_at?->toIso8601String(), $after->onboarding_completed_at?->toIso8601String());
    }

    public function test_a_gym_join_works_without_a_subscription_whatever_the_enforcement_flag_says(): void
    {
        // The audience is exactly the unsubscribed House customers, so the
        // endpoint must not sit behind the subscription gate.
        foreach ([true, false] as $enforced) {
            config(['subscriptions.enforced' => $enforced]);

            $customer = $this->houseCustomer();
            $gym = $this->joinableGym();

            $this->actingAs($customer, 'sanctum')
                ->postJson('/api/me/partner', ['partner_id' => $gym->id])
                ->assertOk();

            $this->assertSame($gym->id, $customer->fresh()->partner_id);
        }
    }

    public function test_the_target_partner_id_is_validated(): void
    {
        $customer = $this->houseCustomer();

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/me/partner', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['partner_id']);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/me/partner', ['partner_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['partner_id']);
    }

    public function test_the_endpoint_requires_authentication(): void
    {
        $gym = $this->joinableGym();

        $this->postJson('/api/me/partner', ['partner_id' => $gym->id])
            ->assertUnauthorized();
    }

    public function test_no_route_exists_to_leave_or_revert_a_gym(): void
    {
        // ADR 0006: there is deliberately no leave-gym endpoint. The only
        // verb on /api/me/partner is the join itself.
        $member = User::factory()->create(['partner_id' => $this->joinableGym()->id]);

        $this->actingAs($member, 'sanctum')
            ->deleteJson('/api/me/partner')
            ->assertStatus(405);

        $this->actingAs($member, 'sanctum')
            ->putJson('/api/me/partner', ['partner_id' => null])
            ->assertStatus(405);
    }

    /** An active Partner with the Join Prompt enabled: a valid Gym Join target. */
    private function joinableGym(array $attributes = []): Partner
    {
        return Partner::factory()->create(array_merge([
            'is_active' => true,
            'geo_prompt_enabled' => true,
            'benefits' => ['Member pricing'],
        ], $attributes));
    }

    /** A customer on the House Partner: the only audience a Gym Join accepts. */
    private function houseCustomer(): User
    {
        $house = Partner::factory()->create(['name' => 'Fit Nation']);
        config(['partners.house_partner_id' => $house->id]);

        return User::factory()->create(['partner_id' => $house->id]);
    }
}
