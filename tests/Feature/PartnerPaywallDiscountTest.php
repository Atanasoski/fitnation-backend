<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaywallDiscountTier;
use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Paywall Discount: a super admin gives a partner one of four fixed tiers, and
 * the partner block of its members' /user names the RevenueCat offering that
 * holds that tier's store products. The app shows that offering on the
 * paywall instead of the standard one.
 */
class PartnerPaywallDiscountTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_of_a_discounted_partner_is_told_which_offering_to_show(): void
    {
        $partner = Partner::factory()->create(['paywall_discount_tier' => PaywallDiscountTier::Thirty]);
        $member = User::factory()->create(['partner_id' => $partner->id]);

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.partner.paywall_offering_id', 'discount-30');
    }

    /** Like sponsorship: a deactivated partner gives its members nothing, whatever its tier. */
    public function test_a_deactivated_partner_offers_no_discount(): void
    {
        $partner = Partner::factory()->inactive()->create(['paywall_discount_tier' => PaywallDiscountTier::Thirty]);
        $member = User::factory()->create(['partner_id' => $partner->id]);

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonStructure(['user' => ['partner' => ['paywall_offering_id']]])
            ->assertJsonPath('user.partner.paywall_offering_id', null);
    }

    public function test_an_admin_gives_a_partner_a_tier_on_the_edit_form_and_takes_it_away(): void
    {
        $partner = Partner::factory()->create(['slug' => 'iron-temple']);

        $this->actingAs($this->admin())
            ->put('/partners/iron-temple', $this->form(['paywall_discount_tier' => '30']))
            ->assertSessionHasNoErrors();

        $this->assertSame(PaywallDiscountTier::Thirty, $partner->refresh()->paywall_discount_tier);

        // "None" posts an empty value.
        $this->actingAs($this->admin())
            ->put('/partners/iron-temple', $this->form(['paywall_discount_tier' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull($partner->refresh()->paywall_discount_tier);
    }

    public function test_a_new_partner_can_start_with_a_tier(): void
    {
        $this->actingAs($this->admin())
            ->post('/partners', $this->form(['paywall_discount_tier' => '50']))
            ->assertSessionHasNoErrors();

        $this->assertSame(PaywallDiscountTier::Fifty, Partner::where('slug', 'iron-temple')->firstOrFail()->paywall_discount_tier);
    }

    public function test_only_the_four_tiers_are_accepted(): void
    {
        $partner = Partner::factory()->create(['slug' => 'iron-temple', 'paywall_discount_tier' => PaywallDiscountTier::Ten]);
        $admin = $this->admin();

        foreach (['25', '100', 'thirty'] as $invalid) {
            $this->actingAs($admin)
                ->put('/partners/iron-temple', $this->form(['paywall_discount_tier' => $invalid]))
                ->assertSessionHasErrors('paywall_discount_tier');
            $this->actingAs($admin)
                ->post('/partners', $this->form(['slug' => 'lift-club', 'paywall_discount_tier' => $invalid]))
                ->assertSessionHasErrors('paywall_discount_tier');
        }

        $this->assertSame(PaywallDiscountTier::Ten, $partner->refresh()->paywall_discount_tier);
        $this->assertFalse(Partner::where('slug', 'lift-club')->exists());
    }

    public function test_the_edit_form_offers_none_and_the_four_tiers_with_the_current_one_selected(): void
    {
        Partner::factory()->create(['slug' => 'iron-temple', 'paywall_discount_tier' => PaywallDiscountTier::Twenty]);

        $this->actingAs($this->admin())
            ->get('/partners/iron-temple/edit')
            ->assertOk()
            ->assertSee('name="paywall_discount_tier"', escape: false)
            ->assertSeeInOrder(['Paywall discount', 'None', '10% off', '20% off', '30% off', '50% off'])
            ->assertSee('value="20" selected', escape: false);
    }

    public function test_the_partner_page_shows_the_tier_beside_the_plan(): void
    {
        Partner::factory()->create(['slug' => 'iron-temple', 'paywall_discount_tier' => PaywallDiscountTier::Thirty]);
        Partner::factory()->create(['slug' => 'lift-club']);

        $this->actingAs($this->admin())
            ->get('/admin/partners/iron-temple')
            ->assertOk()
            ->assertSeeInOrder(['Sponsorship until', 'Paywall discount', '30% off']);

        $this->actingAs($this->admin())
            ->get('/admin/partners/lift-club')
            ->assertOk()
            ->assertSeeInOrder(['Sponsorship until', 'Paywall discount', 'None']);
    }

    /**
     * What the edit form posts.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Iron Temple',
            'slug' => 'iron-temple',
            'domain' => '',
            'is_active' => '1',
            'paywall_discount_tier' => '',
            'primary_color' => '#aabbcc',
            'secondary_color' => '#ddeeff',
            'primary_color_dark' => '#123456',
            'secondary_color_dark' => '#654321',
        ];
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->id);

        return $user;
    }
}
