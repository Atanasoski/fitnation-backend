<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Partner payloads the mobile app consumes carry an `is_house` flag derived
 * from Partner::isHouse() (issue #95), so clients never hardcode the House
 * Partner's id (GLOSSARY: House Partner).
 */
class HousePartnerFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_house_customer_sees_is_house_true_on_their_partner_payload(): void
    {
        $customer = $this->houseCustomer();

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.partner.is_house', true);
    }

    public function test_a_gym_member_sees_is_house_false_on_their_partner_payload(): void
    {
        $this->houseCustomer(); // establishes which Partner is the House
        $gym = Partner::factory()->create(['is_active' => true]);
        $member = User::factory()->create(['partner_id' => $gym->id]);

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.partner.is_house', false);
    }

    public function test_the_flag_flips_to_false_in_the_gym_join_response(): void
    {
        $customer = $this->houseCustomer();
        $gym = Partner::factory()->create([
            'is_active' => true,
            'geo_prompt_enabled' => true,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/me/partner', ['partner_id' => $gym->id])
            ->assertOk()
            ->assertJsonPath('user.partner.id', $gym->id)
            ->assertJsonPath('user.partner.is_house', false);
    }

    public function test_the_branding_payload_carries_the_flag_for_house_and_gym_partners(): void
    {
        $house = $this->houseCustomer()->partner;
        $gym = Partner::factory()->create(['is_active' => true]);

        $this->getJson("/api/partners/{$house->slug}/branding")
            ->assertOk()
            ->assertJsonPath('data.is_house', true);

        $this->getJson("/api/partners/{$gym->slug}/branding")
            ->assertOk()
            ->assertJsonPath('data.is_house', false);
    }

    /** A customer on the House Partner: Fit Nation itself. */
    private function houseCustomer(): User
    {
        $house = Partner::factory()->create(['name' => 'Fit Nation']);
        config(['partners.house_partner_id' => $house->id]);

        return User::factory()->create(['partner_id' => $house->id]);
    }
}
